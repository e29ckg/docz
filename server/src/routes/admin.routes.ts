import { Router, Response } from 'express';
import bcrypt from 'bcryptjs';
import path from 'path';
import fs from 'fs';
import crypto from 'crypto';
import multer from 'multer';
import { pool } from '../db/connection';
import { AuthenticatedRequest, requireAdmin } from '../middlewares/auth';
import { RowDataPacket, ResultSetHeader } from 'mysql2';
import { isUUID, generateUid } from '../utils/uid';

export const adminRouter = Router();

const profileUploadDir = path.resolve(__dirname, '../../../web/uploads/profile');
if (!fs.existsSync(profileUploadDir)) {
  fs.mkdirSync(profileUploadDir, { recursive: true });
}

const profileStorage = multer.diskStorage({
  destination: (_req, _file, cb) => {
    cb(null, profileUploadDir);
  },
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase() || '.png';
    const hash = crypto.randomBytes(16).toString('hex');
    cb(null, `${hash}${ext}`);
  },
});

const profileUpload = multer({
  storage: profileStorage,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10MB
  fileFilter: (_req, file, cb) => {
    const allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    if (allowed.includes(file.mimetype.toLowerCase())) {
      cb(null, true);
    } else {
      cb(new Error('รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น'));
    }
  },
});

// ใช้ requireAdmin กับทุก route ใน admin
adminRouter.use('/admin', requireAdmin);

// 1. Meta ข้อมูลตัวเลือกฟอร์ม
adminRouter.get('/admin/meta', async (_req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const [pfnames] = await pool.query<RowDataPacket[]>('SELECT name FROM profile_pfname ORDER BY sort ASC, id ASC');
    const [departments] = await pool.query<RowDataPacket[]>('SELECT name FROM user_dep_name ORDER BY sort ASC, id ASC');
    const [groupWorks] = await pool.query<RowDataPacket[]>('SELECT name FROM user_group_work ORDER BY sort ASC, id ASC');
    const [roleNames] = await pool.query<RowDataPacket[]>('SELECT id, name, sort FROM role_name WHERE status = 1 ORDER BY sort ASC, id ASC');

    res.json({
      data: {
        pfnames: pfnames.map((r) => r.name),
        departments: departments.map((r) => r.name),
        groupWorks: groupWorks.map((r) => r.name),
        roleNames,
      },
    });
  } catch (err) {
    console.error('[Admin Meta Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงข้อมูล meta' });
  }
});

// 2. ดึงรายชื่อสมาชิกทั้งหมด
adminRouter.get('/admin/users', async (_req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const [users] = await pool.query<RowDataPacket[]>(`
      SELECT u.id, u.uid, u.username, u.email, u.status,
             p.pfname, p.name, p.sname, p.id_card, p.dep_name, p.group_work, p.phone, p.line_id, p.photo, p.sign_photo, p.sort
      FROM user u
      LEFT JOIN user_profile p ON p.user_id = u.id
      ORDER BY p.sort ASC, u.id ASC
    `);

    const [rolePowers] = await pool.query<RowDataPacket[]>(`
      SELECT rp.user_id, rn.id as role_id, rn.name as role_name
      FROM role_power rp
      INNER JOIN role_name rn ON rn.id = rp.role_name_id
    `);

    const userRolesMap: Record<number, { id: number; name: string }[]> = {};
    for (const rp of rolePowers) {
      if (!userRolesMap[rp.user_id]) userRolesMap[rp.user_id] = [];
      userRolesMap[rp.user_id].push({ id: rp.role_id, name: rp.role_name });
    }

    const list = users.map((u) => {
      const fullName = `${u.pfname || ''}${u.name || ''} ${u.sname || ''}`.trim() || u.username;
      return {
        id: u.id,
        uid: u.uid || '',
        username: u.username,
        email: u.email || '',
        status: u.status,
        isActive: u.status === 10,
        pfname: u.pfname || '',
        name: u.name || '',
        sname: u.sname || '',
        id_card: u.id_card || '',
        fullName,
        dep_name: u.dep_name || '',
        group_work: u.group_work || '',
        phone: u.phone || '',
        line_id: u.line_id || '',
        photo: u.photo || '',
        sign_photo: u.sign_photo || '',
        sort: u.sort || 0,
        roles: userRolesMap[u.id] || [],
      };
    });

    res.json({ data: list });
  } catch (err) {
    console.error('[Admin Users Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงรายชื่อสมาชิก' });
  }
});

