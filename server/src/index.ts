import express, { Request, Response, NextFunction } from 'express';
import http from 'http';
import cors from 'cors';
import cookieParser from 'cookie-parser';
import dotenv from 'dotenv';
import path from 'path';
import fs from 'fs';
import { testConnection } from './db/connection';
import { authMiddleware } from './middlewares/auth';
import { securityHeadersMiddleware, validateCorsOrigin } from './middlewares/security';
import { authRouter } from './routes/auth.routes';
import { documentRouter } from './routes/document.routes';
import { adminRouter } from './routes/admin.routes';
import { incomingRouter } from './routes/incoming.routes';
import { signingRouter } from './routes/signing.routes';
import { destructionRouter } from './routes/destruction.routes';

dotenv.config({ path: path.resolve(__dirname, '../.env') });

const app = express();
const PORT = process.env.PORT || 3000;

// ปิดการเปิดเผย X-Powered-By
app.disable('x-powered-by');

// 1. Security Headers (OWASP A05)
app.use(securityHeadersMiddleware);

// 2. ปลอดภัยด้วย CORS Whitelist Strict Validation (OWASP A05)
const allowedOrigins = (process.env.CORS_ORIGIN || '')
  .split(',')
  .map((o) => o.trim())
  .filter(Boolean);

app.use(
  cors({
    origin: validateCorsOrigin(allowedOrigins),
    credentials: true,
  })
);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(authMiddleware);

// Health check
app.get(['/health', '/docz/health'], (_req: Request, res: Response) => {
  res.json({ status: 'OK', timestamp: new Date().toISOString() });
});

// Middleware สำหรับ Backward Compatibility กับ Yii2 API format (?r=route/action)
app.use((req: Request, _res: Response, next: NextFunction) => {
  const r = req.query.r;
  if (r && typeof r === 'string') {
    const isDoczPrefix = req.path.startsWith('/docz');
    const apiBase = isDoczPrefix ? '/docz/api' : '/api';

    if (r === 'session/index') req.url = `${apiBase}/session`;
    else if (r === 'session/login') req.url = `${apiBase}/session/login`;
    else if (r === 'session/logout') req.url = `${apiBase}/session/logout`;
    else if (r === 'documents/index') req.url = `${apiBase}/documents`;
    else if (r === 'documents/view') req.url = `${apiBase}/documents/${req.query.id}`;
    else if (r === 'documents/download') req.url = `${apiBase}/documents/${req.query.id}/download`;
    else if (r === 'admin/users') req.url = `${apiBase}/admin/users`;
    else if (r === 'admin/meta') req.url = `${apiBase}/admin/meta`;
    else if (r === 'admin/create-user') req.url = `${apiBase}/admin/create-user`;
    else if (r === 'admin/update-user') req.url = `${apiBase}/admin/update-user`;
    else if (r === 'admin/toggle-user-status') req.url = `${apiBase}/admin/toggle-user-status`;
    else if (r === 'admin/reset-password') req.url = `${apiBase}/admin/reset-password`;
    else if (r === 'admin/roles') req.url = `${apiBase}/admin/roles`;
    else if (r === 'admin/roles/create') req.url = `${apiBase}/admin/roles/create`;
    else if (r === 'admin/roles/update') req.url = `${apiBase}/admin/roles/update`;
    else if (r === 'admin/roles/toggle-status') req.url = `${apiBase}/admin/roles/toggle-status`;
    else if (r === 'admin/assign-role') req.url = `${apiBase}/admin/assign-role`;
    else if (r === 'admin/update-role-power') req.url = `${apiBase}/admin/update-role-power`;
    else if (r === 'admin/remove-role') req.url = `${apiBase}/admin/remove-role`;
    else if (r === 'admin/upload-user-media') req.url = `${apiBase}/admin/upload-user-media`;
    else if (r === 'admin/delete-user-media') req.url = `${apiBase}/admin/delete-user-media`;
    else if (r === 'admin/doc-profiles' || r === 'docprofile/index') req.url = `${apiBase}/admin/doc-profiles`;
    else if (r === 'admin/doc-profiles/create') req.url = `${apiBase}/admin/doc-profiles/create`;
    else if (r === 'admin/doc-profiles/update') req.url = `${apiBase}/admin/doc-profiles/update`;
    else if (r === 'admin/doc-profiles/delete') req.url = `${apiBase}/admin/doc-profiles/delete`;
    else if (r === 'admin/doc-profiles/add-step') req.url = `${apiBase}/admin/doc-profiles/add-step`;
    else if (r === 'admin/doc-profiles/remove-step') req.url = `${apiBase}/admin/doc-profiles/remove-step`;
    else if (r === 'incoming/options') req.url = `${apiBase}/incoming/options`;
    else if (r === 'incoming/upload') req.url = `${apiBase}/incoming/upload`;
    else if (r === 'incoming/register') req.url = `${apiBase}/incoming/register`;
    else if (r === 'signing/pending' || r === 'doczm/index') req.url = `${apiBase}/signing/pending`;
    else if (r === 'signing/history') req.url = `${apiBase}/signing/history`;
    else if (r === 'signing/count') req.url = `${apiBase}/signing/count`;
    else if (r === 'signing/sign' || r === 'doczm/mg') req.url = `${apiBase}/signing/sign`;
    else if (r === 'signing/return' || r === 'doczm/mg_return') req.url = `${apiBase}/signing/return`;
  }
  next();
});

