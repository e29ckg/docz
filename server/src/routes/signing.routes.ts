import { Router, Response } from 'express';
import { pool } from '../db/connection';
import { AuthenticatedRequest, requireAuth } from '../middlewares/auth';
import { RowDataPacket, ResultSetHeader } from 'mysql2';
import { stampEndPdf } from '../utils/pdfStamper';
import { resolveDocId } from './document.routes';
import { isUUID } from '../utils/uid';

export const signingRouter = Router();
signingRouter.use(requireAuth);

// ตัวช่วยสร้างข้อความตรายางมาตรฐานตามบทบาท
function getStampsByRole(roleId: number, isLastStep: boolean = false): string[] {
  if (roleId === 2) {
    // หัวหน้ากลุ่มงานสารบรรณ
    return [
      'เรียน ผู้อำนวยการสำนักงานฯ / เพื่อโปรดพิจารณา',
      'เรียน ผู้อำนวยการสำนักงานฯ / เพื่อโปรดทราบ',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดทราบ',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดพิจารณา',
    ];
  } else if (roleId === 8) {
    // ผู้อำนวยการฯ
    if (isLastStep) {
      return [
        '- ทราบ / - ดำเนินการตามเสนอ',
        '- ทราบ',
      ];
    }
    return [
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดพิจารณา',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดทราบและพิจารณา',
      'เรียน ผู้พิพากษาหัวหน้าศาลฯ / เพื่อโปรดทราบ',
      '- ทราบ / - ดำเนินการตามเสนอ',
      '- ทราบ',
    ];
  } else if (roleId === 9) {
    // ผู้พิพากษาหัวหน้าศาลฯ
    return [
      '- ทราบ / - ดำเนินการตามเสนอ',
      '- อนุญาต / - ดำเนินการตามเสนอ',
      '- ทราบ / - แจ้งเข้าร่วม / - ดำเนินการตามเสนอ',
      '- ทราบ',
    ];
  }

  return [
    '- เพื่อโปรดพิจารณา',
    '- เพื่อโปรดทราบ',
    '- ทราบ / - ดำเนินการตามเสนอ',
    '- ทราบ',
  ];
}

// 1. ดึงจำนวนเอกสารรอลงนาม (สำหรับแสดง Badge ใน Sidebar)
signingRouter.get('/signing/count', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const userId = req.user?.id;

    // ดึงเฉพาะจำนวนเอกสารรอลงนามตามบทบาทที่ตนเองมีสิทธิ์ใน role_power เท่านั้น
    const [rows] = await pool.query<RowDataPacket[]>(
      `SELECT COUNT(DISTINCT dm.id) as pendingCount
       FROM doc_manage dm
       INNER JOIN docz d ON d.id = dm.doc_id
       WHERE dm.st = 2
         AND dm.role_name_id IN (SELECT role_name_id FROM role_power WHERE user_id = ?)`,
      [userId]
    );
    const pendingCount = rows[0]?.pendingCount || 0;

    // นับจำนวนเอกสารถูกตีกลับที่กำลังรอให้ตนเองดำเนินการแก้ไข/พิจารณาใหม่
    const [retRows] = await pool.query<RowDataPacket[]>(
      `SELECT COUNT(DISTINCT dm.id) as returnedCount
       FROM doc_manage dm
       INNER JOIN docz d ON d.id = dm.doc_id
       INNER JOIN doc_manage ret ON ret.doc_id = dm.doc_id AND ret.sort > dm.sort AND ret.detail LIKE '[ตีกลับ]%'
       WHERE dm.st = 2
         AND dm.role_name_id IN (SELECT role_name_id FROM role_power WHERE user_id = ?)`,
      [userId]
    );
    const returnedCount = retRows[0]?.returnedCount || 0;

    res.json({
      data: {
        pendingCount: Number(pendingCount),
        returnedCount: Number(returnedCount),
      },
    });
  } catch (err) {
    console.error('[Signing Count Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงจำนวนเอกสารรอลงนาม' });
  }
});