// 3. เพิ่มสมาชิกใหม่
adminRouter.post('/admin/create-user', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { username, password, name, sname, pfname, id_card, email, dep_name, group_work, phone, line_id } = req.body;

  if (!username || !password || !name) {
    res.status(400).json({ message: 'กรุณากรอก Username, Password และชื่อจริง' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    // เช็ค username ซ้ำ
    const [existing] = await connection.query<RowDataPacket[]>('SELECT id FROM user WHERE username = ? LIMIT 1', [username.trim()]);
    if (existing.length) {
      await connection.rollback();
      res.status(400).json({ message: 'ชื่อผู้ใช้ (Username) นี้ถูกใช้งานแล้ว' });
      return;
    }

    // Hash password ด้วย bcrypt
    const passwordHash = await bcrypt.hash(password, 10);
    const authKey = Math.random().toString(36).substring(2, 15);
    const userEmail = email || `${username}@docz.local`;
    const userUid = generateUid();

    const [userResult] = await connection.query<ResultSetHeader>(
      'INSERT INTO user (uid, username, auth_key, password_hash, email, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 10, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())',
      [userUid, username.trim(), authKey, passwordHash, userEmail]
    );

    const newUserId = userResult.insertId;

    await connection.query(
      'INSERT INTO user_profile (user_id, pfname, name, sname, id_card, dep_name, group_work, phone, line_id, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)',
      [newUserId, pfname || '', name.trim(), sname || '', id_card ? String(id_card).trim() : null, dep_name || '', group_work || '', phone || '', line_id || '']
    );

    await connection.commit();
    res.json({ data: { success: true, id: newUserId, uid: userUid, message: 'เพิ่มสมาชิกเรียบร้อยแล้ว' } });
  } catch (err) {
    await connection.rollback();
    console.error('[Create User Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการสร้างสมาชิก' });
  } finally {
    connection.release();
  }
});

// 4. แก้ไขข้อมูลสมาชิก
adminRouter.post('/admin/update-user', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id, uid, pfname, name, sname, id_card, dep_name, group_work, phone, line_id, email } = req.body;

  const targetIdentifier = uid || id;
  if (!targetIdentifier || !name) {
    res.status(400).json({ message: 'ข้อมูลไม่ครบถ้วน' });
    return;
  }

  try {
    let targetUserId = Number(id);
    if (isUUID(targetIdentifier)) {
      const [uRows] = await pool.query<RowDataPacket[]>('SELECT id FROM user WHERE uid = ? LIMIT 1', [targetIdentifier]);
      if (uRows.length) {
        targetUserId = uRows[0].id;
      } else {
        res.status(404).json({ message: 'ไม่พบข้อมูลสมาชิก' });
        return;
      }
    }

    if (email) {
      await pool.query('UPDATE user SET email = ? WHERE id = ?', [email, targetUserId]);
    }

    await pool.query(
      `UPDATE user_profile
       SET pfname = ?, name = ?, sname = ?, id_card = ?, dep_name = ?, group_work = ?, phone = ?, line_id = ?
       WHERE user_id = ?`,
      [pfname || '', name.trim(), sname || '', id_card ? String(id_card).trim() : null, dep_name || '', group_work || '', phone || '', line_id || '', targetUserId]
    );

    res.json({ data: { success: true, message: 'บันทึกข้อมูลสมาชิกเรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Update User Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขข้อมูลสมาชิก' });
  }
});

// 4.1 อัปโหลดรูปโปรไฟล์ หรือ รูปลายเซ็นดิจิทัล
adminRouter.post('/admin/upload-user-media', profileUpload.single('file'), async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const userId = Number(req.body.userId);
  const type = String(req.body.type || '').trim(); // 'photo' | 'sign_photo'

  if (!userId || (type !== 'photo' && type !== 'sign_photo')) {
    if (req.file) {
      fs.unlink(req.file.path, () => {});
    }
    res.status(400).json({ message: 'ข้อมูลไม่ครบถ้วน ต้องระบุ userId และ type (photo หรือ sign_photo)' });
    return;
  }

  if (!req.file) {
    res.status(400).json({ message: 'ไม่พบไฟล์รูปภาพที่อัปโหลด' });
    return;
  }

  const relativePath = `uploads/profile/${req.file.filename}`;

  try {
    // หาไฟล์เดิมเพื่อลบ
    const [existing] = await pool.query<RowDataPacket[]>(
      `SELECT photo, sign_photo FROM user_profile WHERE user_id = ? LIMIT 1`,
      [userId]
    );

    if (existing.length) {
      const oldFile = type === 'photo' ? existing[0].photo : existing[0].sign_photo;
      if (oldFile && typeof oldFile === 'string' && oldFile.startsWith('uploads/profile/')) {
        const oldFullPath = path.resolve(__dirname, '../../../web', oldFile);
        if (fs.existsSync(oldFullPath)) {
          try { fs.unlinkSync(oldFullPath); } catch (_) {}
        }
      }
    }

    // อัปเดตลงฐานข้อมูล
    const column = type === 'photo' ? 'photo' : 'sign_photo';
    await pool.query(
      `UPDATE user_profile SET ${column} = ? WHERE user_id = ?`,
      [relativePath, userId]
    );

    res.json({
      data: {
        success: true,
        type,
        url: relativePath,
        message: type === 'photo' ? 'อัปโหลดรูปโปรไฟล์เรียบร้อยแล้ว' : 'อัปโหลดรูปลายเซ็นดิจิทัลเรียบร้อยแล้ว',
      },
    });
  } catch (err) {
    console.error('[Upload User Media Error]', err);
    if (req.file) {
      fs.unlink(req.file.path, () => {});
    }
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการบันทึกรูปภาพ' });
  }
});

