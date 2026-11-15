# DOCZ — การติดตั้งบนเซิร์ฟเวอร์

คู่มือนี้ใช้กับเวอร์ชัน Node.js/Vue ขั้นตอนติดตั้งใหม่และการสร้างผู้ดูแลอยู่ใน [README.md](README.md)

ตรวจทานล่าสุด: 16 พฤศจิกายน 2026 (Asia/Bangkok)

## รันบนเครื่องเซิร์ฟเวอร์

1. ติดตั้ง Node.js 22.12 ขึ้นไป และเตรียม MariaDB
2. รัน `npm run setup` จากโฟลเดอร์โปรเจกต์
3. ตั้งค่า `server/.env` ให้ตรงกับฐานข้อมูลของหน่วยงานและคงค่า JWT Secret ที่สร้างแบบสุ่มไว้
4. สำหรับฐานข้อมูลใหม่ รัน `npm run db:init` แล้ว `npm run admin:create`
5. รัน `npm start` และเปิด `http://localhost:3000/docz/`

หากย้ายระบบเดิม ให้สำรองฐานข้อมูลและเอกสารก่อน ตรวจ `server/src/db/migrate_production.sql` และใช้ขั้นตอนอัปเดตใน README แทน `db:init`

## ใช้ PM2

```powershell
npm install -g pm2
pm2 start ecosystem.config.js
pm2 save
pm2 status
```

ตั้งค่าการเริ่มบริการหลังเปิดเครื่องตามระบบปฏิบัติการของเซิร์ฟเวอร์ บน Windows สามารถใช้ Task Scheduler เรียก PM2 หรือใช้เครื่องมือจัดการบริการที่หน่วยงานกำหนด

หลังอัปเดตโค้ด:

```powershell
npm --prefix server ci
npm --prefix frontend ci
npm run build
pm2 restart docz-server
```

## Docker Compose

สร้างไฟล์ `.env` ที่โฟลเดอร์หลักของโปรเจกต์สำหรับ Docker เท่านั้น แล้วกำหนด `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD` และ `JWT_SECRET` เป็นค่าสุ่มเฉพาะการติดตั้งนี้ ไม่ใช้ค่าตัวอย่างร่วมกันระหว่างเครื่อง

สร้างข้อความสุ่มแต่ละค่าได้ด้วย:

```powershell
node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"
```

จากนั้น:

```powershell
docker compose up -d --build
docker compose run --rm app node server/dist/scripts/initDatabase.js
docker compose run --rm app node server/dist/scripts/createAdmin.js
```

การติดตั้ง Docker ใช้ MySQL 8.0 และ Volume สำหรับเอกสาร/ฐานข้อมูล เส้นทาง Docker เป็นทางเลือกและยังไม่ได้ทดสอบ Build ในเครื่องนี้ คำสั่ง `initDatabase` ใช้ได้กับฐานข้อมูลเปล่าเท่านั้น ถ้ามีข้อมูลเดิมให้ใช้วิธีย้ายฐานข้อมูลที่ผู้ดูแลตรวจสอบแล้ว

## Reverse Proxy และ HTTPS

ชี้ Reverse Proxy ไปยังพอร์ต Backend ที่ตั้งใน `PORT` และส่งต่อพาธ `/docz/` รวมถึง `/docz/api/` ให้ตั้ง `CORS_ORIGIN` ใน `server/.env` ให้ตรงกับ Origin ที่ผู้ใช้เข้าใช้งานจริง รวมโปรโตคอลและพอร์ต ติดตั้งใบรับรอง HTTPS ผ่านเครื่องมือที่หน่วยงานดูแล

## สำรองและกู้คืน

บน Windows ใช้ `backup.bat` หรือเรียก `backup-auto.bat` โดยตรง สคริปต์สำรองฐานข้อมูลด้วย `mysqldump` พร้อม `web/uploads/`, `server/storage/action-files/`, `web/fonts/` และ `server/.env` ไปยัง ZIP ค่าเริ่มต้นใน `D:\DOCZ_BACKUP` เก็บ Log ที่ `logs/backup.log`

ติดตั้ง 7-Zip บน Server ก่อน ทั้ง backup และ restore ตรวจหา `7z.exe` อัตโนมัติ ใช้ ZIP แบบ Store (`ZIP_LEVEL=0`) อ่านโฟลเดอร์เอกสารจากต้นทางเข้า ZIP โดยตรง พื้นที่ staging ใช้เฉพาะ SQL และไฟล์ตั้งค่า ต้องเผื่อพื้นที่ SQL ชั่วคราวพร้อม ZIP ใหม่และชุดเก่าที่ยังเก็บอยู่ ดูรายละเอียดในคู่มือสำรองข้อมูล

หลังสร้าง ZIP ใหม่สำเร็จ สคริปต์ลบเฉพาะไฟล์ `DOCZ_BACKUP_YYYYMMDD_HHMMSS.zip` ในโฟลเดอร์ปลายทางที่มี `LastWriteTime` เก่ากว่า 7 วัน ไม่ลบไฟล์อื่นหรือค้นในโฟลเดอร์ย่อย ตั้งเวลารายวัน 23:00 ตามเวลา Server ด้วย `setup-backup-task.bat`

หากเปลี่ยนปลายทางจาก `D:\DOCZ_BACKUP` ให้แก้ `BACKUP_DIR` ในทั้งสคริปต์สำรองและกู้คืน การกู้คืนต้องนำฐานข้อมูลและไฟล์เอกสารชุดเดียวกันกลับมา และสคริปต์กู้คืนยังใช้ค่าเชื่อมต่อฐานข้อมูลที่กำหนดไว้ในไฟล์ ไม่ได้อ่าน `server/.env` อัตโนมัติ ตรวจและปรับก่อนใช้งาน

ดูขั้นตอนใช้งาน ข้อจำกัดการกู้คืน และแนวทางแก้ `Cannot load from mysql.proc` ใน [BACKUP_GUIDE.md](BACKUP_GUIDE.md) ทดสอบกู้คืนในระบบแยกก่อนใช้งานจริง

## ตรวจหลังติดตั้ง

- เปิด `/health` และตรวจ Log การเชื่อมต่อฐานข้อมูลตอนเริ่ม Backend
- เข้าสู่ระบบด้วยบัญชีผู้ดูแลที่สร้างเอง
- เพิ่มสมาชิกและกำหนดสายการลงนามตามการใช้งาน
- ทดลองลงรับ PDF, ลงนาม, แจกจ่าย และปักหมุดด้วยข้อมูลทดสอบ
- เมื่อแก้ Backend ต้องรีสตาร์ตโปรเซส; เมื่ออัปเดตหน้าเว็บให้กด Ctrl + F5