// 2. ดึงรายการเอกสารรอลงนาม (Pending Signing)
signingRouter.get('/signing/pending', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const userId = req.user?.id;
    const roleIdFilter = req.query.roleId ? Number(req.query.roleId) : null;
    const onlyReturned = req.query.returned === 'true' || req.query.returned === '1';

    // ดึงรายการที่รอลงนามในบทบาทที่ตนเองมีสิทธิ์ใน role_power
    let query = `
      SELECT dm.id as docManageId, dm.doc_id as docId, d.uid as docUid, dm.sort as stepOrder,
             dm.role_name_id as roleId, rn.name as roleName, dm.created as assignedAt,
             d.r_number as receiveNumber, d.r_date as receivedAt, d.doc_speed as priority,
             d.doc_form_number as letterNumber, d.doc_date as letterDate,
             d.doc_to as docTo, d.doc_form as docForm, d.name as subject,
             d.file as mainFile,
             (SELECT ret.detail FROM doc_manage ret WHERE ret.doc_id = dm.doc_id AND ret.sort > dm.sort AND ret.detail LIKE '[ตีกลับ]%' ORDER BY ret.id DESC LIMIT 1) as returnReason,
             (SELECT ret.updated FROM doc_manage ret WHERE ret.doc_id = dm.doc_id AND ret.sort > dm.sort AND ret.detail LIKE '[ตีกลับ]%' ORDER BY ret.id DESC LIMIT 1) as returnedAt,
             (SELECT CONCAT(COALESCE(p2.pfname, ''), COALESCE(p2.name, ''), ' ', COALESCE(p2.sname, ''))
              FROM doc_manage ret
              LEFT JOIN user_profile p2 ON p2.user_id = ret.user_id
              WHERE ret.doc_id = dm.doc_id AND ret.sort > dm.sort AND ret.detail LIKE '[ตีกลับ]%' ORDER BY ret.id DESC LIMIT 1) as returnerName,
             (SELECT rn2.name
              FROM doc_manage ret
              LEFT JOIN role_name rn2 ON rn2.id = ret.role_name_id
              WHERE ret.doc_id = dm.doc_id AND ret.sort > dm.sort AND ret.detail LIKE '[ตีกลับ]%' ORDER BY ret.id DESC LIMIT 1) as returnerRole
      FROM doc_manage dm
      INNER JOIN docz d ON d.id = dm.doc_id
      LEFT JOIN role_name rn ON rn.id = dm.role_name_id
      WHERE dm.st = 2
        AND dm.role_name_id IN (SELECT role_name_id FROM role_power WHERE user_id = ?)
    `;
    const params: any[] = [userId];

    if (roleIdFilter) {
      query += ` AND dm.role_name_id = ?`;
      params.push(roleIdFilter);
    }

    if (onlyReturned) {
      query += ` AND EXISTS (SELECT 1 FROM doc_manage ret WHERE ret.doc_id = dm.doc_id AND ret.sort > dm.sort AND ret.detail LIKE '[ตีกลับ]%')`;
    }

    query += ` ORDER BY d.id DESC, dm.sort ASC`;

    const [rows] = await pool.query<RowDataPacket[]>(query, params);

    const data = rows.map((r) => ({
      docManageId: r.docManageId,
      docId: r.docId,
      docUid: r.docUid || '',
      stepOrder: r.stepOrder,
      roleId: r.roleId,
      roleName: r.roleName || `บทบาท #${r.roleId}`,
      assignedAt: r.assignedAt,
      receiveNumber: r.receiveNumber || '—',
      receivedAt: r.receivedAt,
      priority: r.priority || 'ปกติ',
      letterNumber: r.letterNumber || '—',
      letterDate: r.letterDate,
      docTo: r.docTo || '—',
      docForm: r.docForm || '—',
      subject: r.subject || 'ไม่ระบุเรื่อง',
      hasMainFile: !!r.mainFile,
      isReturned: Boolean(r.returnReason),
      returnReason: r.returnReason ? String(r.returnReason).replace(/^\[ตีกลับ\]\s*/, '') : null,
      returnerName: r.returnerName?.trim() || null,
      returnerRole: r.returnerRole || null,
      returnedAt: r.returnedAt || null,
    }));

    res.json({ data });
  } catch (err) {
    console.error('[Signing Pending Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงรายการเอกสารรอลงนาม' });
  }
});