// 4.2 ลบรูปโปรไฟล์ หรือ รูปลายเซ็นดิจิทัล
adminRouter.post('/admin/delete-user-media', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const userId = Number(req.body.userId);
  const type = String(req.body.type || '').trim(); // 'photo' | 'sign_photo'

  if (!userId || (type !== 'photo' && type !== 'sign_photo')) {
    res.status(400).json({ message: 'ต้องระบุ userId และ type' });
    return;
  }

  try {
    const [existing] = await pool.query<RowDataPacket[]>(
      `SELECT photo, sign_photo FROM user_profile WHERE user_id = ? LIMIT 1`,
      [userId]
    );

    if (existing.length) {
      const oldFile = type === 'photo' ? existing[0].photo : existing[0].sign_photo;
      if (oldFile && typeof oldFile === 'string' && oldFile.startsWith('uploads/profile/')) {
        const oldFullPath = path.resolve(__dirname, '../../../web', oldFile);
        if (fs.existsSync(oldFullPath)) {
          try { fs.unlinkSync(oldFullPath); } catch (_) {}
        }
      }
    }

    const column = type === 'photo' ? 'photo' : 'sign_photo';
    await pool.query(
      `UPDATE user_profile SET ${column} = '' WHERE user_id = ?`,
      [userId]
    );

    res.json({
      data: {
        success: true,
        type,
        message: type === 'photo' ? 'ลบรูปโปรไฟล์เรียบร้อยแล้ว' : 'ลบรูปลายเซ็นดิจิทัลเรียบร้อยแล้ว',
      },
    });
  } catch (err) {
    console.error('[Delete User Media Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลบรูปภาพ' });
  }
});

// 5. เปิด/ระงับการใช้งาน
adminRouter.post('/admin/toggle-user-status', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id } = req.body;
  if (!id) {
    res.status(400).json({ message: 'กรุณาระบุ ID สมาชิก' });
    return;
  }

  if (Number(id) === req.user!.id) {
    res.status(400).json({ message: 'ไม่สามารถเปลี่ยนสถานะบัญชีของตนเองได้' });
    return;
  }

  try {
    const [rows] = await pool.query<RowDataPacket[]>('SELECT status FROM user WHERE id = ? LIMIT 1', [id]);
    if (!rows.length) {
      res.status(404).json({ message: 'ไม่พบผู้ใช้งาน' });
      return;
    }

    const currentStatus = rows[0].status;
    const newStatus = currentStatus === 10 ? 9 : 10;
    const newSort = newStatus === 10 ? 0 : 9999;

    await pool.query('UPDATE user SET status = ? WHERE id = ?', [newStatus, id]);
    await pool.query('UPDATE user_profile SET sort = ? WHERE user_id = ?', [newSort, id]);

    res.json({
      data: {
        success: true,
        status: newStatus,
        message: newStatus === 10 ? 'เปิดใช้งานบัญชีเรียบร้อยแล้ว' : 'ระงับการใช้งานเรียบร้อยแล้ว',
      },
    });
  } catch (err) {
    console.error('[Toggle Status Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะ' });
  }
});

// 6. รีเซ็ตรหัสผ่าน
adminRouter.post('/admin/reset-password', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id, password } = req.body;
  const newPassword = (password && String(password).trim()) || 'password';

  try {
    const hash = await bcrypt.hash(newPassword, 10);
    await pool.query('UPDATE user SET password_hash = ? WHERE id = ?', [hash, id]);

    res.json({ data: { success: true, message: 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Reset Password Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน' });
  }
});

// 7. ดึงข้อมูลสิทธิ์และผู้รับผิดชอบ
adminRouter.get('/admin/roles', async (_req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const [roleNames] = await pool.query<RowDataPacket[]>('SELECT id, name, status, sort FROM role_name ORDER BY sort ASC, id ASC');
    const [rolePowers] = await pool.query<RowDataPacket[]>(`
      SELECT rp.id, rp.role_name_id, rp.user_id,
             rn.name as role_name, u.username,
             p.pfname, p.name as user_name, p.sname as user_sname, p.dep_name, p.group_work,
             r.id as role_table_id, r.name_dep1, r.name_dep2, r.name_dep3
      FROM role_power rp
      INNER JOIN role_name rn ON rn.id = rp.role_name_id
      INNER JOIN user u ON u.id = rp.user_id
      LEFT JOIN user_profile p ON p.user_id = u.id
      LEFT JOIN role r ON (r.user_id = rp.user_id AND r.role_name_id = rp.role_name_id)
      ORDER BY rn.sort ASC, rn.id ASC, rp.id ASC
    `);

    const formattedPowers = rolePowers.map((row) => ({
      id: row.id,
      roleId: row.role_name_id,
      roleName: row.role_name,
      userId: row.user_id,
      username: row.username,
      fullName: `${row.pfname || ''}${row.user_name || ''} ${row.user_sname || ''}`.trim() || row.username,
      dep_name: row.dep_name || '',
      group_work: row.group_work || '',
      name_dep1: row.name_dep1 || null,
      name_dep2: row.name_dep2 || null,
      name_dep3: row.name_dep3 || null,
    }));

    res.json({
      data: {
        roleNames,
        rolePowers: formattedPowers,
      },
    });
  } catch (err) {
    console.error('[Admin Roles Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงข้อมูลสิทธิ์' });
  }
});

