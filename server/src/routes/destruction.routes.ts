import { Router, Response } from 'express';
import path from 'path';
import fs from 'fs';
import crypto from 'crypto';
import { pool } from '../db/connection';
import { AuthenticatedRequest, requireAuth } from '../middlewares/auth';
import { RowDataPacket, ResultSetHeader } from 'mysql2';
import { generateUid, isUUID } from '../utils/uid';
import { resolveDocId } from './document.routes';

export const destructionRouter = Router();
destructionRouter.use(requireAuth);

function formatSqlDateTime(dateVal: any): string | null {
  if (!dateVal) return null;
  const d = new Date(dateVal);
  if (isNaN(d.getTime())) return String(dateVal);
  return d.toISOString().replace('T', ' ').slice(0, 19);
}

function formatSqlDate(dateVal: any): string | null {
  if (!dateVal) return null;
  const d = new Date(dateVal);
  if (isNaN(d.getTime())) return String(dateVal);
  return d.toISOString().slice(0, 10);
}

// Helper: คำนวณ SHA-256 Hash ของไฟล์
function calculateFileSha256(filePath: string): string | null {
  try {
    if (fs.existsSync(filePath)) {
      const buffer = fs.readFileSync(filePath);
      return crypto.createHash('sha256').update(buffer).digest('hex');
    }
  } catch (err) {
    console.warn('[Hash Warning]', err);
  }
  return null;
}

// 1. ดึงสถิติจำนวนเอกสารที่ยังไม่ถูกทำลาย แยกตามปี พ.ศ. (สำหรับวางแผนทำลายรายปี)
destructionRouter.get('/destruction/years', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้น' });
    return;
  }

  try {
    // คำนวณปี พ.ศ. จาก r_date หรือ r_number ที่ลงท้ายด้วย /25xx
    const [rows] = await pool.query<RowDataPacket[]>(`
      SELECT 
        CASE 
          WHEN d.r_date IS NOT NULL AND d.r_date != '0000-00-00 00:00:00' 
            THEN YEAR(d.r_date) + 543
          WHEN d.r_number REGEXP '/[0-9]{4}$'
            THEN CAST(SUBSTRING_INDEX(d.r_number, '/', -1) AS UNSIGNED)
          ELSE YEAR(d.created) + 543
        END AS buddhist_year,
        COUNT(*) as total_count,
        SUM(CASE WHEN d.st = 3 THEN 1 ELSE 0 END) as signed_count,
        SUM(CASE WHEN d.st = 4 THEN 1 ELSE 0 END) as distributed_count,
        MIN(d.r_date) as oldest_date,
        MAX(d.r_date) as newest_date
      FROM docz d
      WHERE d.st != 6
      GROUP BY buddhist_year
      HAVING buddhist_year > 2500 AND buddhist_year <= YEAR(CURDATE()) + 543
      ORDER BY buddhist_year ASC
    `);

    const data = rows.map((r) => ({
      year: Number(r.buddhist_year),
      totalCount: Number(r.total_count || 0),
      signedCount: Number(r.signed_count || 0),
      distributedCount: Number(r.distributed_count || 0),
      oldestDate: formatSqlDate(r.oldest_date),
      newestDate: formatSqlDate(r.newest_date),
    }));

    res.json({ data });
  } catch (err) {
    console.error('[Destruction Years Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงสถิติเอกสารรายปี' });
  }
});