// 3. ดึงรายการเอกสารที่ลงนามแล้ว (Signed History)
signingRouter.get('/signing/history', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const userId = req.user?.id;

    // ดึงเฉพาะรายการที่ตนเองลงนาม หรืออยู่ในบทบาทที่ตนเองมีสิทธิ์ใน role_power
    let query = `
      SELECT dm.id as docManageId, dm.doc_id as docId, d.uid as docUid, dm.sort as stepOrder,
             dm.role_name_id as roleId, rn.name as roleName, dm.updated as signedAt,
             dm.ty as stamp, dm.detail as signDetail,
             d.r_number as receiveNumber, d.r_date as receivedAt, d.doc_speed as priority,
             d.doc_form_number as letterNumber, d.doc_date as letterDate,
             d.doc_to as docTo, d.doc_form as docForm, d.name as subject,
             d.st as docStatus, d.file as mainFile
      FROM doc_manage dm
      INNER JOIN docz d ON d.id = dm.doc_id
      LEFT JOIN role_name rn ON rn.id = dm.role_name_id
      WHERE dm.st = 3
        AND (dm.user_id = ? OR dm.role_name_id IN (SELECT role_name_id FROM role_power WHERE user_id = ?))
      ORDER BY dm.updated DESC, dm.id DESC LIMIT 100
    `;
    const params: any[] = [userId, userId];

    const [rows] = await pool.query<RowDataPacket[]>(query, params);

    const data = rows.map((r) => ({
      docManageId: r.docManageId,
      docId: r.docId,
      docUid: r.docUid || '',
      stepOrder: r.stepOrder,
      roleId: r.roleId,
      roleName: r.roleName || `บทบาท #${r.roleId}`,
      signedAt: r.signedAt,
      stamp: r.stamp || '',
      signDetail: r.signDetail || '',
      receiveNumber: r.receiveNumber || '—',
      receivedAt: r.receivedAt,
      priority: r.priority || 'ปกติ',
      letterNumber: r.letterNumber || '—',
      letterDate: r.letterDate,
      docTo: r.docTo || '—',
      docForm: r.docForm || '—',
      subject: r.subject || 'ไม่ระบุเรื่อง',
      docStatus: r.docStatus,
      hasMainFile: !!r.mainFile,
    }));

    res.json({ data });
  } catch (err) {
    console.error('[Signing History Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงประวัติการลงนาม' });
  }
});

