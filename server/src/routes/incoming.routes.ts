import { Router, Response } from 'express';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import crypto from 'crypto';
import { pool } from '../db/connection';
import { AuthenticatedRequest, requireAuth } from '../middlewares/auth';
import { RowDataPacket, ResultSetHeader } from 'mysql2';
import { generateUid } from '../utils/uid';
import { validateFileExtension, DANGEROUS_EXTENSIONS } from '../utils/fileValidation';
import { validatePdfA4Portrait } from '../utils/pdfValidation';

export const incomingRouter = Router();

// สร้างโฟลเดอร์สำหรับเก็บไฟล์อัปโหลดชั่วคราว
const uploadDir = path.resolve(__dirname, '../../../runtime/private-documents/imports');
if (!fs.existsSync(uploadDir)) {
  fs.mkdirSync(uploadDir, { recursive: true });
}

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => {
    cb(null, uploadDir);
  },
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    const token = crypto.randomBytes(24).toString('hex');
    cb(null, `${token}${ext}`);
  },
});

const upload = multer({
  storage,
  limits: { fileSize: 20 * 1024 * 1024 }, // 20 MB ต่อไฟล์
  fileFilter: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    if (DANGEROUS_EXTENSIONS.includes(ext)) {
      return cb(new Error(`ไม่อนุญาตให้อัปโหลดไฟล์อันตราย (${ext})`));
    }
    cb(null, true);
  },
});

async function getNextDailyNumber(connectionOrPool: any, dateObj: Date = new Date()): Promise<string> {
  const pad = (n: number) => String(n).padStart(2, '0');
  const yyyy = dateObj.getFullYear();
  const mm = pad(dateObj.getMonth() + 1);
  const dd = pad(dateObj.getDate());
  const dateStr = `${yyyy}${mm}${dd}`; // e.g. "20261102"

  const [rows] = await connectionOrPool.query(
    'SELECT r_number FROM docz WHERE r_number LIKE ? ORDER BY id DESC LIMIT 100',
    [`%${dateStr}-%`]
  );

  let maxSeq = 0;
  const regex = new RegExp(`${dateStr}-(\\d+)`);
  for (const r of rows as RowDataPacket[]) {
    const match = String(r.r_number || '').match(regex);
    if (match && match[1]) {
      const seq = parseInt(match[1], 10);
      if (!isNaN(seq) && seq > maxSeq) {
        maxSeq = seq;
      }
    }
  }

  const nextSeq = maxSeq + 1;
  return `${dateStr}-${String(nextSeq).padStart(3, '0')}`;
}

// 1. ตัวเลือกฟอร์มนำเข้าหนังสือ
incomingRouter.get('/incoming/options', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณที่ได้รับสิทธิ์นำเข้าหนังสือ' });
    return;
  }

  try {
    const currentYear = new Date().getFullYear() + 543;

    // หาเลขล่าสุดของปีนี้
    const [seqRows] = await pool.query<RowDataPacket[]>(
      'SELECT last_number FROM docz_receive_sequences WHERE numbering_year = ?',
      [currentYear]
    );

    let nextNumber = 1;
    if (seqRows.length && seqRows[0].last_number) {
      nextNumber = Number(seqRows[0].last_number) + 1;
    } else {
      // ตรวจสอบจากตาราง docz โดยดู r_number ล่าสุดที่ลงท้ายด้วยปีนี้
      const [maxDocs] = await pool.query<RowDataPacket[]>(
        'SELECT r_number FROM docz WHERE r_number LIKE ? ORDER BY id DESC LIMIT 20',
        [`%/${currentYear}`]
      );
      for (const row of maxDocs) {
        if (row.r_number) {
          const parts = String(row.r_number).split('/');
          const num = parseInt(parts[0], 10);
          if (!isNaN(num) && num >= nextNumber) {
            nextNumber = num + 1;
          }
        }
      }
    }

    // ดึงรายการโปรไฟล์การลงนามทั้งหมด
    const [profiles] = await pool.query<RowDataPacket[]>(
      'SELECT id, code, name FROM doc_profile ORDER BY id ASC'
    );

    // ดึงขั้นตอนผู้ลงนามของแต่ละโปรไฟล์
    const [steps] = await pool.query<RowDataPacket[]>(`
      SELECT dps.id, dps.doc_profile_id, dps.role_name_id, dps.sort, rn.name as role_name
      FROM doc_profile_sub dps
      INNER JOIN role_name rn ON rn.id = dps.role_name_id
      ORDER BY dps.sort ASC, dps.id ASC
    `);

    const profileList = profiles.map((p) => {
      const pSteps = steps.filter(
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

    const suggestedDailyNumber = await getNextDailyNumber(pool, new Date());

    res.json({
      data: {
        to: ['ผู้อำนวยการฯ', 'ผู้พิพากษาหัวหน้าศาลฯ'],
        priorities: ['ปกติ', 'ด่วน', 'ด่วนที่สุด'],
        docTypes: ['E', 'ศาลากลาง', '(ค)', 'วันทำการ'],
        currentYear: String(currentYear),
        suggestedSeq: String(nextNumber),
        suggestedDailyNumber,
        profiles: profileList,
        serverTime: new Date().toISOString(),
        maxFileBytes: 20 * 1024 * 1024,
        maxTotalBytes: 100 * 1024 * 1024,
        maxAttachments: 20,
      },
    });
  } catch (err) {
    console.error('[Incoming Options Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการโหลดตัวเลือกฟอร์ม' });
  }
});