// 2. ดูตัวอย่างรายการเอกสารเป้าหมายที่จะทำลายตามปี พ.ศ.
destructionRouter.get('/destruction/preview', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้น' });
    return;
  }

  const targetYear = Number(req.query.year);
  if (!targetYear || isNaN(targetYear)) {
    res.status(400).json({ message: 'กรุณาระบุปี พ.ศ. ที่ต้องการทำลาย' });
    return;
  }

  const q = String(req.query.q || '').trim();
  const page = Math.max(1, Number(req.query.page) || 1);
  const pageSize = Math.min(200, Math.max(1, Number(req.query.pageSize) || 50));
  const offset = (page - 1) * pageSize;

  try {
    let whereClause = `
      d.st != 6 AND (
        (d.r_date IS NOT NULL AND YEAR(d.r_date) + 543 = ?) OR
        (d.r_number LIKE ?)
      )
    `;
    const params: any[] = [targetYear, `%/${targetYear}`];

    if (q) {
      whereClause += ' AND (d.name LIKE ? OR d.r_number LIKE ? OR d.doc_form_number LIKE ?)';
      params.push(`%${q}%`, `%${q}%`, `%${q}%`);
    }

    const [countRows] = await pool.query<RowDataPacket[]>(
      `SELECT COUNT(*) as total FROM docz d WHERE ${whereClause}`,
      params
    );
    const total = Number(countRows[0]?.total || 0);

    const [docs] = await pool.query<RowDataPacket[]>(`
      SELECT d.id, d.uid, d.r_number, d.r_date, d.doc_form_number, d.doc_date, d.name, d.st, d.file,
             (SELECT COUNT(*) FROM doc_file df WHERE df.docz_id = d.id) as attachment_count
      FROM docz d
      WHERE ${whereClause}
      ORDER BY d.id ASC
      LIMIT ? OFFSET ?
    `, [...params, pageSize, offset]);

    const formattedDocs = docs.map((d) => ({
      id: d.id,
      uid: d.uid || '',
      receiveNumber: d.r_number || `ID-${d.id}`,
      receivedAt: formatSqlDateTime(d.r_date),
      letterNumber: d.doc_form_number || null,
      letterDate: formatSqlDate(d.doc_date),
      subject: d.name || 'ไม่มีชื่อเรื่อง',
      legacyStatus: Number(d.st || 0),
      hasMainFile: Boolean(d.file),
      attachmentCount: Number(d.attachment_count || 0),
    }));

    res.json({
      data: formattedDocs,
      meta: {
        page,
        pageSize,
        total,
        pages: Math.ceil(total / pageSize),
      },
    });
  } catch (err) {
    console.error('[Destruction Preview Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงรายการตัวอย่างเอกสาร' });
  }
});

