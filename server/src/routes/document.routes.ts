import { Router, Response } from 'express';
import path from 'path';
import fs from 'fs';
import multer from 'multer';
import crypto from 'crypto';
import { pool } from '../db/connection';
import { AuthenticatedRequest, requireAuth } from '../middlewares/auth';
import { RowDataPacket, ResultSetHeader } from 'mysql2';
import { stampReceivePdf } from '../utils/pdfStamper';
import { isUUID, generateUid } from '../utils/uid';
import { validateFileExtension, DANGEROUS_EXTENSIONS } from '../utils/fileValidation';
import { validatePdfA4Portrait } from '../utils/pdfValidation';
import { ensureDocumentPins } from '../db/documentPins';

export const documentRouter = Router();

export async function resolveDocId(idOrUid: any): Promise<{ id: number; uid: string } | null> {
  if (!idOrUid) return null;
  const str = Array.isArray(idOrUid) ? String(idOrUid[0]).trim() : String(idOrUid).trim();
  if (isUUID(str)) {
    const [rows] = await pool.query<RowDataPacket[]>('SELECT id, uid FROM docz WHERE uid = ? LIMIT 1', [str]);
    if (rows.length) return { id: rows[0].id, uid: rows[0].uid };
    return null;
  }
  const numericId = Number(str);
  if (isNaN(numericId) || numericId <= 0) return null;
  const [rows] = await pool.query<RowDataPacket[]>('SELECT id, uid FROM docz WHERE id = ? LIMIT 1', [numericId]);
  if (rows.length) return { id: rows[0].id, uid: rows[0].uid };
  return null;
}

export async function resolveAttachmentId(idOrUid: any): Promise<number | null> {
  if (!idOrUid) return null;
  const str = Array.isArray(idOrUid) ? String(idOrUid[0]).trim() : String(idOrUid).trim();
  if (isUUID(str)) {
    const [rows] = await pool.query<RowDataPacket[]>('SELECT id FROM doc_file WHERE uid = ? LIMIT 1', [str]);
    return rows.length ? rows[0].id : null;
  }
  const num = Number(str);
  return isNaN(num) || num <= 0 ? null : num;
}

export function resolveWebFilePath(filePath?: string | null): string | null {
  if (!filePath) return null;
  const clean = String(filePath).trim().replace(/^[/\\]+/, '');
  const withoutWeb = clean.replace(/^web[/\\]+/, '');

  const candidates = [
    path.resolve(__dirname, '../../../web', withoutWeb),
    path.resolve(__dirname, '../../../web', clean),
    path.resolve(__dirname, '../../../', withoutWeb),
    path.resolve(__dirname, '../../../', clean),
    path.resolve(process.cwd(), 'web', withoutWeb),
    path.resolve(process.cwd(), withoutWeb),
  ];

  for (const c of candidates) {
    if (fs.existsSync(c)) {
      return c;
    }
  }
  return null;
}

function formatSqlDateTime(val: any): string | null {
  if (!val) return null;
  if (val instanceof Date) {
    if (isNaN(val.getTime())) return null;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${val.getFullYear()}-${pad(val.getMonth() + 1)}-${pad(val.getDate())}T${pad(val.getHours())}:${pad(val.getMinutes())}:${pad(val.getSeconds())}`;
  }
  const str = String(val).trim();
  if (!str || str === '0000-00-00 00:00:00' || str === '0000-00-00') return null;
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    return str.replace(' ', 'T').slice(0, 19);
  }
  const parsed = new Date(str);
  if (!isNaN(parsed.getTime())) {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}:${pad(parsed.getSeconds())}`;
  }
  return str;
}

function formatSqlDate(val: any): string | null {
  if (!val) return null;
  if (val instanceof Date) {
    if (isNaN(val.getTime())) return null;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${val.getFullYear()}-${pad(val.getMonth() + 1)}-${pad(val.getDate())}`;
  }
  const str = String(val).trim();
  if (!str || str === '0000-00-00 00:00:00' || str === '0000-00-00') return null;
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    return str.slice(0, 10);
  }
  const parsed = new Date(str);
  if (!isNaN(parsed.getTime())) {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}`;
  }
  return str;
}

// ตั้งค่า Multer สำหรับอัปโหลด/แก้ไขหนังสือนำ
const storage = multer.diskStorage({
  destination: (_req, _file, cb) => {
    const targetDir = path.resolve(__dirname, '../../../web/uploads/docz');
    if (!fs.existsSync(targetDir)) {
      fs.mkdirSync(targetDir, { recursive: true });
    }
    cb(null, targetDir);
  },
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    const token = crypto.randomBytes(16).toString('hex');
    cb(null, `${token}${ext}`);
  },
});

const upload = multer({
  storage,
  limits: { fileSize: 30 * 1024 * 1024 }, // 30 MB
  fileFilter: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    if (DANGEROUS_EXTENSIONS.includes(ext)) {
      return cb(new Error(`ไม่อนุญาตให้อัปโหลดไฟล์อันตราย (${ext})`));
    }
    cb(null, true);
  },
});