// 2. อัปโหลดไฟล์ชั่วคราว (Two-Phase Upload)
incomingRouter.post(
  '/incoming/upload',
  requireAuth,
  upload.single('file'),
  async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    if (!req.user?.registryOfficer && !req.user?.isAdmin) {
      res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณ' });
      return;
    }

    if (!req.file) {
      res.status(400).json({ message: 'กรุณาเลือกไฟล์' });
      return;
    }

    const kind = req.body.kind === 'MAIN' ? 'MAIN' : 'ATTACHMENT';
    const validation = validateFileExtension(req.file.originalname, kind);
    if (!validation.valid) {
      if (fs.existsSync(req.file.path)) fs.unlinkSync(req.file.path);
      res.status(400).json({ message: validation.error || 'ไฟล์ไม่ถูกต้อง' });
      return;
    }

    // หากเป็นหนังสือนำ (ไฟล์หลัก) ต้องเป็น PDF ขนาด A4 แนวตั้งเท่านั้น
    if (kind === 'MAIN') {
      const pdfCheck = validatePdfA4Portrait(req.file.path);
      if (!pdfCheck.valid) {
        if (fs.existsSync(req.file.path)) fs.unlinkSync(req.file.path);
        res.status(400).json({
          message: pdfCheck.message,
          details: pdfCheck.pages,
        });
        return;
      }
    }

    const filename = req.file.filename;
    const token = path.basename(filename, path.extname(filename));
    const ext = path.extname(req.file.originalname).replace('.', '').toLowerCase();

    // ย้ายไฟล์ไปยัง uploads/docz/
    const targetDir = path.resolve(__dirname, '../../../web/uploads/docz');
    if (!fs.existsSync(targetDir)) {
      fs.mkdirSync(targetDir, { recursive: true });
    }

    const finalStoragePath = `uploads/docz/${token}.${ext}`;
    const targetFilePath = path.resolve(__dirname, '../../../web', finalStoragePath);
    fs.renameSync(req.file.path, targetFilePath);

    res.json({
      data: {
        token,
        name: req.file.originalname,
        size: req.file.size,
        extension: ext,
        kind,
        storageKey: finalStoragePath,
      },
    });
  }
);