// 3. สั่งทำลายเอกสารแบบรายปี (Annual Batch Destruction)
destructionRouter.post('/destruction/batch', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้น' });
    return;
  }

  const {
    targetYear,
    title,
    orderNumber,
    approvalNumber,
    approvedDate,
    note,
    purgeFiles = true,
    docIds,
  } = req.body;

  if (!title || typeof title !== 'string' || !title.trim()) {
    res.status(400).json({ message: 'กรุณาระบุชื่อรอบการทำลายเอกสาร' });
    return;
  }

  const targetIds: number[] = Array.isArray(docIds) ? docIds.map(Number).filter((n) => !isNaN(n) && n > 0) : [];
  if (targetIds.length === 0) {
    res.status(400).json({ message: 'กรุณาเลือกเอกสารที่ต้องการทำลายอย่างน้อย 1 รายการ' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const currentBYear = new Date().getFullYear() + 543;
    const batchUid = generateUid();

    // สร้างหมายเลขรอบการทำลาย เช่น DES-2569-001
    const [countRows] = await connection.query<RowDataPacket[]>(
      'SELECT COUNT(*) as cnt FROM doc_destruction_batch WHERE YEAR(created_at) + 543 = ?',
      [currentBYear]
    );
    const seq = Number(countRows[0]?.cnt || 0) + 1;
    const batchNumber = `DES-${currentBYear}-${String(seq).padStart(3, '0')}`;

    // บันทึกลงตาราง doc_destruction_batch
    const [batchResult] = await connection.query<ResultSetHeader>(`
      INSERT INTO doc_destruction_batch (
        uid, batch_number, title, target_year, order_number, approval_number, approved_date, total_docs, note, created_by, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    `, [
      batchUid,
      batchNumber,
      title.trim(),
      targetYear ? Number(targetYear) : null,
      orderNumber ? String(orderNumber).trim() : null,
      approvalNumber ? String(approvalNumber).trim() : null,
      approvedDate || null,
      targetIds.length,
      note ? String(note).trim() : null,
      req.user.id,
    ]);

    const batchId = batchResult.insertId;

    // ประมวลผลเอกสารแต่ละรายการ
    for (const docId of targetIds) {
      const [docRows] = await connection.query<RowDataPacket[]>(
        'SELECT id, uid, r_number, doc_form_number, name, file FROM docz WHERE id = ? FOR UPDATE',
        [docId]
      );
      if (!docRows.length) continue;

      const d = docRows[0];
      const [attRows] = await connection.query<RowDataPacket[]>(
        'SELECT id, file FROM doc_file WHERE docz_id = ?',
        [docId]
      );

      const filesToDelete: string[] = [];
      let mainHash: string | null = null;

      if (d.file) {
        const fullPath = path.resolve(__dirname, '../../../web', d.file);
        filesToDelete.push(d.file);
        if (purgeFiles) {
          mainHash = calculateFileSha256(fullPath);
          if (fs.existsSync(fullPath)) {
            try { fs.unlinkSync(fullPath); } catch {}
          }
        }
      }

      for (const att of attRows) {
        if (att.file) {
          filesToDelete.push(att.file);
          if (purgeFiles) {
            const fullPath = path.resolve(__dirname, '../../../web', att.file);
            if (fs.existsSync(fullPath)) {
              try { fs.unlinkSync(fullPath); } catch {}
            }
          }
        }
      }

      // บันทึกลง doc_destruction_item
      await connection.query(`
        INSERT INTO doc_destruction_item (
          batch_id, doc_id, doc_uid, receive_number, letter_number, subject, purge_files, purged_file_paths, file_hash_sha256, reason, order_number, destroyed_by, destroyed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
      `, [
        batchId,
        docId,
        d.uid || '',
        d.r_number || null,
        d.doc_form_number || null,
        d.name || null,
        purgeFiles ? 1 : 0,
        JSON.stringify(filesToDelete),
        mainHash,
        `ทำลายตามรอบประจำปี ${targetYear || ''} (${title})`,
        orderNumber || approvalNumber || null,
        req.user.id,
      ]);

      // ปรับสถานะ docz เป็น st = 6 (ทำลายแล้ว) และลบ file reference หาก purge
      if (purgeFiles) {
        await connection.query(
          'UPDATE docz SET st = 6, file = NULL, destroyed_at = NOW() WHERE id = ?',
          [docId]
        );
        await connection.query(
          'UPDATE doc_file SET file = NULL WHERE docz_id = ?',
          [docId]
        );
      } else {
        await connection.query(
          'UPDATE docz SET st = 6, destroyed_at = NOW() WHERE id = ?',
          [docId]
        );
      }
    }

    await connection.commit();

    res.json({
      data: {
        batchId,
        batchUid,
        batchNumber,
        totalDocs: targetIds.length,
        purgeFiles,
      },
      message: `บันทึกการทำลายเอกสารรอบ ${batchNumber} เรียบร้อยแล้ว (จำนวน ${targetIds.length} เรื่อง)`,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Batch Destruction Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการทำลายเอกสารแบบรายปี' });
  } finally {
    connection.release();
  }
});

// 4. สั่งทำลายเอกสารแบบรายเรื่อง / หลายเรื่องที่เลือก (Individual or Ad-hoc Destruction)
destructionRouter.post('/destruction/single', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้น' });
    return;
  }

  const { docIds, reason, orderNumber, purgeFiles = true } = req.body;

  const rawIds = Array.isArray(docIds) ? docIds : [docIds];
  if (rawIds.length === 0 || !rawIds[0]) {
    res.status(400).json({ message: 'กรุณาระบุเอกสารที่ต้องการทำลาย' });
    return;
  }

  if (!reason || typeof reason !== 'string' || !reason.trim()) {
    res.status(400).json({ message: 'กรุณาระบุเหตุผลในการทำลายเอกสาร' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    let destroyedCount = 0;

    for (const rawId of rawIds) {
      const resolved = await resolveDocId(rawId);
      if (!resolved) continue;

      const docId = resolved.id;
      const [docRows] = await connection.query<RowDataPacket[]>(
        'SELECT id, uid, r_number, doc_form_number, name, file, st FROM docz WHERE id = ? FOR UPDATE',
        [docId]
      );
      if (!docRows.length) continue;

      const d = docRows[0];
      if (d.st === 6) continue; // ข้ามเอกสารที่ทำลายไปแล้ว

      const [attRows] = await connection.query<RowDataPacket[]>(
        'SELECT id, file FROM doc_file WHERE docz_id = ?',
        [docId]
      );

      const filesToDelete: string[] = [];
      let mainHash: string | null = null;

      if (d.file) {
        const fullPath = path.resolve(__dirname, '../../../web', d.file);
        filesToDelete.push(d.file);
        if (purgeFiles) {
          mainHash = calculateFileSha256(fullPath);
          if (fs.existsSync(fullPath)) {
            try { fs.unlinkSync(fullPath); } catch {}
          }
        }
      }

      for (const att of attRows) {
        if (att.file) {
          filesToDelete.push(att.file);
          if (purgeFiles) {
            const fullPath = path.resolve(__dirname, '../../../web', att.file);
            if (fs.existsSync(fullPath)) {
              try { fs.unlinkSync(fullPath); } catch {}
            }
          }
        }
      }

      // บันทึกลง doc_destruction_item (batch_id = NULL สำหรับทำลายรายเรื่อง)
      await connection.query(`
        INSERT INTO doc_destruction_item (
          batch_id, doc_id, doc_uid, receive_number, letter_number, subject, purge_files, purged_file_paths, file_hash_sha256, reason, order_number, destroyed_by, destroyed_at
        ) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
      `, [
        docId,
        d.uid || resolved.uid,
        d.r_number || null,
        d.doc_form_number || null,
        d.name || null,
        purgeFiles ? 1 : 0,
        JSON.stringify(filesToDelete),
        mainHash,
        reason.trim(),
        orderNumber ? String(orderNumber).trim() : null,
        req.user.id,
      ]);

      // ปรับสถานะ docz เป็น st = 6 (ทำลายแล้ว)
      if (purgeFiles) {
        await connection.query(
          'UPDATE docz SET st = 6, file = NULL, destroyed_at = NOW() WHERE id = ?',
          [docId]
        );
        await connection.query(
          'UPDATE doc_file SET file = NULL WHERE docz_id = ?',
          [docId]
        );
      } else {
        await connection.query(
          'UPDATE docz SET st = 6, destroyed_at = NOW() WHERE id = ?',
          [docId]
        );
      }

      destroyedCount++;
    }

    await connection.commit();

    res.json({
      data: {
        destroyedCount,
        purgeFiles,
      },
      message: `ทำลายเอกสารเรียบร้อยแล้ว จำนวน ${destroyedCount} เรื่อง`,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Single Destruction Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการทำลายเอกสาร' });
  } finally {
    connection.release();
  }
});

// 5. ดึงประวัติรอบการทำลายเอกสาร (Destruction Batches History)
destructionRouter.get('/destruction/history', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้น' });
    return;
  }

  try {
    const [rows] = await pool.query<RowDataPacket[]>(`
      SELECT b.id, b.uid, b.batch_number, b.title, b.target_year, b.order_number, b.approval_number, b.approved_date,
             b.total_docs, b.note, b.created_at,
             CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as creator_name,
             u.username as creator_username
      FROM doc_destruction_batch b
      LEFT JOIN user u ON u.id = b.created_by
      LEFT JOIN user_profile p ON p.user_id = b.created_by
      ORDER BY b.id DESC
    `);

    const data = rows.map((r) => ({
      id: r.id,
      uid: r.uid,
      batchNumber: r.batch_number,
      title: r.title,
      targetYear: r.target_year,
      orderNumber: r.order_number,
      approvalNumber: r.approval_number,
      approvedDate: formatSqlDate(r.approved_date),
      totalDocs: Number(r.total_docs || 0),
      note: r.note,
      createdAt: formatSqlDateTime(r.created_at),
      creatorName: r.creator_name?.trim() || r.creator_username || '—',
    }));

    res.json({ data });
  } catch (err) {
    console.error('[Destruction History Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงประวัติรอบการทำลาย' });
  }
});

// 6. ดึงรายละเอียดรอบการทำลาย พร้อมรายการเอกสารภายในรอบ
destructionRouter.get('/destruction/history/:idOrUid', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้น' });
    return;
  }

  const idOrUid = req.params.idOrUid;
  const isUid = isUUID(idOrUid);

  try {
    const [batchRows] = await pool.query<RowDataPacket[]>(`
      SELECT b.id, b.uid, b.batch_number, b.title, b.target_year, b.order_number, b.approval_number, b.approved_date,
             b.total_docs, b.note, b.created_at,
             CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as creator_name,
             u.username as creator_username
      FROM doc_destruction_batch b
      LEFT JOIN user u ON u.id = b.created_by
      LEFT JOIN user_profile p ON p.user_id = b.created_by
      WHERE ${isUid ? 'b.uid = ?' : 'b.id = ?'}
      LIMIT 1
    `, [idOrUid]);

    if (!batchRows.length) {
      res.status(404).json({ message: 'ไม่พบรอบการทำลายที่ระบุ' });
      return;
    }

    const b = batchRows[0];

    // ดึงรายการเอกสารในรอบนี้
    const [itemRows] = await pool.query<RowDataPacket[]>(`
      SELECT i.id, i.doc_id, i.doc_uid, i.receive_number, i.letter_number, i.subject, i.purge_files,
             i.file_hash_sha256, i.reason, i.destroyed_at
      FROM doc_destruction_item i
      WHERE i.batch_id = ?
      ORDER BY i.id ASC
    `, [b.id]);

    const items = itemRows.map((it) => ({
      id: it.id,
      docId: it.doc_id,
      docUid: it.doc_uid,
      receiveNumber: it.receive_number,
      letterNumber: it.letter_number,
      subject: it.subject,
      purgeFiles: Boolean(it.purge_files),
      fileHash: it.file_hash_sha256,
      reason: it.reason,
      destroyedAt: formatSqlDateTime(it.destroyed_at),
    }));

    res.json({
      data: {
        batch: {
          id: b.id,
          uid: b.uid,
          batchNumber: b.batch_number,
          title: b.title,
          targetYear: b.target_year,
          orderNumber: b.order_number,
          approvalNumber: b.approval_number,
          approvedDate: formatSqlDate(b.approved_date),
          totalDocs: Number(b.total_docs || 0),
          note: b.note,
          createdAt: formatSqlDateTime(b.created_at),
          creatorName: b.creator_name?.trim() || b.creator_username || '—',
        },
        items,
      },
    });
  } catch (err) {
    console.error('[Destruction Batch Detail Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงข้อมูลรอบการทำลาย' });
  }
});