// 1. รายการหนังสือ (ค้นหา + แบ่งหน้า)
documentRouter.get('/documents', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const userId = req.user!.id;
  const isOfficer = Boolean(req.user?.registryOfficer || req.user?.isAdmin);
  const scope = String(req.query.scope || 'mine');
  const q = String(req.query.q || '').trim();
  const from = String(req.query.from || '').trim();
  const to = String(req.query.to || '').trim();
  const page = Math.max(1, Number(req.query.page) || 1);
  const pageSize = Math.min(100, Math.max(1, Number(req.query.pageSize) || 20));
  const offset = (page - 1) * pageSize;

  try {
    let whereClause = '1=1';
    const params: any[] = [];

    // Scope สิทธิ์การเข้าถึง
    if (scope === 'mine') {
      await ensureDocumentPins();
      // หนังสือของฉัน: ดึงเฉพาะหนังสือที่ได้รับแจกจ่ายถึง user (บันทึกใน doc_user_read)
      whereClause += ' AND d.id IN (SELECT doc_id FROM doc_user_read WHERE user_id = ?)';
      params.push(userId);
    }
    // ค้นหาหนังสือ (scope === 'visible'): ค้นหาจากหนังสือทั้งหมดในระบบ

    // ตัวกรองคำค้นหา
    if (q) {
      whereClause += ' AND (d.name LIKE ? OR d.r_number LIKE ? OR d.doc_form_number LIKE ?)';
      const searchParam = `%${q}%`;
      params.push(searchParam, searchParam, searchParam);
    }

    // ตัวกรองสถานะหนังสือ
    const statusParam = req.query.status;
    if (statusParam !== undefined && statusParam !== null && statusParam !== '') {
      if (scope === 'mine') {
        // ในหน้าหนังสือของฉัน: กรองตามสถานะการอ่าน (read: อ่านแล้ว, unread: ยังไม่ได้อ่าน)
        if (statusParam === 'read' || statusParam === '1') {
          whereClause += ' AND d.id IN (SELECT doc_id FROM doc_user_read WHERE user_id = ? AND `check` = 1)';
          params.push(userId);
        } else if (statusParam === 'unread' || statusParam === '0') {
          whereClause += ' AND d.id IN (SELECT doc_id FROM doc_user_read WHERE user_id = ? AND `check` = 0)';
          params.push(userId);
        }
      } else {
        // ในหน้าทะเบียน/ค้นหา: กรองตามสถานะเอกสาร (st: 1=รอเสนอ, 2=อยู่ระหว่างดำเนินการ, 3=เกษียนครบ, 4=จ่ายแล้ว)
        whereClause += ' AND d.st = ?';
        params.push(Number(statusParam));
      }
    }

    // ตัวกรองชั้นความเร็ว
    const priorityParam = req.query.priority;
    if (priorityParam) {
      whereClause += ' AND d.doc_speed = ?';
      params.push(String(priorityParam).trim());
    }

    // ตัวกรองผู้รับ (เรียน)
    const docToParam = req.query.docTo;
    if (docToParam) {
      whereClause += ' AND d.doc_to = ?';
      params.push(String(docToParam).trim());
    }

    // ตัวกรองช่วงวันที่
    if (from) {
      whereClause += ' AND d.r_date >= ?';
      params.push(`${from} 00:00:00`);
    }
    if (to) {
      whereClause += ' AND d.r_date <= ?';
      params.push(`${to} 23:59:59`);
    }

    // นับจำนวนทั้งหมด
    const [countRows] = await pool.query<RowDataPacket[]>(
      `SELECT COUNT(*) as total FROM docz d WHERE ${whereClause}`,
      params
    );
    const total = Number(countRows[0].total || 0);

    // ดึงข้อมูลตามหน้า พร้อมสถานะการเปิดอ่านของผู้ใช้ปัจจุบัน (เฉพาะหน้าหนังสือของฉัน)
    const isMine = scope === 'mine';
    const readStatusSelect = isMine
      ? `(SELECT dur.\`check\` FROM doc_user_read dur WHERE dur.doc_id = d.id AND dur.user_id = ? LIMIT 1) as my_read_status,
         (SELECT dur.updated FROM doc_user_read dur WHERE dur.doc_id = d.id AND dur.user_id = ? LIMIT 1) as my_read_at,
         EXISTS(SELECT 1 FROM doc_user_pin pin WHERE pin.doc_id = d.id AND pin.user_id = ?) as is_pinned,`
      : `NULL as my_read_status, NULL as my_read_at, 0 as is_pinned,`;

    const selectParams = isMine ? [userId, userId, userId, ...params, pageSize, offset] : [...params, pageSize, offset];

    const [docs] = await pool.query<RowDataPacket[]>(
      `SELECT d.id, d.uid, d.r_number, d.r_date, d.doc_form_number, d.doc_date, d.doc_to, d.doc_form, d.doc_speed, d.name, d.st, d.file,
              ${readStatusSelect}
              CASE WHEN d.st = 5 THEN
                (SELECT dm.detail FROM doc_manage dm WHERE dm.doc_id = d.id AND dm.detail LIKE '[ตีกลับ]%' ORDER BY dm.id DESC LIMIT 1)
              ELSE NULL END as returnReason,
              CASE WHEN d.st = 5 THEN
                (SELECT dm.updated FROM doc_manage dm WHERE dm.doc_id = d.id AND dm.detail LIKE '[ตีกลับ]%' ORDER BY dm.id DESC LIMIT 1)
              ELSE NULL END as returnedAt,
              CASE WHEN d.st = 5 THEN
                (SELECT CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, ''))
                 FROM doc_manage dm
                 LEFT JOIN user_profile p ON p.user_id = dm.user_id
                 WHERE dm.doc_id = d.id AND dm.detail LIKE '[ตีกลับ]%' ORDER BY dm.id DESC LIMIT 1)
              ELSE NULL END as returnerName,
              CASE WHEN d.st = 5 THEN
                (SELECT rn.name
                 FROM doc_manage dm
                 LEFT JOIN role_name rn ON rn.id = dm.role_name_id
                 WHERE dm.doc_id = d.id AND dm.detail LIKE '[ตีกลับ]%' ORDER BY dm.id DESC LIMIT 1)
              ELSE NULL END as returnerRole
       FROM docz d
       WHERE ${whereClause}
       ORDER BY ${isMine ? 'is_pinned DESC, ' : ''}d.id DESC
       LIMIT ? OFFSET ?`,
      selectParams
    );

    const formattedDocs = docs.map((d) => ({
      id: d.id,
      uid: d.uid || '',
      receiveNumber: d.r_number || `ID-${d.id}`,
      receivedAt: formatSqlDateTime(d.r_date),
      letterNumber: d.doc_form_number || null,
      letterDate: formatSqlDate(d.doc_date),
      to: d.doc_to || '',
      docForm: d.doc_form || '',
      priority: d.doc_speed || null,
      subject: d.name || 'ไม่มีชื่อเรื่อง',
      legacyStatus: d.st !== null ? Number(d.st) : null,
      hasMainFile: Boolean(d.file),
      isRead: d.my_read_status !== null && d.my_read_status !== undefined ? Boolean(d.my_read_status) : undefined,
      readAt: d.my_read_at ? formatSqlDateTime(d.my_read_at) : null,
      isPinned: Boolean(d.is_pinned),
      isReturned: Boolean(d.st === 5 || d.returnReason),
      returnReason: d.returnReason ? String(d.returnReason).replace(/^\[ตีกลับ\]\s*/, '') : null,
      returnerName: d.returnerName?.trim() || null,
      returnerRole: d.returnerRole || null,
      returnedAt: d.returnedAt ? formatSqlDateTime(d.returnedAt) : null,
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
    console.error('[Document Index Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการโหลดรายการหนังสือ' });
  }
});

// Personal pins are scoped to the authenticated recipient, including for administrators.
documentRouter.post('/documents/:id/pin', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (typeof req.body?.pinned !== 'boolean') {
    res.status(400).json({ message: 'กรุณาระบุสถานะการปักหมุด' });
    return;
  }
  try {
    const doc = await resolveDocId(req.params.id);
    if (!doc) {
      res.status(404).json({ message: 'ไม่พบหนังสือ' });
      return;
    }
    const userId = req.user!.id;
    const [recipients] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM doc_user_read WHERE doc_id = ? AND user_id = ? LIMIT 1', [doc.id, userId]
    );
    if (!recipients.length) {
      res.status(403).json({ message: 'ปักหมุดได้เฉพาะหนังสือของฉันเท่านั้น' });
      return;
    }
    await ensureDocumentPins();
    if (req.body.pinned) {
      await pool.query('INSERT IGNORE INTO doc_user_pin (user_id, doc_id) VALUES (?, ?)', [userId, doc.id]);
    } else {
      await pool.query('DELETE FROM doc_user_pin WHERE user_id = ? AND doc_id = ?', [userId, doc.id]);
    }
    res.json({ data: { id: doc.id, isPinned: req.body.pinned } });
  } catch (error) {
    console.error('[Document Pin Error]', error);
    res.status(500).json({ message: 'ไม่สามารถบันทึกการปักหมุดได้' });
  }
});