// Static uploads (รองรับทั้ง /uploads และ /docz/uploads)
const uploadsDir = path.resolve(__dirname, '../../web/uploads');
app.use(['/uploads', '/docz/uploads'], express.static(uploadsDir));
app.use(['/api/uploads', '/docz/api/uploads'], express.static(uploadsDir));

// API Info / Health Root (รองรับทั้ง /api และ /docz/api)
app.get(['/api', '/docz/api'], (_req: Request, res: Response) => {
  res.json({
    service: 'DOCZ REST API Service',
    version: '2.0.0',
    status: 'online',
    timestamp: new Date().toISOString(),
    endpoints: {
      auth: {
        session: '/docz/api/session',
        login: '/docz/api/login (POST)',
        logout: '/docz/api/logout (POST)',
        profile: '/docz/api/profile',
      },
      documents: {
        list: '/docz/api/documents',
        unreadCount: '/docz/api/documents/unread-count',
        detail: '/docz/api/documents/:id',
        send: '/docz/api/documents/:id/send',
      },
      incoming: {
        options: '/docz/api/incoming/options',
        register: '/docz/api/incoming/register (POST)',
      },
      signing: {
        documents: '/docz/api/signing/documents',
        sign: '/docz/api/signing/sign (POST)',
      },
      admin: {
        users: '/docz/api/admin/users',
        departments: '/docz/api/admin/departments',
        documentTypes: '/docz/api/admin/document-types',
        systemLogs: '/docz/api/admin/system-logs',
      },
      destruction: {
        list: '/docz/api/destruction',
      },
      system: {
        health: '/docz/health',
      },
    },
  });
});

// Register Routers (รองรับทั้ง /api และ /docz/api)
const apiPrefixes = ['/api', '/docz/api'];
for (const prefix of apiPrefixes) {
  app.use(prefix, authRouter);
  app.use(prefix, documentRouter);
  app.use(prefix, adminRouter);
  app.use(prefix, incomingRouter);
  app.use(prefix, signingRouter);
  app.use(prefix, destructionRouter);
}