// 4. ดูรายละเอียดเอกสารและไทม์ไลน์ขั้นตอนการลงนาม
signingRouter.get('/signing/detail/:docId', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const resolved = await resolveDocId(req.params.docId);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบเอกสารนี้ในระบบ' });
      return;
    }
    const docId = resolved.id;

    // 1. ดึงข้อมูลเอกสารหลัก
    const [docRows] = await pool.query<RowDataPacket[]>(
      `SELECT d.*, u.username as creatorUsername,
              CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as creatorName
       FROM docz d
       LEFT JOIN user u ON u.id = d.user_create
       LEFT JOIN user_profile p ON p.user_id = d.user_create
       WHERE d.id = ? LIMIT 1`,
      [docId]
    );

    if (!docRows.length) {
      res.status(404).json({ message: 'ไม่พบเอกสารนี้ในระบบ' });
      return;
    }
    const doc = docRows[0];

    // 2. ดึงเอกสารแนบจาก doc_file
    const [fileRows] = await pool.query<RowDataPacket[]>(
      'SELECT id, uid, name, file, ext FROM doc_file WHERE docz_id = ? ORDER BY id ASC',
      [docId]
    );

    // 3. ดึงขั้นตอนทั้งหมดจาก doc_manage
    const [manageRows] = await pool.query<RowDataPacket[]>(
      `SELECT dm.id, dm.doc_id, dm.doc_form, dm.role_name_id, dm.user_id,
              dm.sort, dm.detail, dm.st, dm.updated, dm.created, dm.ty,
              rn.name as roleName,
              CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as signerName,
              p.sign_photo as signPhoto,
              u.username as signerUsername
       FROM doc_manage dm
       LEFT JOIN role_name rn ON rn.id = dm.role_name_id
       LEFT JOIN user u ON u.id = dm.user_id
       LEFT JOIN user_profile p ON p.user_id = dm.user_id
       WHERE dm.doc_id = ?
       ORDER BY dm.sort ASC, dm.id ASC`,
      [docId]
    );

    // ดึงข้อมูลรูปภาพลายเซ็นของผู้ใช้งานปัจจุบัน (Current User Sign Photo) สำหรับพรีวิวในฟอร์มลงนาม
    let currentUserSignPhoto: string | null = null;
    let currentUserName: string = req.user?.username || '';
    if (req.user?.id) {
      const [uProfileRows] = await pool.query<RowDataPacket[]>(
        `SELECT p.sign_photo,
                CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as fullName
         FROM user_profile p
         WHERE p.user_id = ? LIMIT 1`,
        [req.user.id]
      );
      if (uProfileRows.length) {
        const up = uProfileRows[0];
        if (up.fullName && up.fullName.trim()) {
          currentUserName = up.fullName.trim();
        }
        if (up.sign_photo && String(up.sign_photo).trim()) {
          const sp = String(up.sign_photo).trim();
          currentUserSignPhoto = sp.startsWith('/') ? sp : '/' + sp;
        }
      }
    }

    // ค้นหาขั้นตอนที่กำลังรอดำเนินการ (st = 2)
    const activeStep = manageRows.find((m) => m.st === 2);
    const isLastStep = activeStep && activeStep.sort === manageRows.length;
    const suggestedStamps = activeStep ? getStampsByRole(activeStep.role_name_id, Boolean(isLastStep)) : [];

    // ดึงรายการโปรไฟล์ทั้งหมดสำหรับสลับ/เปลี่ยนสายการลงนาม
    const [profiles] = await pool.query<RowDataPacket[]>(
      'SELECT id, code, name FROM doc_profile ORDER BY id ASC'
    );
    const [profileSteps] = await pool.query<RowDataPacket[]>(`
      SELECT dps.id, dps.doc_profile_id, dps.role_name_id, dps.sort, rn.name as role_name
      FROM doc_profile_sub dps
      INNER JOIN role_name rn ON rn.id = dps.role_name_id
      ORDER BY dps.sort ASC, dps.id ASC
    `);
    const availableProfiles = profiles.map((p) => {
      const pSteps = profileSteps.filter(
        (s) => String(s.doc_profile_id) === String(p.id) || String(s.doc_profile_id) === String(p.code)
      );
      return {
        id: p.id,
        code: p.code,
        name: p.name,
        steps: pSteps.map((s, idx) => ({
          id: s.id,
          roleId: s.role_name_id,
          roleName: s.role_name,
          stepOrder: idx + 1,
        })),
      };
    });

    // ตรวจสอบว่ามีขั้นตอนที่มีการตีกลับหรือไม่
    const returnedStep = manageRows.slice().reverse().find((m) => m.detail && String(m.detail).startsWith('[ตีกลับ]'));

    res.json({
      data: {
        document: {
          id: doc.id,
          uid: doc.uid || '',
          receiveNumber: doc.r_number,
          receivedAt: doc.r_date,
          priority: doc.doc_speed || 'ปกติ',
          letterNumber: doc.doc_form_number || '',
          letterDate: doc.doc_date,
          docTo: doc.doc_to,
          docForm: doc.doc_form,
          subject: doc.name,
          status: doc.st,
          mainFile: doc.file,
          creatorName: doc.creatorName?.trim() || doc.creatorUsername || '—',
          createdAt: doc.created,
          attachments: fileRows.map((f) => ({
            id: f.id,
            uid: f.uid || '',
            name: f.name,
            file: f.file,
            extension: f.ext,
          })),
        },
        steps: manageRows.map((m) => {
          let stepSignPhoto = m.signPhoto ? String(m.signPhoto).trim() : null;
          if (stepSignPhoto) {
            stepSignPhoto = stepSignPhoto.startsWith('/') ? stepSignPhoto : '/' + stepSignPhoto;
          }
          return {
            id: m.id,
            docId: m.doc_id,
            roleId: m.role_name_id,
            roleName: m.roleName || `บทบาท #${m.role_name_id}`,
            sort: m.sort,
            status: m.st || 1, // 1=รอดำเนินการ, 2=กำลังลงนาม, 3=ลงนามแล้ว
            isReturned: Boolean(m.detail && String(m.detail).startsWith('[ตีกลับ]')),
            stamp: m.ty || '',
            detail: m.detail || '',
            signerName: m.signerName?.trim() || m.signerUsername || '',
            signPhoto: stepSignPhoto,
            signedAt: m.updated,
            createdAt: m.created,
          };
        }),
        activeStepId: activeStep?.id || null,
        currentUserSignPhoto,
        currentUserName,
        suggestedStamps,
        availableProfiles,
        returnInfo: returnedStep ? {
          stepId: returnedStep.id,
          returnerName: returnedStep.signerName?.trim() || returnedStep.signerUsername || 'ผู้ลงนาม',
          returnerRole: returnedStep.roleName || `บทบาท #${returnedStep.role_name_id}`,
          reason: String(returnedStep.detail).replace(/^\[ตีกลับ\]\s*/, ''),
          returnedAt: returnedStep.updated,
        } : null,
      },
    });
  } catch (err) {
    console.error('[Signing Detail Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงรายละเอียดเอกสาร' });
  }
});