// 1.1 ดึงจำนวนหนังสือของฉันที่ยังไม่ได้เปิดอ่าน (สำหรับแสดง Badge ใน Sidebar)
documentRouter.get('/documents/unread-count', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const userId = req.user!.id;
    const [rows] = await pool.query<RowDataPacket[]>(
      `SELECT COUNT(DISTINCT dur.doc_id) as unreadCount
       FROM doc_user_read dur
       INNER JOIN docz d ON d.id = dur.doc_id
       WHERE dur.user_id = ? AND dur.\`check\` = 0`,
      [userId]
    );

    const unreadCount = Number(rows[0]?.unreadCount || 0);
    res.json({ data: { unreadCount } });
  } catch (err) {
    console.error('[Unread Count Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงจำนวนเอกสารที่ยังไม่ได้อ่าน' });
  }
});

// 2. ดูรายละเอียดหนังสือ
documentRouter.get('/documents/:id', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบหนังสือ' });
    return;
  }
  const docId = resolved.id;

  try {
    const [rows] = await pool.query<RowDataPacket[]>(
      'SELECT id, uid, r_number, r_date, doc_form_number, doc_date, doc_to, doc_form, doc_speed, name, st, file FROM docz WHERE id = ? LIMIT 1',
      [docId]
    );

    if (!rows.length) {
      res.status(404).json({ message: 'ไม่พบหนังสือ' });
      return;
    }

    const d = rows[0];

    // 0. อัปเดตสถานะการอ่านของผู้ใช้ปัจจุบัน (ถ้ามีรายการใน doc_user_read และยังไม่อ่าน check = 0)
    const clientIp = (req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() || req.ip || req.socket.remoteAddress || '';
    await pool.query(
      'UPDATE doc_user_read SET `check` = 1, updated = NOW(), ip = ? WHERE doc_id = ? AND user_id = ? AND `check` = 0',
      [clientIp, docId, req.user!.id]
    );

    // 1. ดึงไฟล์แนบ
    const [files] = await pool.query<RowDataPacket[]>(
      'SELECT id, uid, name, file, ext, created FROM doc_file WHERE docz_id = ? ORDER BY id ASC',
      [docId]
    );

    const attachments = files.map((f) => ({
      id: f.id,
      uid: f.uid || '',
      name: f.name || `ไฟล์แนบ ${f.id}`,
      file: f.file,
      extension: f.ext || null,
      created: f.created ? String(f.created) : null,
    }));

    // 2. ดึงข้อมูลขั้นตอนตามโปรไฟล์การลงนาม (doc_profile)
    const formCode = d.doc_form || (d.doc_to === 'ผู้อำนวยการฯ' ? 'DocZpo' : 'DocZ');
    let profileName = formCode;

    const [profRows] = await pool.query<RowDataPacket[]>(
      'SELECT id, code, name FROM doc_profile WHERE code = ? OR id = ? LIMIT 1',
      [formCode, formCode]
    );

    if (profRows.length) {
      profileName = profRows[0].name || profRows[0].code;
    }

    // 3. ดึงขั้นตอนการลงนามจริงจาก doc_manage
    const [manageRows] = await pool.query<RowDataPacket[]>(`
      SELECT dm.id, dm.doc_id, dm.doc_form, dm.role_name_id, dm.user_id,
             dm.sort, dm.detail, dm.st, dm.updated, dm.created, dm.ty,
             rn.name as roleName,
             CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as signerName,
             u.username as signerUsername,
             p.dep_name as signerDepName,
             p.photo as signerPhoto,
             p.sign_photo as signerSignPhoto,
             r.name_dep1, r.name_dep2, r.name_dep3
      FROM doc_manage dm
      LEFT JOIN role_name rn ON rn.id = dm.role_name_id
      LEFT JOIN user u ON u.id = dm.user_id
      LEFT JOIN user_profile p ON p.user_id = dm.user_id
      LEFT JOIN role r ON (r.user_id = dm.user_id AND r.role_name_id = dm.role_name_id)
      WHERE dm.doc_id = ?
      ORDER BY dm.sort ASC, dm.id ASC
    `, [docId]);

    let signingSteps: any[] = [];
    if (manageRows.length) {
      signingSteps = manageRows.map((m) => {
        const isSigned = Number(m.st) === 3;
        const isReturned = m.detail && String(m.detail).startsWith('[ตีกลับ]');
        return {
          id: m.id,
          sort: m.sort,
          roleId: m.role_name_id,
          roleName: m.roleName || `บทบาท #${m.role_name_id}`,
          status: m.st !== null ? Number(m.st) : 1, // 1 = รอคิว, 2 = กำลังลงนาม, 3 = ลงนามแล้ว
          isReturned: Boolean(isReturned),
          returnReason: isReturned ? String(m.detail).replace(/^\[ตีกลับ\]\s*/, '') : null,
          signerId: (isSigned || isReturned) ? m.user_id : null,
          signerName: (isSigned || isReturned) ? (m.signerName?.trim() || null) : null,
          signerUsername: (isSigned || isReturned) ? (m.signerUsername || null) : null,
          signerDepName: (isSigned || isReturned) ? (m.signerDepName || null) : null,
          signerPhoto: (isSigned || isReturned) ? (m.signerPhoto || null) : null,
          signerSignPhoto: (isSigned || isReturned) ? (m.signerSignPhoto || null) : null,
          stamp: isSigned ? (m.ty || null) : null,
          detail: m.detail || null,
          position1: isSigned ? (m.name_dep1 || null) : null,
          position2: isSigned ? (m.name_dep2 || null) : null,
          position3: isSigned ? (m.name_dep3 || null) : null,
          signedAt: (isSigned || isReturned) && m.updated ? formatSqlDateTime(m.updated) : null,
          createdAt: formatSqlDateTime(m.created),
        };
      });
    } else if (profRows.length) {
      // เอกสารที่ยังไม่ได้เริ่มสายการลงนาม (st = 1) ให้แสดงเส้นทางตาม Template
      const [stepRows] = await pool.query<RowDataPacket[]>(
        `SELECT s.sort, rn.id as role_name_id, rn.name as roleName
         FROM doc_profile_sub s
         LEFT JOIN role_name rn ON rn.id = s.role_name_id
         WHERE s.doc_profile_id = ? OR s.doc_profile_id = ?
         ORDER BY s.sort ASC, s.id ASC`,
        [profRows[0].id, profRows[0].code]
      );
      signingSteps = stepRows.map((s) => ({
        id: 0,
        sort: s.sort,
        roleId: s.role_name_id,
        roleName: s.roleName || 'ผู้ลงนาม',
        status: 1,
        signerId: null,
        signerName: null,
        signerUsername: null,
        signerDepName: null,
        signerPhoto: null,
        signerSignPhoto: null,
        stamp: null,
        detail: null,
        position1: null,
        position2: null,
        position3: null,
        signedAt: null,
        createdAt: null,
      }));
    }

    // 4. ดึงรายชื่อผู้ที่อ่านเอกสารแล้ว และผู้รับเอกสารจาก doc_user_read
    const [readerRows] = await pool.query<RowDataPacket[]>(`
      SELECT dur.id, dur.user_id, dur.doc_id, dur.check as isRead,
             dur.updated as readAt, dur.created as assignedAt,
             dur.ip,
             u.username,
             CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as readerName,
             p.dep_name as depName,
             p.group_work as groupWork,
             p.photo
      FROM doc_user_read dur
      LEFT JOIN user u ON u.id = dur.user_id
      LEFT JOIN user_profile p ON p.user_id = dur.user_id
      WHERE dur.doc_id = ?
      ORDER BY dur.check DESC, dur.updated DESC, p.sort ASC, p.name ASC
    `, [docId]);

    const readers = readerRows.map((r) => ({
      id: r.id,
      userId: r.user_id,
      username: r.username,
      readerName: r.readerName?.trim() || r.username || `ผู้ใช้ #${r.user_id}`,
      depName: r.depName || '',
      groupWork: r.groupWork || '',
      photo: r.photo || null,
      isRead: Boolean(r.isRead),
      readAt: r.isRead && r.readAt ? formatSqlDateTime(r.readAt) : null,
      assignedAt: r.assignedAt ? formatSqlDateTime(r.assignedAt) : null,
      ip: r.ip || null,
    }));

    const readCount = readers.filter((r) => r.isRead).length;

    // ตรวจสอบว่ามีขั้นตอนที่ถูกตีกลับหรือไม่
    const returnedStep = manageRows.slice().reverse().find((m) => m.detail && String(m.detail).startsWith('[ตีกลับ]'));
    const returnInfo = returnedStep ? {
      stepId: returnedStep.id,
      returnerName: returnedStep.signerName?.trim() || returnedStep.signerUsername || 'ผู้ลงนาม',
      returnerRole: returnedStep.roleName || `บทบาท #${returnedStep.role_name_id}`,
      reason: String(returnedStep.detail).replace(/^\[ตีกลับ\]\s*/, ''),
      returnedAt: returnedStep.updated ? formatSqlDateTime(returnedStep.updated) : null,
    } : null;

    // ตรวจสอบข้อมูลการทำลายเอกสาร (กรณี st = 6)
    let destructionInfo: any = null;
    if (d.st === 6) {
      const [destRows] = await pool.query<RowDataPacket[]>(`
        SELECT i.reason, i.order_number, i.purge_files, i.file_hash_sha256, i.destroyed_at,
               b.batch_number, b.title as batch_title,
               CONCAT(COALESCE(p.pfname, ''), COALESCE(p.name, ''), ' ', COALESCE(p.sname, '')) as destroyed_by_name,
               u.username as destroyed_by_username
        FROM doc_destruction_item i
        LEFT JOIN doc_destruction_batch b ON b.id = i.batch_id
        LEFT JOIN user u ON u.id = i.destroyed_by
        LEFT JOIN user_profile p ON p.user_id = i.destroyed_by
        WHERE i.doc_id = ?
        ORDER BY i.id DESC
        LIMIT 1
      `, [docId]);

      if (destRows.length) {
        const di = destRows[0];
        destructionInfo = {
          reason: di.reason || 'ทำลายตามระเบียบงานสารบรรณ',
          orderNumber: di.order_number || '—',
          purgeFiles: Boolean(di.purge_files),
          fileHash: di.file_hash_sha256,
          batchNumber: di.batch_number || null,
          batchTitle: di.batch_title || null,
          destroyedAt: formatSqlDateTime(di.destroyed_at),
          destroyedByName: di.destroyed_by_name?.trim() || di.destroyed_by_username || 'เจ้าหน้าที่สารบรรณ',
        };
      }
    }

    res.json({
      data: {
        id: d.id,
        uid: d.uid || '',
        receiveNumber: d.r_number || `ID-${d.id}`,
        receivedAt: formatSqlDateTime(d.r_date),
        letterNumber: d.doc_form_number || null,
        letterDate: formatSqlDate(d.doc_date),
        to: d.doc_to || '',
        docForm: d.doc_form || '',
        profileName,
        signingSteps,
        readers,
        readCount,
        totalAssignedCount: readers.length,
        priority: d.doc_speed || null,
        subject: d.name || 'ไม่มีชื่อเรื่อง',
        legacyStatus: d.st !== null ? Number(d.st) : null,
        hasMainFile: Boolean(d.file),
        attachments,
        isReturned: Boolean(d.st === 5 || returnInfo),
        returnInfo,
        destructionInfo,
      },
    });
  } catch (err) {
    console.error('[Document Detail Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงข้อมูลหนังสือ' });
  }
});