// 8. มอบหมายสิทธิ์ให้สมาชิก
adminRouter.post('/admin/assign-role', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { userId, roleId, name_dep1, name_dep2, name_dep3 } = req.body;

  if (!userId || !roleId) {
    res.status(400).json({ message: 'กรุณาเลือกผู้ใช้งานและสิทธิ์' });
    return;
  }

  try {
    const [existing] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM role_power WHERE user_id = ? AND role_name_id = ? LIMIT 1',
      [userId, roleId]
    );

    if (existing.length) {
      res.status(400).json({ message: 'ผู้ใช้งานได้รับสิทธิ์นี้อยู่แล้ว' });
      return;
    }

    const [rpResult] = await pool.query<ResultSetHeader>(
      'INSERT INTO role_power (user_id, role_name_id) VALUES (?, ?)',
      [userId, roleId]
    );

    // ตรวจสอบและบันทึกในตาราง role สำหรับตำแหน่งการลงนาม
    const [roles] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM role WHERE user_id = ? AND role_name_id = ? LIMIT 1',
      [userId, roleId]
    );

    const dep1Val = name_dep1 !== undefined && name_dep1 !== null ? String(name_dep1).trim() : null;
    const dep2Val = name_dep2 !== undefined && name_dep2 !== null ? String(name_dep2).trim() : null;
    const dep3Val = name_dep3 !== undefined && name_dep3 !== null ? String(name_dep3).trim() : null;

    if (!roles.length) {
      const [rn] = await pool.query<RowDataPacket[]>('SELECT name FROM role_name WHERE id = ? LIMIT 1', [roleId]);
      await pool.query(
        'INSERT INTO role (user_id, role_name_id, name_dep1, name_dep2, name_dep3) VALUES (?, ?, ?, ?, ?)',
        [userId, roleId, dep1Val ?? (rn[0]?.name || ''), dep2Val ?? '', dep3Val ?? '']
      );
    } else {
      await pool.query(
        'UPDATE role SET name_dep1 = ?, name_dep2 = ?, name_dep3 = ? WHERE id = ?',
        [dep1Val ?? '', dep2Val ?? '', dep3Val ?? '', roles[0].id]
      );
    }

    res.json({ data: { success: true, id: rpResult.insertId, message: 'มอบหมายสิทธิ์เรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Assign Role Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการมอบหมายสิทธิ์' });
  }
});

// 8.1 แก้ไขข้อมูลการมอบหมายสิทธิ์และตำแหน่งการลงนาม
adminRouter.post('/admin/update-role-power', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id, roleId, name_dep1, name_dep2, name_dep3 } = req.body;
  const powerId = Number(id);

  if (!powerId) {
    res.status(400).json({ message: 'กรุณาระบุรหัสการมอบหมายสิทธิ์ (id)' });
    return;
  }

  try {
    const [rows] = await pool.query<RowDataPacket[]>(
      'SELECT id, user_id, role_name_id FROM role_power WHERE id = ? LIMIT 1',
      [powerId]
    );
    if (!rows.length) {
      res.status(404).json({ message: 'ไม่พบรายการมอบหมายสิทธิ์' });
      return;
    }

    const currentUserId = rows[0].user_id;
    const currentRoleId = rows[0].role_name_id;
    const targetRoleId = roleId ? Number(roleId) : currentRoleId;

    // ถ้ามีการเปลี่ยนบทบาทสิทธิ์ (roleId) ตรวจสอบว่ามีสิทธิ์ซ้ำซ้อนหรือไม่
    if (targetRoleId !== currentRoleId) {
      const [duplicate] = await pool.query<RowDataPacket[]>(
        'SELECT id FROM role_power WHERE user_id = ? AND role_name_id = ? AND id != ? LIMIT 1',
        [currentUserId, targetRoleId, powerId]
      );
      if (duplicate.length) {
        res.status(400).json({ message: 'ผู้ใช้งานนี้มีสิทธิ์ดังกล่าวอยู่แล้ว' });
        return;
      }

      await pool.query('UPDATE role_power SET role_name_id = ? WHERE id = ?', [targetRoleId, powerId]);
    }

    // อัปเดตหรือเพิ่มในตาราง role สำหรับตำแหน่งการลงนาม (name_dep1, name_dep2, name_dep3)
    const dep1Val = name_dep1 !== undefined && name_dep1 !== null ? String(name_dep1).trim() : '';
    const dep2Val = name_dep2 !== undefined && name_dep2 !== null ? String(name_dep2).trim() : '';
    const dep3Val = name_dep3 !== undefined && name_dep3 !== null ? String(name_dep3).trim() : '';

    const [existingRole] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM role WHERE user_id = ? AND role_name_id = ? LIMIT 1',
      [currentUserId, targetRoleId]
    );

    if (existingRole.length) {
      await pool.query(
        'UPDATE role SET name_dep1 = ?, name_dep2 = ?, name_dep3 = ? WHERE id = ?',
        [dep1Val, dep2Val, dep3Val, existingRole[0].id]
      );
    } else {
      await pool.query(
        'INSERT INTO role (user_id, role_name_id, name_dep1, name_dep2, name_dep3) VALUES (?, ?, ?, ?, ?)',
        [currentUserId, targetRoleId, dep1Val, dep2Val, dep3Val]
      );
    }

    res.json({
      data: {
        success: true,
        message: 'บันทึกการแก้ไขผู้ได้รับมอบหมายสิทธิ์เรียบร้อยแล้ว',
      },
    });
  } catch (err) {
    console.error('[Update Role Power Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขข้อมูลสิทธิ์' });
  }
});