// 5. บันทึกและลงนามส่งต่อ / สั่งการเสร็จสิ้น (Sign & Forward / Finish)
signingRouter.post('/signing/sign', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { docId: rawDocId, docManageId, stamp, detail, actionType, forwardRoleId } = req.body;

  if (!rawDocId || !docManageId) {
    res.status(400).json({ message: 'ข้อมูลระบุเอกสารหรือขั้นตอนไม่ครบถ้วน' });
    return;
  }

  let docId = Number(rawDocId);
  if (isUUID(rawDocId)) {
    const resolved = await resolveDocId(rawDocId);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบเอกสารนี้' });
      return;
    }
    docId = resolved.id;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    // 1. ตรวจสอบขั้นตอนปัจจุบัน
    const [stepRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, doc_id, sort, role_name_id, st, doc_form FROM doc_manage WHERE id = ? AND doc_id = ? FOR UPDATE',
      [docManageId, docId]
    );

    if (!stepRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบขั้นตอนการลงนามนี้' });
      return;
    }

    const currentStep = stepRows[0];

    // ตรวจสอบว่าผู้ใช้งานมีสิทธิ์ในบทบาทการลงนามนี้จริงหรือไม่ (ตามตาราง role_power)
    const [powerRows] = await connection.query<RowDataPacket[]>(
      'SELECT id FROM role_power WHERE user_id = ? AND role_name_id = ? LIMIT 1',
      [req.user!.id, currentStep.role_name_id]
    );

    if (!powerRows.length) {
      await connection.rollback();
      res.status(403).json({ message: 'คุณไม่มีสิทธิ์ลงนามในบทบาทนี้' });
      return;
    }

    // 2. อัปเดตขั้นตอนปัจจุบันเป็นลงนามแล้ว (st = 3)
    await connection.query(
      `UPDATE doc_manage
       SET st = 3, user_id = ?, ty = ?, detail = ?, updated = NOW()
       WHERE id = ?`,
      [req.user!.id, stamp ? String(stamp).trim() : null, detail ? String(detail).trim() : null, currentStep.id]
    );

    // กรณีเลือกสั่งการเสร็จสิ้น (ยุติเรื่องที่ขั้นตอนนี้)
    if (actionType === 'finish') {
      // ลบขั้นตอนที่รออยู่ถัดไปออก เพื่อยุติเรื่องทันที
      await connection.query(
        'DELETE FROM doc_manage WHERE doc_id = ? AND sort > ? AND st = 1',
        [docId, currentStep.sort]
      );

      // ปรับสถานะหนังสือ docz เป็นเกษียนครบ (st = 3) พร้อมจ่ายหนังสือ
      await connection.query('UPDATE docz SET st = 3, end = NOW() WHERE id = ?', [docId]);

      await connection.commit();

      // เมื่อเกษียนครบ ให้เพิ่มหน้าบันทึกเกษียนหนังสือเข้าสู่ไฟล์ PDF หลัก
      try {
        await stampEndPdf(docId);
      } catch (stampErr) {
        console.error('[Stamp End Error on Finish]', stampErr);
      }

      res.json({
        data: {
          docId,
          docManageId,
          isFinished: true,
          message: 'บันทึกสั่งการและยุติเรื่องเรียบร้อยแล้ว (เกษียนครบ พร้อมเพิ่มหน้าบันทึกเกษียนลงในไฟล์หลัก)',
        },
      });
      return;
    }

    // กรณีเลือกเสนอต่อ (Forward)
    const nextSort = currentStep.sort + 1;
    const [nextStepRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, role_name_id FROM doc_manage WHERE doc_id = ? AND sort = ? LIMIT 1 FOR UPDATE',
      [docId, nextSort]
    );

    let isFinished = false;
    let forwardMsg = 'ลงนามและส่งต่อไปยังขั้นตอนถัดไปเรียบร้อยแล้ว';

    if (nextStepRows.length) {
      // มีขั้นตอนถัดไปในสายอยู่แล้ว
      const targetRoleId = forwardRoleId ? Number(forwardRoleId) : nextStepRows[0].role_name_id;
      if (targetRoleId !== nextStepRows[0].role_name_id) {
        await connection.query('UPDATE doc_manage SET role_name_id = ?, st = 2, detail = NULL WHERE id = ?', [
          targetRoleId,
          nextStepRows[0].id,
        ]);
      } else {
        await connection.query('UPDATE doc_manage SET st = 2, detail = NULL WHERE id = ?', [nextStepRows[0].id]);
      }
      await connection.query('UPDATE docz SET st = 2 WHERE id = ?', [docId]);
    } else if (forwardRoleId) {
      // ไม่มีขั้นตอนถัดไปเดิม (เช่น สายเดิมสิ้นสุดที่ ผอ.) แต่ต้องการเสนอต่อ (เช่น เสนอต่อหัวหน้าศาลฯ)
      await connection.query(
        `INSERT INTO doc_manage (doc_form, doc_id, user_id, role_name_id, sort, created, st)
         VALUES (?, ?, ?, ?, ?, NOW(), 2)`,
        [currentStep.doc_form || 'DocZ', docId, req.user!.id, forwardRoleId, nextSort]
      );

      const [roleInfo] = await connection.query<RowDataPacket[]>(
        'SELECT name FROM role_name WHERE id = ? LIMIT 1',
        [forwardRoleId]
      );
      const nextRoleName = roleInfo[0]?.name || '';
      if (nextRoleName) {
        await connection.query('UPDATE docz SET st = 2, doc_to = ? WHERE id = ?', [nextRoleName, docId]);
        forwardMsg = `ลงนามและส่งต่อไปยัง ${nextRoleName} เรียบร้อยแล้ว`;
      } else {
        await connection.query('UPDATE docz SET st = 2 WHERE id = ?', [docId]);
      }
    } else {
      // ไม่มีขั้นตอนถัดไป และไม่ได้ระบุส่งต่อ -> เอกสารเสร็จสมบูรณ์ (เกษียนครบ)
      isFinished = true;
      await connection.query('UPDATE docz SET st = 3, end = NOW() WHERE id = ?', [docId]);
      forwardMsg = 'ลงนามเสร็จสมบูรณ์เรียบร้อยแล้ว (เกษียนครบ พร้อมเพิ่มหน้าบันทึกเกษียนลงในไฟล์หลัก)';
    }

    await connection.commit();

    // เมื่อเกษียนครบ ให้เพิ่มหน้าบันทึกเกษียนหนังสือเข้าสู่ไฟล์ PDF หลัก
    if (isFinished) {
      try {
        await stampEndPdf(docId);
      } catch (stampErr) {
        console.error('[Stamp End Error on Complete]', stampErr);
      }
    }

    res.json({
      data: {
        docId,
        docManageId,
        isFinished,
        message: forwardMsg,
      },
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Sign Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลงนาม' });
  } finally {
    connection.release();
  }
});

