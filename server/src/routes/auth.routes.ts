import { Router, Response } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { pool } from '../db/connection';
import { AuthenticatedRequest, requireAuth } from '../middlewares/auth';
import { RowDataPacket } from 'mysql2';
import { loginRateLimiter, recordFailedLogin, resetLoginAttempts } from '../middlewares/security';
import { JWT_SECRET } from '../config/auth';

export const authRouter = Router();

// 1. ตรวจสอบสถานะ Session ปัจจุบัน
authRouter.get('/session', async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  if (!req.user) {
    res.json({ data: { user: null, csrfToken: 'node-express-csrf' } });
    return;
  }

  // ดึง navigation groups ตามสิทธิ์ของผู้ใช้
  const navigation: any[] = [];
  const isOfficerOrAdmin = Boolean(req.user.isAdmin || req.user.registryOfficer);

  navigation.push({
    id: 'documents',
    label: 'ระบบเอกสาร',
    items: [
      { id: 'mine', label: 'หนังสือของฉัน', view: 'mine' },
      { id: 'visible', label: isOfficerOrAdmin ? 'ทะเบียนหนังสือ' : 'ค้นหาหนังสือ', view: 'visible' },
    ],
  });

  if (req.user.isSigner) {
    navigation.push({
      id: 'signing',
      label: 'การลงนาม',
      items: [
        { id: 'signing-pending', label: 'รอลงนาม', view: 'signing-pending' },
        { id: 'signing-history', label: 'ประวัติการลงนาม', view: 'signing-history' },
      ],
    });
  }

  if (isOfficerOrAdmin) {
    navigation.push({
      id: 'admin',
      label: 'ผู้ดูแลระบบ',
      items: [
        { id: 'admin-users', label: 'จัดการสมาชิก', view: 'admin-users' },
        { id: 'admin-permissions', label: 'กำหนดสิทธิ์', view: 'admin-permissions' },
        { id: 'admin-doc-profiles', label: 'โปรไฟล์การลงนาม', view: 'admin-doc-profiles' },
        { id: 'admin-doc-cats', label: 'จัดการที่เก็บเอกสาร', view: 'admin-doc-cats' },
        { id: 'admin-destruction', label: 'จัดการทำลายเอกสาร', view: 'admin-destruction' },
        { id: 'admin-destroyed-docs', label: 'ทะเบียนหนังสือที่ทำลาย', view: 'admin-destroyed-docs' },
      ],
    });
  }

  res.json({
    data: {
      user: {
        ...req.user,
        navigation,
      },
      csrfToken: 'node-express-csrf',
    },
  });
});

// 2. เข้าสู่ระบบ (Login) - รองรับ Password Hash ของ Yii2 เดิม + Rate Limiter
authRouter.post('/session/login', loginRateLimiter, async (req: AuthenticatedRequest, res: Response): Promise<void> => {
  const { username, password } = req.body;

  if (!username || !password || typeof username !== 'string' || typeof password !== 'string') {
    res.status(400).json({ message: 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน' });
    return;
  }

  try {
    const [rows] = await pool.query<RowDataPacket[]>(
      'SELECT id, username, password_hash, status FROM user WHERE username = ? LIMIT 1',
      [username.trim()]
    );

    if (!rows.length) {
      recordFailedLogin(req);
      res.status(401).json({ message: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง' });
      return;
    }

    const user = rows[0];

    if (user.status !== 10) {
      recordFailedLogin(req);
      res.status(403).json({ message: 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ' });
      return;
    }

    // ตรวจสอบรหัสผ่านด้วย bcryptjs (เข้ากันได้กับ $2y$ ของ PHP)
    const isMatch = await bcrypt.compare(password, user.password_hash);
    if (!isMatch) {
      recordFailedLogin(req);
      res.status(401).json({ message: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง' });
      return;
    }

    // เข้าสู่ระบบสำเร็จ -> รีเซ็ตประวัติการพยายามล็อกอินผิดพลาด
    resetLoginAttempts(req);

    // สร้าง JWT Token
    const token = jwt.sign(
      { id: user.id, username: user.username },
      JWT_SECRET,
      { expiresIn: '7d' }
    );

    // ดึง Profile เพื่อแสดงชื่อจริง
    const [profileRows] = await pool.query<RowDataPacket[]>(
      'SELECT pfname, name, sname FROM user_profile WHERE user_id = ? LIMIT 1',
      [user.id]
    );
    const profile = profileRows[0];
    const fullName = profile ? `${profile.pfname || ''}${profile.name || ''} ${profile.sname || ''}`.trim() : user.username;

    // ตรวจสอบสิทธิ์ สารบรรณ / แอดมิน (role_power.role_name_id = 1) และบทบาทอื่นๆ
    const [roleRows] = await pool.query<RowDataPacket[]>(
      `SELECT rp.role_name_id, rn.name as role_name 
       FROM role_power rp 
       INNER JOIN role_name rn ON rn.id = rp.role_name_id 
       WHERE rp.user_id = ?`,
      [user.id]
    );
    const isOfficer = roleRows.some((r) => r.role_name_id === 1);
    const isSigner = roleRows.some((r) => r.role_name_id !== 1);
    const isAdmin = isOfficer; // เจ้าหน้าที่สารบรรณ ได้สิทธิ์แอดมินด้วย

    // Set Cookie: ปรับ secure flag อัตโนมัติตาม Protocol
    const isHttps = req.secure || req.headers['x-forwarded-proto'] === 'https' || process.env.NODE_ENV === 'production';
    res.cookie('auth_token', token, {
      httpOnly: true,
      secure: Boolean(isHttps),
      sameSite: 'lax',
      maxAge: 7 * 24 * 60 * 60 * 1000,
    });

    res.json({
      data: {
        token,
        user: {
          id: user.id,
          username: user.username,
          name: fullName || user.username,
          registryOfficer: isOfficer || isAdmin,
          isAdmin: isAdmin,
          isSigner: isSigner,
          roles: roleRows.map((r) => ({ id: r.role_name_id, name: r.role_name })),
        },
        csrfToken: 'node-express-csrf',
      },
    });
  } catch (err) {
    console.error('[Login Error]', err);
    res.status(500).json({ message: 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ' });
  }
});

// 3. ออกจากระบบ (Logout)
authRouter.post('/session/logout', (req: AuthenticatedRequest, res: Response): void => {
  res.clearCookie('auth_token');
  res.json({
    data: {
      user: null,
      csrfToken: 'node-express-csrf',
    },
  });
});