// 9. ลบสิทธิ์
adminRouter.post('/admin/remove-role', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id } = req.body;

  try {
    const [rows] = await pool.query<RowDataPacket[]>('SELECT role_name_id FROM role_power WHERE id = ? LIMIT 1', [id]);
    if (!rows.length) {
      res.status(404).json({ message: 'ไม่พบรายการสิทธิ์' });
      return;
    }

    if (rows[0].role_name_id === 1) {
      const [admins] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) as cnt FROM role_power WHERE role_name_id = 1');
      if (admins[0].cnt <= 1) {
        res.status(400).json({ message: 'ไม่สามารถลบสิทธิ์ผู้ดูแลระบบคนสุดท้ายได้' });
        return;
      }
    }

    await pool.query('DELETE FROM role_power WHERE id = ?', [id]);
    res.json({ data: { success: true, message: 'ลบสิทธิ์เรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Remove Role Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลบสิทธิ์' });
  }
});

// 9.1 สร้างชื่อสิทธิ์ใหม่
adminRouter.post(['/admin/roles/create', '/roles/create'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { name, sort } = req.body;
  const roleName = String(name || '').trim();

  if (!roleName) {
    res.status(400).json({ message: 'กรุณากรอกชื่อสิทธิ์' });
    return;
  }

  try {
    const [existing] = await pool.query<RowDataPacket[]>('SELECT id FROM role_name WHERE name = ? LIMIT 1', [roleName]);
    if (existing.length) {
      res.status(400).json({ message: 'ชื่อสิทธิ์นี้มีอยู่ในระบบแล้ว' });
      return;
    }

    const sortOrder = sort !== undefined && sort !== '' ? Number(sort) : 0;
    const [insertResult] = await pool.query<ResultSetHeader>(
      'INSERT INTO role_name (name, sort, status, created_at) VALUES (?, ?, 1, NOW())',
      [roleName, sortOrder]
    );

    res.json({ data: { success: true, id: insertResult.insertId, message: 'เพิ่มชื่อสิทธิ์เรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Create RoleName Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการสร้างชื่อสิทธิ์' });
  }
});

// 9.2 แก้ไขชื่อสิทธิ์
adminRouter.post(['/admin/roles/update', '/roles/update'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id, name, sort, status } = req.body;
  const roleId = Number(id);
  const roleName = String(name || '').trim();

  if (!roleId || !roleName) {
    res.status(400).json({ message: 'ข้อมูลไม่ครบถ้วน' });
    return;
  }

  try {
    const [existing] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM role_name WHERE name = ? AND id != ? LIMIT 1',
      [roleName, roleId]
    );
    if (existing.length) {
      res.status(400).json({ message: 'ชื่อสิทธิ์นี้ถูกใช้งานแล้ว' });
      return;
    }

    const sortOrder = sort !== undefined && sort !== '' ? Number(sort) : 0;
    const statusVal = status !== undefined ? Number(status) : 1;

    await pool.query('UPDATE role_name SET name = ?, sort = ?, status = ? WHERE id = ?', [
      roleName,
      sortOrder,
      statusVal,
      roleId,
    ]);

    res.json({ data: { success: true, message: 'แก้ไขข้อมูลสิทธิ์เรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Update RoleName Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขสิทธิ์' });
  }
});

// 9.3 เปิด/ปิดใช้งานสิทธิ์
adminRouter.post(['/admin/roles/toggle-status', '/roles/toggle-status'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id } = req.body;
  const roleId = Number(id);

  if (!roleId) {
    res.status(400).json({ message: 'กรุณาระบุ ID สิทธิ์' });
    return;
  }

  if (roleId === 1) {
    res.status(400).json({ message: 'ไม่สามารถปิดการใช้งานสิทธิ์สารบรรณ/ผู้ดูแลระบบได้' });
    return;
  }

  try {
    const [rows] = await pool.query<RowDataPacket[]>('SELECT status FROM role_name WHERE id = ? LIMIT 1', [roleId]);
    if (!rows.length) {
      res.status(404).json({ message: 'ไม่พบสิทธิ์นี้' });
      return;
    }

    const currentStatus = rows[0].status;
    const newStatus = currentStatus === 1 ? 0 : 1;

    await pool.query('UPDATE role_name SET status = ? WHERE id = ?', [newStatus, roleId]);

    res.json({
      data: {
        success: true,
        status: newStatus,
        message: newStatus === 1 ? 'เปิดใช้งานสิทธิ์เรียบร้อยแล้ว' : 'ปิดการใช้งานสิทธิ์เรียบร้อยแล้ว',
      },
    });
  } catch (err) {
    console.error('[Toggle RoleName Status Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะสิทธิ์' });
  }
});

