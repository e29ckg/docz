import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { pool } from '../db/connection';
import { RowDataPacket } from 'mysql2';
import { JWT_SECRET } from '../config/auth';

export interface AuthUser {
  id: number;
  username: string;
  name: string;
  registryOfficer: boolean;
  isAdmin: boolean;
  isSigner?: boolean;
  roles?: { id: number; name: string }[];
}

export interface AuthenticatedRequest extends Request {
  user?: AuthUser;
}

export async function authMiddleware(req: AuthenticatedRequest, res: Response, next: NextFunction): Promise<void> {
  const queryToken = typeof req.query.token === 'string' ? req.query.token : undefined;
  const token = req.cookies?.auth_token || req.headers.authorization?.replace(/^Bearer\s+/i, '') || queryToken;

  if (!token) {
    return next();
  }

  try {
    const decoded = jwt.verify(token, JWT_SECRET) as { id: number; username: string };
    
    // ตรวจสอบสถานะ User จาก DB
    const [userRows] = await pool.query<RowDataPacket[]>(
      'SELECT id, username, status FROM user WHERE id = ? AND status = 10 LIMIT 1',
      [decoded.id]
    );

    if (!userRows.length) {
      res.clearCookie('auth_token');
      return next();
    }

    // ดึง Profile
    const [profileRows] = await pool.query<RowDataPacket[]>(
      'SELECT pfname, name, sname FROM user_profile WHERE user_id = ? LIMIT 1',
      [decoded.id]
    );

    const profile = profileRows[0];
    const fullName = profile ? `${profile.pfname || ''}${profile.name || ''} ${profile.sname || ''}`.trim() : decoded.username;

    // ตรวจสอบสิทธิ์ สารบรรณ / แอดมิน (role_power.role_name_id = 1) และบทบาทอื่นๆ
    const [roleRows] = await pool.query<RowDataPacket[]>(
      `SELECT rp.role_name_id, rn.name as role_name 
       FROM role_power rp 
       INNER JOIN role_name rn ON rn.id = rp.role_name_id 
       WHERE rp.user_id = ?`,
      [decoded.id]
    );

    const isOfficer = roleRows.some((r) => r.role_name_id === 1);
    const isSigner = roleRows.some((r) => r.role_name_id !== 1);
    const isAdmin = isOfficer; // เจ้าหน้าที่สารบรรณ ได้สิทธิ์แอดมินด้วย

    req.user = {
      id: decoded.id,
      username: decoded.username,
      name: fullName || decoded.username,
      registryOfficer: isOfficer || isAdmin,
      isAdmin: isAdmin,
      isSigner: isSigner,
      roles: roleRows.map((r) => ({ id: r.role_name_id, name: r.role_name })),
    };

    next();
  } catch (err) {
    // Token หมดอายุหรือไม่ถูกต้อง
    res.clearCookie('auth_token');
    next();
  }
}

export function requireAuth(req: AuthenticatedRequest, res: Response, next: NextFunction): void {
  if (!req.user) {
    res.status(401).json({ message: 'กรุณาเข้าสู่ระบบ' });
    return;
  }
  next();
}

export function requireAdmin(req: AuthenticatedRequest, res: Response, next: NextFunction): void {
  if (!req.user || !req.user.isAdmin) {
    res.status(403).json({ message: 'เฉพาะผู้ดูแลระบบเท่านั้น' });
    return;
  }
  next();
}
