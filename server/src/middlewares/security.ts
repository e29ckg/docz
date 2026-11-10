import { Request, Response, NextFunction } from 'express';

/**
 * 1. Security Headers Middleware (OWASP A05: Security Misconfiguration)
 * ตั้งค่า HTTP Headers ป้องกัน Clickjacking, MIME Confusion, XSS และ Information Leakage
 */
export function securityHeadersMiddleware(req: Request, res: Response, next: NextFunction): void {
  // ซ่อน Express Signature
  res.removeHeader('X-Powered-By');

  // ป้องกัน MIME Sniffing
  res.setHeader('X-Content-Type-Options', 'nosniff');

  // ป้องกัน Clickjacking: อนุญาตเฉพาะ Same Origin และ Origin ที่กำหนด (เพื่อให้อ่าน PDF ใน iframe ได้)
  res.setHeader('X-Frame-Options', 'SAMEORIGIN');

  // ป้องกัน XSS
  res.setHeader('X-XSS-Protection', '1; mode=block');

  // กำหนด Referrer Policy
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

  // ป้องกัน DNS Prefetch
  res.setHeader('X-DNS-Prefetch-Control', 'off');

  // Content-Security-Policy แบบยืดหยุ่นที่อนุญาต frame-ancestors สำหรับระบบสารบรรณ
  res.setHeader(
    'Content-Security-Policy',
    "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; frame-ancestors 'self' http://localhost:* http://127.0.0.1:* http://10.37.64.15 http://10.37.64.15:*;"
  );

  next();
}

/**
 * 2. Login Rate Limiter (OWASP A04: Insecure Design & A07: Identification and Authentication Failures)
 * ป้องกันการ Brute Force รหัสผ่าน โดยจำกัดการพยายามล็อกอินผิดพลาดสูงสุด 5 ครั้งต่อ 10 นาทีต่อ IP
 */
interface FailedAttempt {
  count: number;
  firstAttemptTime: number;
  lockedUntil?: number;
}

const failedAttemptsMap = new Map<string, FailedAttempt>();

// ทำความสะอาดหน่วยความจำทุก 30 นาที
setInterval(() => {
  const now = Date.now();
  for (const [key, record] of failedAttemptsMap.entries()) {
    if (record.lockedUntil && record.lockedUntil < now) {
      failedAttemptsMap.delete(key);
    } else if (now - record.firstAttemptTime > 15 * 60 * 1000) {
      failedAttemptsMap.delete(key);
    }
  }
}, 30 * 60 * 1000);

export function loginRateLimiter(req: Request, res: Response, next: NextFunction): void {
  const clientIp = (req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() || req.ip || req.socket.remoteAddress || 'unknown';
  const username = String(req.body.username || '').trim().toLowerCase();
  const key = `${clientIp}:${username}`;

  const now = Date.now();
  const record = failedAttemptsMap.get(key);

  if (record && record.lockedUntil && record.lockedUntil > now) {
    const remainingMinutes = Math.ceil((record.lockedUntil - now) / (60 * 1000));
    console.warn(`[Security Alert] Brute force detected from IP: ${clientIp} for user: ${username}`);
    res.status(429).json({
      message: `มีการพยายามเข้าสู่ระบบผิดพลาดเกินกำหนด กรุณารออีก ${remainingMinutes} นาทีแล้วลองใหม่อีกครั้ง`,
    });
    return;
  }

  next();
}

/**
 * บันทึกประวัติการพยายามล็อกอินล้มเหลว
 */
export function recordFailedLogin(req: Request): void {
  const clientIp = (req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() || req.ip || req.socket.remoteAddress || 'unknown';
  const username = String(req.body.username || '').trim().toLowerCase();
  const key = `${clientIp}:${username}`;

  const now = Date.now();
  const record = failedAttemptsMap.get(key);

  const WINDOW_MS = 10 * 60 * 1000; // 10 นาที
  const MAX_ATTEMPTS = 5;
  const LOCKOUT_MS = 10 * 60 * 1000; // ล็อก 10 นาที

  if (!record || now - record.firstAttemptTime > WINDOW_MS) {
    failedAttemptsMap.set(key, { count: 1, firstAttemptTime: now });
  } else {
    record.count += 1;
    if (record.count >= MAX_ATTEMPTS) {
      record.lockedUntil = now + LOCKOUT_MS;
      console.warn(`[Security Alert] Account locked for ${username} from ${clientIp} due to too many failed logins.`);
    }
  }
}

/**
 * รีเซ็ตประวัติล็อกอินเมื่อสำเร็จ
 */
export function resetLoginAttempts(req: Request): void {
  const clientIp = (req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() || req.ip || req.socket.remoteAddress || 'unknown';
  const username = String(req.body.username || '').trim().toLowerCase();
  const key = `${clientIp}:${username}`;
  failedAttemptsMap.delete(key);
}

/**
 * 3. ตรวจสอบ CORS แบบปลอดภัย (OWASP A05)
 */
export function validateCorsOrigin(allowedOrigins: string[]) {
  return (origin: string | undefined, callback: (err: Error | null, allow?: boolean) => void) => {
    // ไม่มี Origin (เช่น Same-Origin request, Curl, Mobile app, Server-to-server)
    if (!origin) {
      return callback(null, true);
    }

    // อยู่ใน Allowed Origins หรือ Localhost หรือวงเครือข่ายภายในหน่วยงาน (10.37.64.x)
    const isLocalhost =
      origin.startsWith('http://localhost') ||
      origin.startsWith('http://127.0.0.1') ||
      origin.startsWith('https://localhost') ||
      origin.startsWith('https://127.0.0.1') ||
      origin.startsWith('http://10.37.64.') ||
      origin.startsWith('https://10.37.64.');

    if (isLocalhost || allowedOrigins.includes(origin)) {
      return callback(null, true);
    }

    // ปฏิเสธคำขอจาก Origin ที่ไม่ได้รับอนุญาต (ป้องกัน Insecure Wildcard)
    console.warn(`[Security Warning] Blocked untrusted CORS request from origin: ${origin}`);
    return callback(new Error('Origin Not Allowed by CORS Policy'));
  };
}