// 3. ดาวน์โหลดไฟล์ (File Gateway ตรวจสิทธิ์ก่อนส่งไฟล์)
documentRouter.get('/documents/:id/download', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบหนังสือ' });
    return;
  }
  const docId = resolved.id;
  const attachmentId = req.query.attachmentId ? await resolveAttachmentId(String(req.query.attachmentId)) : null;

  try {
    let filePath = '';
    let fileName = '';

    if (attachmentId) {
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT name, file FROM doc_file WHERE id = ? AND docz_id = ? LIMIT 1',
        [attachmentId, docId]
      );
      if (!rows.length || !rows[0].file) {
        res.status(404).json({ message: 'ไม่พบไฟล์แนบ' });
        return;
      }
      filePath = rows[0].file;
      fileName = rows[0].name || path.basename(filePath);
    } else {
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT name, file FROM docz WHERE id = ? LIMIT 1',
        [docId]
      );
      if (!rows.length || !rows[0].file) {
        res.status(404).json({ message: 'ไม่พบหนังสือนำ' });
        return;
      }
      filePath = rows[0].file;
      fileName = `${rows[0].name || 'หนังสือนำ'}.pdf`;
    }

    // ค้นหาตำแหน่งไฟล์จริงในไดเรกทอรี web/
    const absolutePath = resolveWebFilePath(filePath);

    if (!absolutePath || !fs.existsSync(absolutePath)) {
      console.warn('[Download Error] File not found on disk:', filePath);
      res.status(404).json({ message: 'ไฟล์ไม่พบบนระบบจัดเก็บ' });
      return;
    }

    res.download(absolutePath, fileName);
  } catch (err) {
    console.error('[Download Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดาวน์โหลดไฟล์' });
  }
});