// 3. บันทึกนำเข้าหนังสือ (รองรับเลขลงรับที่กรอกเอง หรือคำนวณอัตโนมัติ และผูกโปรไฟล์การลงนาม)
incomingRouter.post('/incoming/register', requireAuth, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user?.registryOfficer && !req.user?.isAdmin) {
    res.status(403).json({ message: 'เฉพาะเจ้าหน้าที่สารบรรณ' });
    return;
  }

  const {
    isDaily: reqIsDaily,
    receivePrefix,
    receiveSeq,
    receiveYear,
    receiveNumber: customReceiveNumber,
    profileCode,
    subject,
    to,
    priority,
    letterNumber,
    letterDate,
    receivedAt,
    mainToken,
    attachmentTokens,
  } = req.body;

  if (!subject || !to) {
    res.status(400).json({ message: 'กรุณาระบุเรื่องและผู้รับ' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const receiveDate = receivedAt ? new Date(receivedAt) : new Date();
    const fallbackYear = receiveDate.getFullYear() + 543;

    let receiveNumber = '';
    const prefix = receivePrefix !== undefined && receivePrefix !== null ? String(receivePrefix).trim() : '';
    const cleanSeq = receiveSeq !== undefined && receiveSeq !== null ? String(receiveSeq).trim() : '';
    const cleanYear = receiveYear !== undefined && receiveYear !== null ? String(receiveYear).trim() : '';
    const isDaily = Boolean(reqIsDaily) || prefix === 'วันทำการ' || /^\d{8}-\d+/.test(cleanSeq);

    if (isDaily) {
      // เอกสารวันทำการ: ออกเลขตามวันที่ YYYYMMDD-XXX
      const dailySeq = cleanSeq || (await getNextDailyNumber(connection, receiveDate));
      receiveNumber = prefix ? `${prefix} ${dailySeq}` : dailySeq;
    } else if (cleanSeq && cleanYear) {
      // 1. ผู้ใช้กรอกเลขและปี พ.ศ. เอง
      const numPart = `${cleanSeq}/${cleanYear}`;
      receiveNumber = prefix ? `${prefix} ${numPart}` : numPart;
      const parsedSeq = parseInt(cleanSeq, 10);
      const parsedYear = parseInt(cleanYear, 10);

      // ถ้าเป็นตัวเลข ให้อัปเดต sequence ตาราง docz_receive_sequences เพื่อให้เลขถัดไปเดินหน้าต่อ
      if (!isNaN(parsedSeq) && !isNaN(parsedYear)) {
        const [existingSeq] = await connection.query<RowDataPacket[]>(
          'SELECT last_number FROM docz_receive_sequences WHERE numbering_year = ? FOR UPDATE',
          [parsedYear]
        );
        if (existingSeq.length) {
          if (parsedSeq > Number(existingSeq[0].last_number)) {
            await connection.query('UPDATE docz_receive_sequences SET last_number = ?, updated_at = NOW() WHERE numbering_year = ?', [parsedSeq, parsedYear]);
          }
        } else {
          await connection.query('INSERT INTO docz_receive_sequences (numbering_year, last_number, updated_at) VALUES (?, ?, NOW())', [parsedYear, parsedSeq]);
        }
      }
    } else if (customReceiveNumber && String(customReceiveNumber).trim()) {
      // 2. ผู้ใช้ระบุเลขรับแบบสตริงรวม
      receiveNumber = String(customReceiveNumber).trim();
    } else {
      // 3. กรณีไม่ได้กรอกเลข ให้ระบบออกเลขอัตโนมัติด้วย MySQL Transaction Lock
      const [seqRows] = await connection.query<RowDataPacket[]>(
        'SELECT last_number FROM docz_receive_sequences WHERE numbering_year = ? FOR UPDATE',
        [fallbackYear]
      );

      let nextNumber = 1;
      if (seqRows.length) {
        nextNumber = Number(seqRows[0].last_number) + 1;
        await connection.query('UPDATE docz_receive_sequences SET last_number = ?, updated_at = NOW() WHERE numbering_year = ?', [nextNumber, fallbackYear]);
      } else {
        await connection.query('INSERT INTO docz_receive_sequences (numbering_year, last_number, updated_at) VALUES (?, 1, NOW())', [fallbackYear]);
      }

      const numPart = `${nextNumber}/${fallbackYear}`;
      receiveNumber = prefix ? `${prefix} ${numPart}` : numPart;
    }

    // กำหนดโปรไฟล์แบบฟอร์มการลงนาม (doc_form)
    const formCode = (profileCode && String(profileCode).trim())
      ? String(profileCode).trim()
      : (to === 'ผู้อำนวยการฯ' ? 'DocZpo' : 'DocZ');

    const mainFile = mainToken ? `uploads/docz/${mainToken}.pdf` : null;
    const docUid = generateUid();

    // บันทึกตาราง docz พร้อมสร้าง UUID v7
    const [docResult] = await connection.query<ResultSetHeader>(
      `INSERT INTO docz (uid, r_number, r_date, doc_speed, doc_form_number, doc_date, doc_form, doc_to, name, file, user_create, st, created)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())`,
      [
        docUid,
        receiveNumber,
        receivedAt || new Date(),
        priority || null,
        letterNumber || null,
        letterDate ? `${letterDate} 00:00:00` : null,
        formCode,
        to,
        subject.trim(),
        mainFile,
        req.user.id,
      ]
    );

    const docId = docResult.insertId;

    // บันทึกเอกสารแนบเพิ่มเติมลงตาราง doc_file (ถ้ามี)
    if (Array.isArray(attachmentTokens) && attachmentTokens.length > 0) {
      for (const att of attachmentTokens) {
        if (att && att.storageKey) {
          const fileUid = generateUid();
          await connection.query(
            `INSERT INTO doc_file (uid, docz_id, doc_form, name, file, ext, user_id_create, created)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())`,
            [
              fileUid,
              docId,
              formCode,
              att.name || 'เอกสารแนบ',
              att.storageKey,
              att.extension || null,
              req.user.id,
            ]
          );
        }
      }
    }

    // หนังสือที่ลงรับใหม่จะอยู่ในสถานะ st = 1 (รอเสนอ) เสมอ
    // ขั้นตอนใน doc_manage และการประทับตราเลขรับจะเกิดขึ้นเมื่อเจ้าหน้าที่กด "📤 เสนอหนังสือ" (POST /documents/:id/send)
    await connection.commit();

    res.status(201).json({
      data: {
        id: docId,
        uid: docUid,
        receiveNumber,
        subject,
        to,
        legacyStatus: 1,
      },
      message: `ลงรับหนังสือเรียบร้อย เลขรับ: ${receiveNumber}`,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Register Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลงรับหนังสือ' });
  } finally {
    connection.release();
  }
});