// ==========================================
// 10. จัดการโปรไฟล์การลงนาม (Doc Profiles & Steps)
// ==========================================

// 10.1 ดึงรายการโปรไฟล์ทั้งหมดพร้อมขั้นตอนผู้ลงนาม
adminRouter.get(['/admin/doc-profiles', '/doc-profiles'], async (_req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const [profiles] = await pool.query<RowDataPacket[]>(
      'SELECT id, code, name FROM doc_profile ORDER BY id ASC'
    );

    const [steps] = await pool.query<RowDataPacket[]>(`
      SELECT dps.id, dps.doc_profile_id, dps.role_name_id, dps.sort, rn.name as role_name
      FROM doc_profile_sub dps
      INNER JOIN role_name rn ON rn.id = dps.role_name_id
      ORDER BY dps.sort ASC, dps.id ASC
    `);

    // จัดกลุ่ม steps ตาม doc_profile_id (รองรับทั้งจับคู่ด้วย id หรือ code)
    const result = profiles.map((p) => {
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

    res.json({ data: result });
  } catch (err) {
    console.error('[Doc Profiles Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการดึงรายการโปรไฟล์การลงนาม' });
  }
});

// 10.2 สร้างโปรไฟล์การลงนามใหม่
adminRouter.post(['/admin/doc-profiles/create', '/doc-profiles/create'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { code, name, roleIds } = req.body;

  if (!code || !name) {
    res.status(400).json({ message: 'กรุณากรอกรหัส Code และชื่อโปรไฟล์' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const [existing] = await connection.query<RowDataPacket[]>(
      'SELECT id FROM doc_profile WHERE code = ? LIMIT 1',
      [String(code).trim()]
    );
    if (existing.length) {
      await connection.rollback();
      res.status(400).json({ message: 'รหัส Code นี้ถูกใช้งานแล้ว' });
      return;
    }

    const [insertResult] = await connection.query<ResultSetHeader>(
      'INSERT INTO doc_profile (code, name) VALUES (?, ?)',
      [String(code).trim(), String(name).trim()]
    );
    const newProfileId = insertResult.insertId;

    // เพิ่มขั้นตอนผู้ลงนามถ้ามีการเลือก
    if (Array.isArray(roleIds) && roleIds.length > 0) {
      for (let i = 0; i < roleIds.length; i++) {
        await connection.query(
          'INSERT INTO doc_profile_sub (doc_profile_id, role_name_id, sort) VALUES (?, ?, ?)',
          [newProfileId, Number(roleIds[i]), i + 1]
        );
      }
    }

    await connection.commit();
    res.json({ data: { success: true, id: newProfileId, message: 'สร้างโปรไฟล์การลงนามเรียบร้อยแล้ว' } });
  } catch (err) {
    await connection.rollback();
    console.error('[Create Doc Profile Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการสร้างโปรไฟล์การลงนาม' });
  } finally {
    connection.release();
  }
});

// 10.3 แก้ไขโปรไฟล์
adminRouter.post(['/admin/doc-profiles/update', '/doc-profiles/update'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id, code, name } = req.body;

  if (!id || !code || !name) {
    res.status(400).json({ message: 'ข้อมูลไม่ครบถ้วน' });
    return;
  }

  try {
    const [existing] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM doc_profile WHERE code = ? AND id != ? LIMIT 1',
      [String(code).trim(), id]
    );
    if (existing.length) {
      res.status(400).json({ message: 'รหัส Code นี้ถูกใช้งานแล้ว' });
      return;
    }

    await pool.query('UPDATE doc_profile SET code = ?, name = ? WHERE id = ?', [
      String(code).trim(),
      String(name).trim(),
      id,
    ]);

    res.json({ data: { success: true, message: 'แก้ไขข้อมูลโปรไฟล์เรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Update Doc Profile Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขโปรไฟล์' });
  }
});