// เสิร์ฟ Frontend Vue 3 SPA โดยตรงบน Node.js Express (รองรับทั้ง / และ /docz/)
const spaDir = path.resolve(__dirname, '../../web/app');
if (fs.existsSync(spaDir)) {
  // หากเข้า /docz โดยไม่มี trailing slash หรือ URL เดิม (เช่น /docz/web/app) ให้ redirect 301 ไป /docz/
  app.use((req: Request, res: Response, next: NextFunction) => {
    const rawPath = req.originalUrl.split('?')[0];
    if (
      rawPath === '/docz' ||
      rawPath === '/docz/web' ||
      rawPath === '/docz/web/' ||
      rawPath === '/docz/web/app' ||
      rawPath === '/docz/web/app/' ||
      rawPath === '/web/app' ||
      rawPath === '/web/app/'
    ) {
      const query = req.originalUrl.includes('?') ? '?' + req.originalUrl.split('?')[1] : '';
      return res.redirect(301, '/docz/' + query);
    }
    next();
  });

  // เสิร์ฟโฟลเดอร์ assets สำหรับทุก prefix ที่เป็นไปได้ เพื่อป้องกันปัญหา MIME type text/html
  const assetsDir = path.join(spaDir, 'assets');
  if (fs.existsSync(assetsDir)) {
    app.use(['/assets', '/docz/assets', '/docz/web/assets', '/docz/web/app/assets'], express.static(assetsDir));
  }

  app.use('/docz', express.static(spaDir));

  // รองรับ SPA Routing เฉพาะเส้นทางของ /docz
  app.get(['/docz', '/docz/*'], (req: Request, res: Response, next: NextFunction) => {
    if (
      req.path.startsWith('/docz/api') ||
      req.path.startsWith('/docz/uploads') ||
      req.path === '/docz/health'
    ) {
      return next();
    }
    res.sendFile(path.join(spaDir, 'index.html'));
  });
}

// Proxy ทุกคำขอที่ไม่ใช่ของ docz ไปยัง Apache (Port 8099) เพื่อให้โปรเจกต์อื่นๆ ในเครื่องทำงานได้ตามปกติ
app.use((req: Request, res: Response, next: NextFunction) => {
  if (
    req.path.startsWith('/docz') ||
    req.path.startsWith('/api') ||
    req.path.startsWith('/uploads') ||
    req.path === '/health'
  ) {
    return next();
  }

  // ส่งต่อไปยัง Apache พอร์ต 8099
  const proxyReq = http.request(
    {
      hostname: '127.0.0.1',
      port: 8099,
      path: req.originalUrl,
      method: req.method,
      headers: {
        ...req.headers,
        host: req.headers.host || '127.0.0.1:8099',
        'x-forwarded-for': req.ip,
      },
    },
    (proxyRes) => {
      res.writeHead(proxyRes.statusCode || 500, proxyRes.headers);
      proxyRes.pipe(res);
    }
  );

  proxyReq.on('error', () => {
    res.status(404).send('Not Found');
  });

  req.pipe(proxyReq);
});

// Global Error Handler
app.use((err: any, _req: Request, res: Response, _next: NextFunction) => {
  console.error('[Server Error]', err);
  res.status(err.status || 500).json({
    message: err.message || 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์',
  });
});

// Start Server บน PORT หลัก
app.listen(PORT, async () => {
  console.log(`====================================================`);
  console.log(`   DOCZ Express Backend running on port ${PORT}`);
  console.log(`   Local URL:    http://localhost:${PORT}/docz`);
  console.log(`   Network URL:  http://10.37.64.15:${PORT}/docz`);
  console.log(`   Health:       http://10.37.64.15:${PORT}/health`);
  console.log(`====================================================`);

  const dbOk = await testConnection();
  if (dbOk) {
    console.log(`[Database] MySQL connected successfully (docz)`);
  } else {
    console.warn(`[Database] Warning: Cannot connect to MySQL. Please check XAMPP MySQL.`);
  }
});

// เปิดพอร์ต 80 ควบคู่กันเพื่อให้เข้าใช้งานผ่าน http://10.37.64.15/docz ได้โดยตรงโดยไม่ต้องระบุพอร์ต
if (Number(PORT) !== 80) {
  try {
    const server80 = app.listen(80, () => {
      console.log(`   [Production] Listening on Port 80 -> http://10.37.64.15/docz`);
    });
    server80.on('error', (err: any) => {
      console.warn(`[Port 80 Notice] Port 80 is unavailable (${err.code}), continuing on port ${PORT}`);
    });
  } catch (err: any) {
    console.warn(`[Port 80 Notice] Could not bind port 80:`, err.message);
  }
}
