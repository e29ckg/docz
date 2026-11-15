import { Router, Response, NextFunction } from 'express';
import fs from 'fs';
import path from 'path';
import multer from 'multer';
import { PDFDocument } from 'pdf-lib';
import { RowDataPacket, ResultSetHeader } from 'mysql2';
import { pool } from '../db/connection';
import { ensureDocumentActionFiles } from '../db/documentActionFiles';
import { AuthenticatedRequest, requireAuth } from '../middlewares/auth';
import { resolveDocId } from './document.routes';
import { generateUid, isUUID } from '../utils/uid';

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { files: 5, fileSize: 10 * 1024 * 1024, fields: 1, fieldSize: 8000 },
  fileFilter: (_req, file, done) => {
    if (path.extname(file.originalname).toLowerCase() !== '.pdf') return done(new Error('แนบได้เฉพาะไฟล์ PDF เท่านั้น'));
    done(null, true);
  },
}).array('files', 5);

interface ActionFileRequest extends AuthenticatedRequest { actionDocId?: number; actionDocDestroyed?: boolean; }

export function createActionFilesRouter(storageRoot = path.resolve(__dirname, '../../storage/action-files')) {
  const router = Router();
  async function recipient(req: ActionFileRequest, res: Response, next: NextFunction): Promise<void> {
    try {
      const doc = await resolveDocId(req.params.id);
      if (!doc) { res.status(404).json({ message: 'ไม่พบหนังสือ' }); return; }
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT d.st FROM docz d INNER JOIN doc_user_read r ON r.doc_id = d.id WHERE d.id = ? AND r.user_id = ? LIMIT 1',
        [doc.id, req.user!.id]
      );
      if (!rows.length) { res.status(403).json({ message: 'ใช้งานได้เฉพาะหนังสือที่แจกจ่ายถึงคุณเท่านั้น' }); return; }
      req.actionDocId = doc.id;
      req.actionDocDestroyed = Number(rows[0].st) === 6;
      next();
    } catch (error) { next(error); }
  }

  router.get('/documents/:id/action-files', requireAuth, recipient, async (req: ActionFileRequest, res: Response): Promise<void> => {
    try {
      await ensureDocumentActionFiles();
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT uid, original_name, size_bytes, note, created_at FROM doc_user_action_file WHERE doc_id = ? AND user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, uid DESC',
        [req.actionDocId, req.user!.id]
      );
      res.json({ data: rows.map(row => ({ uid: row.uid, name: row.original_name, size: row.size_bytes, note: row.note || '', createdAt: row.created_at })) });
    } catch (error) {
      console.error('[Action Files List]', error);
      res.status(500).json({ message: 'ไม่สามารถโหลดหลักฐานการปฏิบัติตามหนังสือได้' });
    }
  });

  router.post('/documents/:id/action-files', requireAuth, recipient, (req: ActionFileRequest, res: Response, next: NextFunction) => {
    if (req.actionDocDestroyed) { res.status(409).json({ message: 'ไม่สามารถแนบหลักฐานกับหนังสือที่ทำลายแล้ว' }); return; }
    upload(req, res, error => {
      if (error) {
        res.status(400).json({ message: error instanceof multer.MulterError ? 'แนบ PDF ได้ครั้งละไม่เกิน 5 ไฟล์ ไฟล์ละไม่เกิน 10 MB' : error.message });
        return;
      }
      next();
    });
  }, async (req: ActionFileRequest, res: Response): Promise<void> => {
    const files = req.files as Express.Multer.File[] | undefined;
    const note = typeof req.body?.note === 'string' ? req.body.note.trim() : '';
    if (!files?.length || note.length > 2000) { res.status(400).json({ message: 'กรุณาเลือก PDF และระบุหมายเหตุไม่เกิน 2,000 ตัวอักษร' }); return; }
    try {
      for (const file of files) {
        if (file.buffer.subarray(0, 5).toString() !== '%PDF-') throw new Error('Invalid PDF');
        const pdf = await PDFDocument.load(file.buffer, { updateMetadata: false });
        if (!pdf.getPageCount()) throw new Error('Empty PDF');
      }
    } catch {
      res.status(400).json({ message: 'ไฟล์ต้องเป็น PDF ที่เปิดอ่านได้ และไม่มีรหัสผ่าน' });
      return;
    }
    const created: string[] = [];
    let connection;
    try {
      await ensureDocumentActionFiles();
      fs.mkdirSync(storageRoot, { recursive: true });
      connection = await pool.getConnection();
      await connection.beginTransaction();
      for (const file of files) {
        const uid = generateUid();
        const destination = path.join(storageRoot, `${uid}.pdf`);
        const decoded = Buffer.from(file.originalname, 'latin1').toString('utf8');
        const name = path.basename(decoded.includes('\uFFFD') ? file.originalname : decoded).replace(/[\x00-\x1f\x7f]/g, '').slice(0, 255);
        fs.writeFileSync(destination, file.buffer, { flag: 'wx' });
        created.push(destination);
        await connection.query(
          'INSERT INTO doc_user_action_file (uid, doc_id, user_id, original_name, size_bytes, note) VALUES (?, ?, ?, ?, ?, ?)',
          [uid, req.actionDocId, req.user!.id, name, file.size, note || null]
        );
      }
      await connection.commit();
      res.status(201).json({ data: { count: files.length }, message: 'แนบหลักฐานการปฏิบัติตามหนังสือเรียบร้อยแล้ว' });
    } catch (error) {
      if (connection) await connection.rollback();
      for (const file of created) if (fs.existsSync(file)) fs.unlinkSync(file);
      console.error('[Action Files Upload]', error);
      res.status(500).json({ message: 'ไม่สามารถบันทึกไฟล์หลักฐานได้ กรุณาลองใหม่' });
    } finally { connection?.release(); }
  });

  router.get('/documents/:id/action-files/:uid/file', requireAuth, recipient, async (req: ActionFileRequest, res: Response): Promise<void> => {
    if (!isUUID(req.params.uid)) { res.status(404).json({ message: 'ไม่พบหลักฐาน' }); return; }
    try {
      await ensureDocumentActionFiles();
      const [rows] = await pool.query<RowDataPacket[]>(
        'SELECT uid, original_name FROM doc_user_action_file WHERE uid = ? AND doc_id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1',
        [req.params.uid, req.actionDocId, req.user!.id]
      );
      if (!rows.length) { res.status(404).json({ message: 'ไม่พบหลักฐาน' }); return; }
      const file = path.join(storageRoot, `${rows[0].uid}.pdf`);
      if (!fs.existsSync(file)) { res.status(404).json({ message: 'ไม่พบไฟล์ในระบบจัดเก็บ' }); return; }
      res.setHeader('Cache-Control', 'private, no-store');
      if (req.query.download === '1') res.download(file, rows[0].original_name);
      else {
        res.type('pdf');
        res.setHeader('Content-Disposition', `inline; filename="evidence.pdf"; filename*=UTF-8''${encodeURIComponent(rows[0].original_name)}`);
        res.sendFile(path.resolve(file));
      }
    } catch (error) {
      console.error('[Action File Read]', error);
      res.status(500).json({ message: 'ไม่สามารถเปิดหลักฐานได้' });
    }
  });
  // Remove from the owner's active evidence; retain the stored PDF for audit/recovery.
  router.delete('/documents/:id/action-files/:uid', requireAuth, recipient, async (req: ActionFileRequest, res: Response): Promise<void> => {
    if (!isUUID(req.params.uid)) { res.status(404).json({ message: 'ไม่พบหลักฐาน' }); return; }
    try {
      await ensureDocumentActionFiles();
      const [result] = await pool.query<ResultSetHeader>(
        'UPDATE doc_user_action_file SET deleted_at = NOW() WHERE uid = ? AND doc_id = ? AND user_id = ? AND deleted_at IS NULL',
        [req.params.uid, req.actionDocId, req.user!.id]
      );
      if (!result.affectedRows) { res.status(404).json({ message: 'ไม่พบหลักฐานที่คุณเป็นผู้แนบ' }); return; }
      res.json({ data: { uid: req.params.uid }, message: 'ลบหลักฐานการปฏิบัติตามหนังสือเรียบร้อยแล้ว' });
    } catch (error) {
      console.error('[Action File Delete]', error);
      res.status(500).json({ message: 'ไม่สามารถลบหลักฐานได้ กรุณาลองใหม่' });
    }
  });
  return router;
}

export const actionFilesRouter = createActionFilesRouter();