// 10.4 ลบโปรไฟล์และขั้นตอน
adminRouter.post(['/admin/doc-profiles/delete', '/doc-profiles/delete'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { id } = req.body;
  if (!id) {
    res.status(400).json({ message: 'กรุณาระบุ ID โปรไฟล์' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    const [rows] = await connection.query<RowDataPacket[]>('SELECT id, code FROM doc_profile WHERE id = ? LIMIT 1', [id]);
    if (!rows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบโปรไฟล์ที่ต้องการลบ' });
      return;
    }

    const profile = rows[0];
    // ลบขั้นตอนทั้งหมดที่ผูกกับ profile นี้
    await connection.query('DELETE FROM doc_profile_sub WHERE doc_profile_id = ? OR doc_profile_id = ?', [
      String(profile.id),
      String(profile.code),
    ]);

    // ลบโปรไฟล์
    await connection.query('DELETE FROM doc_profile WHERE id = ?', [id]);

    await connection.commit();
    res.json({ data: { success: true, message: 'ลบโปรไฟล์การลงนามเรียบร้อยแล้ว' } });
  } catch (err) {
    await connection.rollback();
    console.error('[Delete Doc Profile Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลบโปรไฟล์' });
  } finally {
    connection.release();
  }
});

// 10.5 เพิ่มขั้นตอนผู้ลงนามในโปรไฟล์
adminRouter.post(['/admin/doc-profiles/add-step', '/doc-profiles/add-step'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { docProfileId, roleId } = req.body;

  if (!docProfileId || !roleId) {
    res.status(400).json({ message: 'กรุณาเลือกโปรไฟล์และบทบาทผู้ลงนาม' });
    return;
  }

  try {
    const [rows] = await pool.query<RowDataPacket[]>('SELECT id, code FROM doc_profile WHERE id = ? LIMIT 1', [docProfileId]);
    if (!rows.length) {
      res.status(404).json({ message: 'ไม่พบโปรไฟล์' });
      return;
    }

    const profile = rows[0];
    // หาจำนวนขั้นตอนปัจจุบัน
    const [countRows] = await pool.query<RowDataPacket[]>(
      'SELECT COUNT(*) as cnt FROM doc_profile_sub WHERE doc_profile_id = ? OR doc_profile_id = ?',
      [String(profile.id), String(profile.code)]
    );
    const nextSort = Number(countRows[0].cnt || 0) + 1;

    await pool.query(
      'INSERT INTO doc_profile_sub (doc_profile_id, role_name_id, sort) VALUES (?, ?, ?)',
      [profile.id, Number(roleId), nextSort]
    );

    res.json({ data: { success: true, message: 'เพิ่มขั้นตอนผู้ลงนามเรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Add Step Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเพิ่มขั้นตอน' });
  }
});

// 10.6 ลบขั้นตอนผู้ลงนาม
adminRouter.post(['/admin/doc-profiles/remove-step', '/doc-profiles/remove-step'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { stepId } = req.body;

  if (!stepId) {
    res.status(400).json({ message: 'กรุณาระบุขั้นตอนที่ต้องการลบ' });
    return;
  }

  try {
    await pool.query('DELETE FROM doc_profile_sub WHERE id = ?', [stepId]);
    res.json({ data: { success: true, message: 'ลบขั้นตอนเรียบร้อยแล้ว' } });
  } catch (err) {
    console.error('[Remove Step Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลบขั้นตอน' });
  }
});

// 7. จัดการที่เก็บเอกสาร (Doc Categories / Storage)
adminRouter.get(['/admin/doc-cats', '/admin/categories'], async (_req: AuthenticatedRequest, res: Response): Promise<void> => {
  try {
    const [rows] = await pool.query<RowDataPacket[]>(`
      SELECT dcn.id, dcn.uid, dcn.name, dcn.note,
             COUNT(dc.id) as docCount
      FROM doc_cat_name dcn
      LEFT JOIN doc_cat dc ON dc.doc_cat_name_id = dcn.id
      GROUP BY dcn.id, dcn.uid, dcn.name, dcn.note
      ORDER BY dcn.name ASC, dcn.id ASC
    `);

    const data = rows.map((r) => ({
      id: Number(r.id),
      uid: String(r.uid || ''),
      name: String(r.name || ''),
      note: r.note ? String(r.note) : null,
      docCount: Number(r.docCount || 0),
    }));

    res.json({ data });
  } catch (err) {
    console.error('[Admin Doc Cats Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการโหลดรายการที่เก็บเอกสาร' });
  }
});

adminRouter.post(['/admin/doc-cats/create', '/admin/categories/create'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const name = String(req.body.name || '').trim();
  const note = req.body.note !== undefined && req.body.note !== null ? String(req.body.note).trim() : null;

  if (!name) {
    res.status(400).json({ message: 'กรุณากรอกชื่อที่เก็บเอกสาร' });
    return;
  }

  try {
    const [existing] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM doc_cat_name WHERE name = ? LIMIT 1',
      [name]
    );

    if (existing.length) {
      res.status(400).json({ message: `มีที่เก็บเอกสารชื่อ "${name}" อยู่แล้ว` });
      return;
    }

    const catUid = generateUid();
    const [result] = await pool.query<ResultSetHeader>(
      'INSERT INTO doc_cat_name (uid, name, note) VALUES (?, ?, ?)',
      [catUid, name, note || null]
    );

    res.json({
      data: {
        id: result.insertId,
        uid: catUid,
        name,
        note: note || null,
        docCount: 0,
      },
      message: `เพิ่มที่เก็บเอกสาร "${name}" เรียบร้อยแล้ว`,
    });
  } catch (err) {
    console.error('[Create Doc Cat Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเพิ่มที่เก็บเอกสาร' });
  }
});