// 4. ดูไฟล์ PDF แบบ Inline สำหรับแสดงใน Iframe / PDF Viewer โดยเฉพาะ
documentRouter.get(['/documents/:id/view-file', '/documents/:id/file'], requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบหนังสือ' });
    return;
  }
  const docId = resolved.id;
  const attachmentId = req.query.attachmentId ? await resolveAttachmentId(String(req.query.attachmentId)) : null;

  try {
    let filePath = '';

    if (attachmentId) {
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT file FROM doc_file WHERE id = ? AND docz_id = ? LIMIT 1',
        [attachmentId, docId]
      );
      if (!rows.length || !rows[0].file) {
        res.status(404).json({ message: 'ไม่พบไฟล์แนบ' });
        return;
      }
      filePath = rows[0].file;
    } else {
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT file FROM docz WHERE id = ? LIMIT 1',
        [docId]
      );
      if (!rows.length || !rows[0].file) {
        res.status(404).json({ message: 'ไม่พบหนังสือนำ' });
        return;
      }
      filePath = rows[0].file;
    }

    const absolutePath = resolveWebFilePath(filePath);

    if (!absolutePath || !fs.existsSync(absolutePath)) {
      console.warn('[ViewFile Error] File not found on disk:', filePath);
      res.status(404).json({ message: 'ไฟล์ไม่พบบนระบบจัดเก็บ' });
      return;
    }

    const ext = path.extname(absolutePath).toLowerCase();
    if (ext === '.pdf') {
      res.setHeader('Content-Type', 'application/pdf');
    } else if (['.jpg', '.jpeg'].includes(ext)) {
      res.setHeader('Content-Type', 'image/jpeg');
    } else if (ext === '.png') {
      res.setHeader('Content-Type', 'image/png');
    } else {
      res.setHeader('Content-Type', 'application/octet-stream');
    }

    res.setHeader('Content-Disposition', `inline; filename="${encodeURIComponent(path.basename(absolutePath))}"`);
    res.sendFile(absolutePath);
  } catch (err) {
    console.error('[View File Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเปิดไฟล์' });
  }
});

// 5. แก้ไข / เปลี่ยนไฟล์หนังสือนำ (ไฟล์หลัก)
documentRouter.post(
  '/documents/:id/update-main-file',
  requireAuth,
  upload.single('file'),
  async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    const resolved = await resolveDocId(req.params.id);
    if (!resolved) {
      if (req.file) fs.unlinkSync(req.file.path);
      res.status(404).json({ message: 'ไม่พบหนังสือที่ต้องการแก้ไข' });
      return;
    }
    const docId = resolved.id;

    // OWASP A01: Access Control - ตรวจสอบสิทธิ์สารบรรณ/แอดมิน หรือผู้ลงนามในขั้นตอนปัจจุบัน
    const isOfficerOrAdmin = Boolean(req.user?.registryOfficer || req.user?.isAdmin);
    if (!isOfficerOrAdmin) {
      const [activeStepRows] = await pool.query<RowDataPacket[]>(
        `SELECT dm.id FROM doc_manage dm
         INNER JOIN role_power rp ON rp.role_name_id = dm.role_name_id AND rp.user_id = ?
         WHERE dm.doc_id = ? AND dm.st = 2 LIMIT 1`,
        [req.user!.id, docId]
      );
      if (!activeStepRows.length) {
        if (req.file) fs.unlinkSync(req.file.path);
        res.status(403).json({ message: 'คุณไม่มีสิทธิ์แก้ไขหรือเปลี่ยนไฟล์หนังสือนำของเอกสารนี้' });
        return;
      }
    }

    if (!req.file) {
      res.status(400).json({ message: 'กรุณาเลือกไฟล์ PDF หนังสือนำฉบับใหม่' });
      return;
    }

    const ext = path.extname(req.file.originalname).toLowerCase();
    if (ext !== '.pdf') {
      fs.unlinkSync(req.file.path);
      res.status(400).json({ message: 'หนังสือนำต้องเป็นไฟล์ PDF เท่านั้น' });
      return;
    }

    const pdfCheck = validatePdfA4Portrait(req.file.path);
    if (!pdfCheck.valid) {
      fs.unlinkSync(req.file.path);
      res.status(400).json({
        message: pdfCheck.message,
        details: pdfCheck.pages,
      });
      return;
    }

    try {
      // 1. ดึงไฟล์เดิม
      const [oldRows] = await pool.query<RowDataPacket[]>(
        'SELECT file FROM docz WHERE id = ? LIMIT 1',
        [docId]
      );

      if (!oldRows.length) {
        fs.unlinkSync(req.file.path);
        res.status(404).json({ message: 'ไม่พบหนังสือที่ต้องการแก้ไข' });
        return;
      }

      const relativeStoragePath = `uploads/docz/${req.file.filename}`;

      // 2. อัปเดตตาราง docz
      await pool.query(
        'UPDATE docz SET file = ? WHERE id = ?',
        [relativeStoragePath, docId]
      );

      // 3. ลบไฟล์เดิมออกจากดิสก์ (ถ้ามี)
      if (oldRows[0].file) {
        const oldFileAbs = path.resolve(__dirname, '../../../web', oldRows[0].file);
        if (fs.existsSync(oldFileAbs) && oldFileAbs !== path.resolve(req.file.path)) {
          try {
            fs.unlinkSync(oldFileAbs);
          } catch {
            // ละเว้นหากลบไม่ได้
          }
        }
      }

      res.json({
        data: {
          docId,
          uid: resolved.uid,
          newFilePath: relativeStoragePath,
        },
        message: 'อัปเดตและเปลี่ยนไฟล์หนังสือนำเรียบร้อยแล้ว',
      });
    } catch (err) {
      console.error('[Update Main File Error]', err);
      res.status(500).json({ message: 'เกิดข้อผิดพลาดในการอัปเดตไฟล์หนังสือนำ' });
    }
  }
);