// 7. ทะเบียนหนังสือที่ถูกทำลาย (Disposed Documents Register - ค้นหาและดูย้อนหลัง)
destructionRouter.get('/destruction/documents', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const q = String(req.query.q || '').trim();
  const year = req.query.year ? Number(req.query.year) : null;
  const page = Math.max(1, Number(req.query.page) || 1);
  const pageSize = Math.min(100, Math.max(1, Number(req.query.pageSize) || 20));
  const offset = (page - 1) * pageSize;

  try {
    let whereClause = '1=1';
    const params: any[] = [];

    if (q) {
      whereClause += ' AND (i.subject LIKE ? OR i.receive_number LIKE ? OR i.letter_number LIKE ? OR b.batch_number LIKE ?)';
      params.push(`%${q}%`, `%${q}%`, `%${q}%`, `%${q}%`);
    }

    if (year) {
      whereClause += ' AND (YEAR(i.destroyed_at) + 543 = ? OR b.target_year = ?)';
      params.push(year, year);
    }

    const [countRows] = await pool.query<RowDataPacket[]>(`
      SELECT COUNT(*) as total
      FROM doc_destruction_item i
      LEFT JOIN doc_destruction_batch b ON b.id = i.batch_id
      WHERE ${whereClause}
    `, params);
    const total = Number(countRows[0]?.total || 0);

    const [rows] = await pool.query<RowDataPacket[]>(`
      SELECT i.id, i.doc_id, i.doc_uid, i.receive_number, i.letter_number, i.subject, i.purge_files,
             i.file_hash_sha256, i.reason, i.order_number as item_order, i.destroyed_at,
             b.id as batch_id, b.batch_number, b.title as batch_title, b.order_number as batch_order,
             CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as destroyed_by_name,
             u.username as destroyed_by_username
      FROM doc_destruction_item i
      LEFT JOIN doc_destruction_batch b ON b.id = i.batch_id
      LEFT JOIN user u ON u.id = i.destroyed_by
      LEFT JOIN user_profile p ON p.user_id = i.destroyed_by
      WHERE ${whereClause}
      ORDER BY i.id DESC
      LIMIT ? OFFSET ?
    `, [...params, pageSize, offset]);

    const data = rows.map((r) => ({
      id: r.id,
      docId: r.doc_id,
      docUid: r.doc_uid,
      receiveNumber: r.receive_number || `ID-${r.doc_id}`,
      letterNumber: r.letter_number || '—',
      subject: r.subject || 'ไม่มีชื่อเรื่อง',
      purgeFiles: Boolean(r.purge_files),
      fileHash: r.file_hash_sha256,
      reason: r.reason || 'ทำลายตามระเบียบงานสารบรรณ',
      orderNumber: r.item_order || r.batch_order || '—',
      batchId: r.batch_id,
      batchNumber: r.batch_number || 'ทำลายเฉพาะเรื่อง',
      batchTitle: r.batch_title || null,
      destroyedAt: formatSqlDateTime(r.destroyed_at),
      destroyedByName: r.destroyed_by_name?.trim() || r.destroyed_by_username || 'เจ้าหน้าที่สารบรรณ',
    }));

    res.json({
      data,
      meta: {
        page,
        pageSize,
        total,
        pages: Math.ceil(total / pageSize),
      },
    });
  } catch (err) {
    console.error('[Destroyed Documents Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงทะเบียนหนังสือที่ถูกทำลาย' });
  }
});