adminRouter.post(['/admin/doc-cats/update', '/admin/categories/update'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const identifier = req.body.uid || req.body.id;
  const name = String(req.body.name || '').trim();
  const note = req.body.note !== undefined && req.body.note !== null ? String(req.body.note).trim() : null;

  if (!identifier || !name) {
    res.status(400).json({ message: 'ข้อมูลระบุที่เก็บเอกสารไม่ครบถ้วน' });
    return;
  }

  try {
    let catId = Number(req.body.id);
    if (isUUID(identifier)) {
      const [cRows] = await pool.query<RowDataPacket[]>('SELECT id FROM doc_cat_name WHERE uid = ? LIMIT 1', [identifier]);
      if (cRows.length) {
        catId = cRows[0].id;
      } else {
        res.status(404).json({ message: 'ไม่พบที่เก็บเอกสารนี้' });
        return;
      }
    }

    const [existing] = await pool.query<RowDataPacket[]>(
      'SELECT id FROM doc_cat_name WHERE name = ? AND id != ? LIMIT 1',
      [name, catId]
    );

    if (existing.length) {
      res.status(400).json({ message: `มีที่เก็บเอกสารชื่อ "${name}" อยู่แล้ว` });
      return;
    }

    await pool.query(
      'UPDATE doc_cat_name SET name = ?, note = ? WHERE id = ?',
      [name, note || null, catId]
    );

    res.json({
      data: {
        id: catId,
        uid: isUUID(identifier) ? identifier : undefined,
        name,
        note: note || null,
      },
      message: 'บันทึกการแก้ไขที่เก็บเอกสารเรียบร้อยแล้ว',
    });
  } catch (err) {
    console.error('[Update Doc Cat Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการแก้ไขที่เก็บเอกสาร' });
  }
});

adminRouter.post(['/admin/doc-cats/delete', '/admin/categories/delete'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const identifier = req.body.uid || req.body.id;
  if (!identifier) {
    res.status(400).json({ message: 'ระบุรหัสที่เก็บเอกสารไม่ถูกต้อง' });
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    let catId = Number(req.body.id);
    if (isUUID(identifier)) {
      const [cRows] = await connection.query<RowDataPacket[]>('SELECT id FROM doc_cat_name WHERE uid = ? LIMIT 1', [identifier]);
      if (cRows.length) {
        catId = cRows[0].id;
      } else {
        await connection.rollback();
        res.status(404).json({ message: 'ไม่พบที่เก็บเอกสารนี้' });
        return;
      }
    }

    const [catRows] = await connection.query<RowDataPacket[]>(
      'SELECT id, name FROM doc_cat_name WHERE id = ? LIMIT 1',
      [catId]
    );

    if (!catRows.length) {
      await connection.rollback();
      res.status(404).json({ message: 'ไม่พบที่เก็บเอกสารนี้' });
      return;
    }

    // ล้างข้อมูลการผูกใน doc_cat
    await connection.query('DELETE FROM doc_cat WHERE doc_cat_name_id = ?', [catId]);

    // ลบที่เก็บเอกสาร
    await connection.query('DELETE FROM doc_cat_name WHERE id = ?', [catId]);

    await connection.commit();

    res.json({
      message: `ลบที่เก็บเอกสาร "${catRows[0].name}" เรียบร้อยแล้ว`,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Delete Doc Cat Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการลบที่เก็บเอกสาร' });
  } finally {
    connection.release();
  }
});

adminRouter.get(['/admin/doc-cats/:id/documents', '/admin/categories/:id/documents'], async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const param = req.params.id;
  if (!param) {
    res.status(400).json({ message: 'รหัสที่เก็บเอกสารไม่ถูกต้อง' });
    return;
  }

  try {
    let catId = Number(param);
    if (isUUID(param)) {
      const [cRows] = await pool.query<RowDataPacket[]>('SELECT id FROM doc_cat_name WHERE uid = ? LIMIT 1', [param]);
      if (cRows.length) {
        catId = cRows[0].id;
      } else {
        res.status(404).json({ message: 'ไม่พบที่เก็บเอกสารนี้' });
        return;
      }
    }

    const [docs] = await pool.query<RowDataPacket[]>(
      `SELECT d.id, d.uid, d.r_number as receiveNumber, d.r_date as receivedAt,
              d.doc_form_number as letterNumber, d.doc_date as letterDate,
              d.name as subject, d.st as legacyStatus, d.doc_speed as priority,
              d.file as mainFile
       FROM doc_cat dc
       INNER JOIN docz d ON d.id = dc.doc_id
       WHERE dc.doc_cat_name_id = ?
       ORDER BY d.r_date DESC, d.id DESC`,
      [catId]
    );

    res.json({ data: docs });
  } catch (err) {
    console.error('[Doc Cat Documents Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการโหลดรายการเอกสารในที่เก็บนี้' });
  }
});