// 6. เสนอหนังสือ (Send / Submit to Signing Workflow)
documentRouter.post('/documents/:id/send', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้นที่สามารถเสนอหนังสือได้' });
    return;
  }

  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบเอกสาร' });
    return;
  }
  const docId = resolved.id;
  const { profileCode } = req.body;

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const [docRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, doc_form, doc_to, st, file, name, r_number, r_date, start FROM docz WHERE id = ? FOR UPDATE',
      [docId]
    );

    if (!docRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบเอกสาร' });
      return;
    }

    const doc = docRows[0];

    // ประทับตราเลขลงรับ (Watermark Stamp) บนหัวหนังสือด้านขวา หากมีไฟล์หนังสือนำ (PDF)
    if (doc.file && doc.file.toLowerCase().endsWith('.pdf')) {
      try {
        const courtName = process.env.COURT_NAME || 'ศาลเยาวชนและครอบครัวจังหวัดประประจวบคีรีขันธ์';
        const stampRes = await stampReceivePdf(
          doc.file,
          courtName,
          doc.r_number || '',
          formatSqlDateTime(doc.r_date) || (doc.r_date ? String(doc.r_date) : '')
        );
        if (!stampRes.success) {
          console.warn('[Stamp Receive Warning]', stampRes.error);
        }
      } catch (stampErr) {
        console.error('[Stamp Error during Send]', stampErr);
      }
    }
    const formCode = profileCode || doc.doc_form || (doc.doc_to === 'ผู้อำนวยการฯ' ? 'DocZpo' : 'DocZ');

    // ดึงขั้นตอนผู้ลงนามจาก doc_profile
    const [profRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, code FROM doc_profile WHERE code = ? OR id = ? LIMIT 1',
      [formCode, formCode]
    );

    let stepsToInsert: { role_name_id: number; sort: number }[] = [];
    if (profRows.length) {
      const profId = profRows[0].id;
      const profCode = profRows[0].code;
      const [subRows] = await connection.query<RowDataPacket[]>(
        'SELECT role_name_id, sort FROM doc_profile_sub WHERE doc_profile_id = ? OR doc_profile_id = ? ORDER BY sort ASC, id ASC',
        [profId, profCode]
      );
      stepsToInsert = subRows.map((r) => ({ role_name_id: r.role_name_id, sort: r.sort }));
    }

    // ล้างขั้นตอนเดิมออกก่อน (ตามการทำงานของ actionSend เดิม)
    await connection.query('DELETE FROM doc_manage WHERE doc_id = ?', [docId]);

    // สร้างขั้นตอนใหม่โดยให้ขั้นตอนที่ 1 เป็น st = 2 (รอลงนาม)
    for (const step of stepsToInsert) {
      const initialSt = step.sort === 1 ? 2 : 1;
      await connection.query(
        `INSERT INTO doc_manage (doc_form, doc_id, user_id, role_name_id, sort, created, st)
         VALUES (?, ?, ?, ?, ?, NOW(), ?)`,
        [formCode, docId, req.user!.id, step.role_name_id, step.sort, initialSt]
      );
    }

    // อัปเดตสถานะหนังสือ docz เป็น st = 2 (อยู่ระหว่างดำเนินการ)
    await connection.query(
      `UPDATE docz SET st = 2, doc_form = ?, start = NOW() WHERE id = ?`,
      [formCode, docId]
    );

    await connection.commit();

    res.json({
      data: {
        docId,
        legacyStatus: 2,
        message: `เสนอหนังสือเลขรับ ${doc.r_number} เรียบร้อยแล้ว (ส่งต่อไปยังคิวลงนามขั้นที่ 1)`,
      },
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Send Document Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเสนอหนังสือ' });
  } finally {
    connection.release();
  }
});

// 7. แก้ไขข้อมูลหนังสือและจัดการไฟล์แนบ (Update Document & Attachments)
documentRouter.post(
  '/documents/:id/update',
  requireAuth,
  upload.fields([
    { name: 'file', maxCount: 1 },
    { name: 'attachments', maxCount: 20 },
  ]),
  async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    if (!req.user?.registryOfficer && !req.user?.isAdmin) {
      res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้นที่สามารถแก้ไขข้อมูลหนังสือได้' });
      return;
    }

    const resolved = await resolveDocId(req.params.id);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบหนังสือที่ต้องการแก้ไข' });
      return;
    }
    const docId = resolved.id;
    const {
      receiveNumber,
      receivedAt,
      priority,
      letterNumber,
      letterDate,
      docTo,
      profileCode,
      subject,
      deletedAttachmentIds,
      updatedAttachmentNames,
    } = req.body;

    if (!subject) {
      res.status(400).json({ message: 'กรุณากรอกเรื่องของหนังสือ' });
      return;
    }

    const filesMap = req.files as { [fieldname: string]: Express.Multer.File[] } | undefined;
    const mainFile = filesMap?.['file']?.[0];
    const newAttachments = filesMap?.['attachments'] || [];

    // OWASP A08: ตรวจสอบความปลอดภัยของไฟล์แนบใหม่ทั้งหมด
    for (const att of newAttachments) {
      const val = validateFileExtension(att.originalname, 'ATTACHMENT');
      if (!val.valid) {
        if (fs.existsSync(att.path)) fs.unlinkSync(att.path);
        res.status(400).json({ message: val.error || `ไฟล์แนบ ${att.originalname} ไม่ได้รับอนุญาต` });
        return;
      }
    }

    const connection = await pool.getConnection();
    try {
      await connection.beginTransaction();

      const [oldRows] = await connection.query<RowDataPacket[]>(
        'SELECT id, doc_form, file FROM docz WHERE id = ? FOR UPDATE',
        [docId]
      );

      if (!oldRows.length) {
        await connection.rollback();
        res.status(404).json({ message: 'ไม่พบหนังสือที่ต้องการแก้ไข' });
        return;
      }

      let filePath = oldRows[0].file;
      if (mainFile) {
        filePath = `uploads/docz/${mainFile.filename}`;
      }

      const formCode = profileCode || oldRows[0].doc_form;

      // 1. อัปเดตข้อมูลหนังสือ docz
      await connection.query(
        `UPDATE docz
         SET r_number = COALESCE(?, r_number),
             r_date = COALESCE(?, r_date),
             doc_speed = ?,
             doc_form_number = ?,
             doc_date = ?,
             doc_to = ?,
             doc_form = ?,
             name = ?,
             file = ?
         WHERE id = ?`,
        [
          receiveNumber || null,
          receivedAt ? receivedAt.replace('T', ' ') : null,
          priority || null,
          letterNumber || null,
          letterDate ? `${letterDate} 00:00:00` : null,
          docTo || null,
          formCode,
          subject.trim(),
          filePath,
          docId,
        ]
      );

      // 2. จัดการลบไฟล์แนบเดิมที่ระบุ (deletedAttachmentIds)
      if (deletedAttachmentIds) {
        let toDeleteIds: number[] = [];
        try {
          toDeleteIds = JSON.parse(deletedAttachmentIds);
        } catch {
          if (Array.isArray(deletedAttachmentIds)) toDeleteIds = deletedAttachmentIds.map(Number);
        }
        const validIds = toDeleteIds.filter((n) => !isNaN(n) && n > 0);
        if (validIds.length > 0) {
          const [delFiles] = await connection.query<RowDataPacket[]>(
            `SELECT file FROM doc_file WHERE id IN (?) AND docz_id = ?`,
            [validIds, docId]
          );
          await connection.query(
            `DELETE FROM doc_file WHERE id IN (?) AND docz_id = ?`,
            [validIds, docId]
          );
          for (const item of delFiles) {
            if (item.file) {
              const absPath = path.resolve(__dirname, '../../../web', item.file);
              if (fs.existsSync(absPath)) {
                try { fs.unlinkSync(absPath); } catch {}
              }
            }
          }
        }
      }

      // 3. จัดการแก้ไขชื่อไฟล์แนบเดิม (updatedAttachmentNames)
      if (updatedAttachmentNames) {
        let renameMap: Record<string, string> = {};
        try {
          renameMap = JSON.parse(updatedAttachmentNames);
        } catch {}
        for (const [idStr, newName] of Object.entries(renameMap)) {
          const attId = Number(idStr);
          if (attId > 0 && typeof newName === 'string' && newName.trim()) {
            await connection.query(
              `UPDATE doc_file SET name = ? WHERE id = ? AND docz_id = ?`,
              [newName.trim(), attId, docId]
            );
          }
        }
      }

      // 4. บันทึกไฟล์แนบใหม่เพิ่มเติม (newAttachments)
      if (newAttachments.length > 0) {
        for (const att of newAttachments) {
          const ext = path.extname(att.originalname).replace('.', '').toLowerCase();
          let displayName = att.originalname;
          try {
            const decoded = Buffer.from(att.originalname, 'latin1').toString('utf8');
            if (/[\u0E00-\u0E7F]/.test(decoded)) {
              displayName = decoded;
            }
          } catch {}

          const attUid = generateUid();
          await connection.query(
            `INSERT INTO doc_file (uid, docz_id, doc_form, name, file, ext, user_id_create, created)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())`,
            [
              attUid,
              docId,
              formCode,
              displayName,
              `uploads/docz/${att.filename}`,
              ext || null,
              req.user!.id,
            ]
          );
        }
      }

      // 5. ถ้ามีการเปลี่ยน profileCode และยังไม่มีใครลงนาม ให้ปรับสายการลงนามใหม่
      if (profileCode && profileCode !== oldRows[0].doc_form) {
        const [signedCount] = await connection.query<RowDataPacket[]>(
          'SELECT COUNT(*) as cnt FROM doc_manage WHERE doc_id = ? AND st = 3',
          [docId]
        );
        if (Number(signedCount[0].cnt) === 0) {
          await connection.query('DELETE FROM doc_manage WHERE doc_id = ?', [docId]);
          const [profRows] = await connection.query<RowDataPacket[]>(
            'SELECT id, code FROM doc_profile WHERE code = ? OR id = ? LIMIT 1',
            [profileCode, profileCode]
          );
          if (profRows.length) {
            const profId = profRows[0].id;
            const profCode = profRows[0].code;
            const [subRows] = await connection.query<RowDataPacket[]>(
              'SELECT role_name_id, sort FROM doc_profile_sub WHERE doc_profile_id = ? OR doc_profile_id = ? ORDER BY sort ASC, id ASC',
              [profId, profCode]
            );
            for (const s of subRows) {
              await connection.query(
                `INSERT INTO doc_manage (doc_form, doc_id, user_id, role_name_id, sort, created, st)
                 VALUES (?, ?, ?, ?, ?, NOW(), ?)`,
                [profileCode, docId, req.user!.id, s.role_name_id, s.sort, s.sort === 1 ? 2 : 1]
              );
            }
          }
        }
      }

      await connection.commit();

      res.json({
        data: {
          docId,
          receiveNumber,
          subject,
        },
        message: 'บันทึกการแก้ไขหนังสือและไฟล์แนบเรียบร้อยแล้ว',
      });
    } catch (err) {
      await connection.rollback();
      console.error('[Update Document Error]', err);
      res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขหนังสือ' });
    } finally {
      connection.release();
    }
  }
);