// 6. เปลี่ยนสายการลงนามของเอกสาร (Change Route Profile)
signingRouter.post('/signing/change-route', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { docId: rawDocId, profileCode } = req.body;

  if (!rawDocId || !profileCode) {
    res.status(400).json({ message: 'ข้อมูลระบุเอกสารหรือโปรไฟล์ไม่ครบถ้วน' });
    return;
  }

  let docId = Number(rawDocId);
  if (isUUID(rawDocId)) {
    const resolved = await resolveDocId(rawDocId);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบเอกสารนี้' });
      return;
    }
    docId = resolved.id;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const [docRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, doc_form, doc_to, st FROM docz WHERE id = ? FOR UPDATE',
      [docId]
    );

    if (!docRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบเอกสารนี้ในระบบ' });
      return;
    }

    // OWASP A01: ตรวจสอบสิทธิ์สารบรรณ/แอดมิน หรือผู้ลงนามในขั้นตอนปัจจุบัน
    const isOfficerOrAdmin = Boolean(req.user?.registryOfficer || req.user?.isAdmin);
    if (!isOfficerOrAdmin) {
      const [powerRows] = await connection.query<RowDataPacket[]>(
        `SELECT dm.id FROM doc_manage dm
         INNER JOIN role_power rp ON rp.role_name_id = dm.role_name_id AND rp.user_id = ?
         WHERE dm.doc_id = ? AND dm.st = 2 LIMIT 1`,
        [req.user!.id, docId]
      );
      if (!powerRows.length) {
        await connection.rollback();
        res.status(403).json({ message: 'คุณไม่มีสิทธิ์เปลี่ยนสายการลงนามของเอกสารนี้' });
        return;
      }
    }

    // ดึงโปรไฟล์ใหม่จาก doc_profile
    const [profRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, code, name FROM doc_profile WHERE code = ? OR id = ? LIMIT 1',
      [profileCode, profileCode]
    );

    if (!profRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบโปรไฟล์สายการลงนามที่ระบุ' });
      return;
    }

    const newProfile = profRows[0];

    // ดึงขั้นตอนของโปรไฟล์ใหม่
    const [newSubRows] = await connection.query<RowDataPacket[]>(
      'SELECT role_name_id, sort FROM doc_profile_sub WHERE doc_profile_id = ? OR doc_profile_id = ? ORDER BY sort ASC, id ASC',
      [newProfile.id, newProfile.code]
    );

    // ดึงขั้นตอนปัจจุบันของเอกสารใน doc_manage
    const [currentSteps] = await connection.query<RowDataPacket[]>(
      'SELECT id, sort, role_name_id, st FROM doc_manage WHERE doc_id = ? ORDER BY sort ASC',
      [docId]
    );

    const signedSteps = currentSteps.filter((s) => s.st === 3);
    const activeStep = currentSteps.find((s) => s.st === 2);

    if (signedSteps.length === 0) {
      // ยังไม่มีใครลงนามเลย (กำลังรอขั้นตอนที่ 1) -> สลับขั้นตอนใหม่ทั้งหมด
      await connection.query('DELETE FROM doc_manage WHERE doc_id = ?', [docId]);
      for (let i = 0; i < newSubRows.length; i++) {
        const step = newSubRows[i];
        const initialSt = i === 0 ? 2 : 1;
        await connection.query(
          `INSERT INTO doc_manage (doc_form, doc_id, user_id, role_name_id, sort, created, st)
           VALUES (?, ?, ?, ?, ?, NOW(), ?)`,
          [newProfile.code, docId, req.user!.id, step.role_name_id, i + 1, initialSt]
        );
      }
    } else {
      // มีขั้นตอนที่ลงนามแล้ว (st = 3) -> ให้คงขั้นตอนที่ลงนามแล้วและ active ไว้
      // ลบเฉพาะขั้นตอนที่รอดำเนินการ (st = 1) ออก
      await connection.query('DELETE FROM doc_manage WHERE doc_id = ? AND st = 1', [docId]);

      // นำขั้นตอนใหม่ที่อยู่ถัดจากขั้นตอนปัจจุบันมาแทรก
      const baseSort = activeStep ? activeStep.sort : Math.max(...signedSteps.map((s) => s.sort));

      for (let i = 0; i < newSubRows.length; i++) {
        const targetOrder = i + 1;
        if (targetOrder > baseSort) {
          await connection.query(
            `INSERT INTO doc_manage (doc_form, doc_id, user_id, role_name_id, sort, created, st)
             VALUES (?, ?, ?, ?, ?, NOW(), 1)`,
            [newProfile.code, docId, req.user!.id, newSubRows[i].role_name_id, targetOrder]
          );
        }
      }
    }

    // กำหนด doc_to ให้สอดคล้องกับโปรไฟล์ใหม่
    let newDocTo = undefined;
    if (newProfile.code.toLowerCase() === 'docz') {
      newDocTo = 'ผู้พิพากษาหัวหน้าศาลฯ';
    } else if (newProfile.code.toLowerCase() === 'doczpo') {
      newDocTo = 'ผู้อำนวยการฯ';
    }

    if (newDocTo) {
      await connection.query('UPDATE docz SET doc_form = ?, doc_to = ? WHERE id = ?', [
        newProfile.code,
        newDocTo,
        docId,
      ]);
    } else {
      await connection.query('UPDATE docz SET doc_form = ? WHERE id = ?', [
        newProfile.code,
        docId,
      ]);
    }

    await connection.commit();

    res.json({
      data: {
        docId,
        profileCode: newProfile.code,
        profileName: newProfile.name,
      },
      message: `เปลี่ยนสายการลงนามเป็น "${newProfile.name}" เรียบร้อยแล้ว`,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Change Route Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเปลี่ยนสายการลงนาม' });
  } finally {
    connection.release();
  }
});