// 8. ลบไฟล์แนบเดี่ยว (Direct Delete Attachment)
documentRouter.delete(
  '/documents/:id/attachments/:attachmentId',
  requireAuth,
  async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    if (!req.user?.registryOfficer && !req.user?.isAdmin) {
      res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้นที่สามารถลบไฟล์แนบได้' });
      return;
    }

    const resolved = await resolveDocId(req.params.id);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบหนังสือ' });
      return;
    }
    const docId = resolved.id;
    const attachmentId = await resolveAttachmentId(req.params.attachmentId);
    if (!attachmentId) {
      res.status(404).json({ message: 'ไม่พบไฟล์แนบที่ต้องการลบ' });
      return;
    }

    try {
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT file FROM doc_file WHERE id = ? AND docz_id = ? LIMIT 1',
        [attachmentId, docId]
      );

      if (!rows.length) {
        res.status(404).json({ message: 'ไม่พบไฟล์แนบที่ต้องการลบ' });
        return;
      }

      await pool.query('DELETE FROM doc_file WHERE id = ? AND docz_id = ?', [attachmentId, docId]);

      if (rows[0].file) {
        const absPath = path.resolve(__dirname, '../../../web', rows[0].file);
        if (fs.existsSync(absPath)) {
          try { fs.unlinkSync(absPath); } catch {}
        }
      }

      res.json({ success: true, message: 'ลบไฟล์แนบเรียบร้อยแล้ว' });
    } catch (err) {
      console.error('[Delete Attachment Error]', err);
      res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลบไฟล์แนบ' });
    }
  }
);

// 9. แก้ไขชื่อไฟล์แนบเดี่ยว (Direct Rename Attachment)
documentRouter.patch(
  '/documents/:id/attachments/:attachmentId',
  requireAuth,
  async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    if (!req.user?.registryOfficer && !req.user?.isAdmin) {
      res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้นที่สามารถแก้ไขชื่อไฟล์แนบได้' });
      return;
    }

    const resolved = await resolveDocId(req.params.id);
    if (!resolved) {
      res.status(404).json({ message: 'ไม่พบหนังสือ' });
      return;
    }
    const docId = resolved.id;
    const attachmentId = await resolveAttachmentId(req.params.attachmentId);
    if (!attachmentId) {
      res.status(404).json({ message: 'ไม่พบไฟล์แนบที่ต้องการแก้ไข' });
      return;
    }
    const { name } = req.body;

    if (!name || typeof name !== 'string' || !name.trim()) {
      res.status(400).json({ message: 'กรุณาระบุชื่อไฟล์แนบ' });
      return;
    }

    try {
      const [result]: any = await pool.query(
        'UPDATE doc_file SET name = ? WHERE id = ? AND docz_id = ?',
        [name.trim(), attachmentId, docId]
      );

      if (result.affectedRows === 0) {
        res.status(404).json({ message: 'ไม่พบไฟล์แนบที่ต้องการแก้ไข' });
        return;
      }

      res.json({ success: true, message: 'แก้ไขชื่อไฟล์แนบเรียบร้อยแล้ว' });
    } catch (err) {
      console.error('[Rename Attachment Error]', err);
      res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขชื่อไฟล์แนบ' });
    }
  }
);

// 10. ดึงข้อมูลสำหรับหน้าต่างจ่ายหนังสือ (Distribute Options)
documentRouter.get('/documents/:id/distribute-options', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้นที่สามารถดูตัวเลือกจ่ายหนังสือได้' });
    return;
  }

  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบหนังสือ' });
    return;
  }
  const docId = resolved.id;

  try {
    // 1. ตรวจสอบเอกสาร
    const [docRows] = await pool.query<RowDataPacket[]>(
      'SELECT id, uid, r_number, name, st, file FROM docz WHERE id = ? LIMIT 1',
      [docId]
    );

    if (!docRows.length) {
      res.status(404).json({ message: 'ไม่พบหนังสือ' });
      return;
    }

    // 2. ดึงรายชื่อแฟ้มจัดเก็บเอกสาร (doc_cat_name)
    const [categories] = await pool.query<RowDataPacket[]>(
      'SELECT id, uid, name FROM doc_cat_name ORDER BY id ASC'
    );

    // 3. ดึงแฟ้มเดิมที่หนังสือนี้เคยถูกจัดเก็บไว้ (ถ้ามี)
    const [selectedCats] = await pool.query<RowDataPacket[]>(
      'SELECT doc_cat_name_id FROM doc_cat WHERE doc_id = ?',
      [docId]
    );
    const selectedCategoryIds = selectedCats.map((c) => Number(c.doc_cat_name_id));

    // 4. ดึงรายชื่อผู้ใช้งานทั้งหมดที่ Active (user.status = 10)
    const [users] = await pool.query<RowDataPacket[]>(`
      SELECT u.id, u.uid, u.username, up.name, up.sname, up.group_work, up.dep_name
      FROM user u
      LEFT JOIN user_profile up ON up.user_id = u.id
      WHERE u.status = 10
      ORDER BY up.sort ASC, u.id ASC
    `);

    // 5. ดึงรายชื่อผู้ใช้งานที่เคยถูกจ่ายหนังสือนี้ให้แล้ว (doc_user_read)
    const [assignedUsers] = await pool.query<RowDataPacket[]>(
      'SELECT user_id FROM doc_user_read WHERE doc_id = ?',
      [docId]
    );
    const assignedUserIds = assignedUsers.map((u) => Number(u.user_id));

    // จัดกลุ่มผู้ใช้งานตาม group_work
    const groupsMap = new Map<string, Array<{ id: number; uid: string; username: string; fullName: string; depName: string }>>();
    for (const u of users) {
      const groupName = u.group_work ? String(u.group_work).trim() : 'อื่นๆ';
      const fullName = [u.name, u.sname].filter(Boolean).join(' ') || u.username;
      if (!groupsMap.has(groupName)) {
        groupsMap.set(groupName, []);
      }
      groupsMap.get(groupName)!.push({
        id: u.id,
        uid: u.uid || '',
        username: u.username,
        fullName,
        depName: u.dep_name || '',
      });
    }

    const groups = Array.from(groupsMap.entries()).map(([groupName, groupUsers]) => ({
      name: groupName,
      users: groupUsers,
    }));

    // 5.1 ดึงไฟล์แนบของหนังสือ (doc_file)
    const [attachments] = await pool.query<RowDataPacket[]>(
      'SELECT id, uid, name, file FROM doc_file WHERE docz_id = ? ORDER BY id ASC',
      [docId]
    );

    const formattedAttachments = attachments.map((a) => {
      const originalName = a.name || (a.file ? path.basename(String(a.file)) : 'ไฟล์แนบ');
      return {
        id: a.id,
        uid: a.uid || '',
        name: originalName,
      };
    });

    res.json({
      data: {
        document: {
          id: docRows[0].id,
          uid: docRows[0].uid || '',
          receiveNumber: docRows[0].r_number,
          subject: docRows[0].name,
          legacyStatus: docRows[0].st,
          hasMainFile: Boolean(docRows[0].file),
          attachments: formattedAttachments,
        },
        categories,
        selectedCategoryIds,
        assignedUserIds,
        groups,
      },
    });
  } catch (err) {
    console.error('[Distribute Options Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการโหลดข้อมูลจ่ายหนังสือ' });
  }
});

// 11. บันทึกการจ่ายหนังสือและจัดเก็บเข้าแฟ้ม (Submit Document Distribution)
documentRouter.post('/documents/:id/distribute', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณหรือผู้ดูแลระบบเท่านั้นที่สามารถจ่ายหนังสือได้' });
    return;
  }

  const resolved = await resolveDocId(req.params.id);
  if (!resolved) {
    res.status(404).json({ message: 'ไม่พบหนังสือ' });
    return;
  }
  const docId = resolved.id;
  const { userIds, categoryIds, lineNotifyAll } = req.body;

  const targetUserIds: number[] = Array.isArray(userIds) ? userIds.map(Number).filter((n) => !isNaN(n) && n > 0) : [];
  const targetCategoryIds: number[] = Array.isArray(categoryIds) ? categoryIds.map(Number).filter((n) => !isNaN(n) && n > 0) : [];

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const [docRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, r_number, name, st FROM docz WHERE id = ? FOR UPDATE',
      [docId]
    );

    if (!docRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบหนังสือ' });
      return;
    }

    // 1. บันทึกผู้รับหนังสือลงตาราง doc_user_read
    // ลบสิทธิ์อ่านเดิมก่อน (หรือ sync)
    await connection.query('DELETE FROM doc_user_read WHERE doc_id = ?', [docId]);
    for (const uid of targetUserIds) {
      await connection.query(
        `INSERT INTO doc_user_read (user_id, doc_id, \`check\`, created)
         VALUES (?, ?, 0, NOW())`,
        [uid, docId]
      );
    }

    // 2. บันทึกหมวดแฟ้มจัดเก็บเอกสารลงตาราง doc_cat
    await connection.query('DELETE FROM doc_cat WHERE doc_id = ?', [docId]);
    for (const catId of targetCategoryIds) {
      await connection.query(
        `INSERT INTO doc_cat (doc_id, doc_cat_name_id)
         VALUES (?, ?)`,
        [docId, catId]
      );
    }

    // 3. ปรับสถานะหนังสือ docz เป็น st = 4 (จ่ายแล้ว)
    await connection.query('UPDATE docz SET st = 4 WHERE id = ?', [docId]);

    await connection.commit();

    res.json({
      data: {
        docId,
        legacyStatus: 4,
        recipientCount: targetUserIds.length,
        categoryCount: targetCategoryIds.length,
      },
      message: `จ่ายหนังสือเลขรับ ${docRows[0].r_number} เรียบร้อยแล้ว (แจกจ่ายถึง ${targetUserIds.length} ท่าน)`,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Distribute Submit Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการบันทึกจ่ายหนังสือ' });
  } finally {
    connection.release();
  }
});