// 6. ตีกลับเอกสาร (Return Document)
signingRouter.post('/signing/return', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { docId: rawDocId, docManageId, reason } = req.body;

  if (!rawDocId || !docManageId) {
    res.status(400).json({ message: 'ข้อมูลระบุเอกสารหรือขั้นตอนไม่ครบถ้วน' });
    return;
  }

  let docId = Number(rawDocId);
  if (isUUID(rawDocId)) {
    const resolved = await resolveDocId(rawDocId);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบเอกสารนี้' });
      return;
    }
    docId = resolved.id;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const [stepRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, doc_id, sort, role_name_id, st FROM doc_manage WHERE id = ? AND doc_id = ? FOR UPDATE',
      [docManageId, docId]
    );

    if (!stepRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบขั้นตอนการลงนามนี้' });
      return;
    }

    const currentStep = stepRows[0];

    // OWASP A01: ตรวจสอบสิทธิ์ว่าผู้ใช้มีบทบาทในขั้นตอนปัจจุบัน หรือเป็นผู้ดูแลระบบ
    const isOfficerOrAdmin = Boolean(req.user?.registryOfficer || req.user?.isAdmin);
    if (!isOfficerOrAdmin) {
      const [powerRows] = await connection.query<RowDataPacket[]>(
        'SELECT id FROM role_power WHERE user_id = ? AND role_name_id = ? LIMIT 1',
        [req.user!.id, currentStep.role_name_id]
      );
      if (!powerRows.length) {
        await connection.rollback();
        res.status(403).json({ message: 'คุณไม่มีสิทธิ์ตีกลับเอกสารในบทบาทนี้' });
        return;
      }
    }

    const prevSort = currentStep.sort - 1;

    // อัปเดตขั้นตอนปัจจุบัน: เปลี่ยนกลับเป็น st = 1 พร้อมระบุเหตุผล
    await connection.query(
      `UPDATE doc_manage
       SET st = 1, user_id = ?, detail = ?, updated = NOW()
       WHERE id = ?`,
      [req.user!.id, reason ? `[ตีกลับ] ${reason}` : '[ตีกลับ]', currentStep.id]
    );

    if (prevSort <= 0) {
      // ตีกลับไปยังสารบรรณ (st = 5: ถูกตีกลับ)
      await connection.query('UPDATE docz SET st = 5 WHERE id = ?', [docId]);
    } else {
      // ตีกลับไปยังขั้นตอนก่อนหน้า
      await connection.query(
        'UPDATE doc_manage SET st = 2 WHERE doc_id = ? AND sort = ?',
        [docId, prevSort]
      );
      await connection.query('UPDATE docz SET st = 2 WHERE id = ?', [docId]);
    }

    await connection.commit();

    res.json({
      data: {
        docId,
        uid: isUUID(rawDocId) ? rawDocId : undefined,
        docManageId,
        message: 'ตีกลับเอกสารเรียบร้อยแล้ว',
      },
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Return Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการตีกลับเอกสาร' });
  } finally {
    connection.release();
  }
});

// ประทับหน้าเกษียนหนังสือท้ายไฟล์หลัก (Manual / Re-stamp สำหรับหนังสือที่เกษียนครบ st = 3)
signingRouter.post('/documents/:id/stamp-end', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin && !req.user?.isSigner) {
    res.status(403).json({ message: 'เฉพาะผู้ลงนามหรือเจ้าหน้าที่สารบรรณเท่านั้น' });
    return;
  }

  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบเอกสาร' });
    return;
  }
  const docId = resolved.id;
  try {
    const result = await stampEndPdf(docId);
    if (!result.success) {
      res.status(400).json({ message: result.error || 'ไม่สามารถเพิ่มหน้าเกษียนหนังสือได้' });
      return;
    }
    res.json({ data: result, message: 'ประทับหน้าบันทึกเกษียนหนังสือเข้าสู่ไฟล์หลักเรียบร้อยแล้ว' });
  } catch (err) {
    console.error('[Stamp End Manual Error]', err);
    res.status(500).json({ message: err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการประทับหน้าเกษียนหนังสือ' });
  }
});
