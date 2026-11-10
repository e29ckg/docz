# DOCZ — ระบบสารบรรณอิเล็กทรอนิกส์

ระบบจัดการหนังสือด้วย **Node.js / Express / TypeScript** และ **Vue 3 / Vite** รองรับการลงรับ เสนอเกษียน ลงนาม จ่ายหนังสือ ติดตามการอ่าน จัดเก็บ และทำลายเอกสาร

เวอร์ชันนี้เป็นแอป Node.js/Vue ซึ่งใช้โครงสร้างฐานข้อมูลต่อยอดจาก Yii2 เดิม ให้ใช้ขั้นตอนด้านล่างแทนการติดตั้งด้วย Composer ไฟล์ PHP เดิมที่ยังอยู่ใน Repository เป็นส่วนของระบบเก่า

## ความสามารถ

- ลงรับและแนบไฟล์ PDF พร้อมประทับตราเลขรับ
- กำหนดสายการลงนามและสร้างหน้าบันทึกเกษียนท้าย PDF พร้อมตัดคำไทย
- แจกจ่ายหนังสือและติดตามสถานะการอ่าน
- ปักหมุดหนังสือส่วนตัวด้วยปุ่ม ☆ / ★ ในหน้า **หนังสือของฉัน** หมุดแยกตามบัญชีและเรียงไว้ด้านบน
- จัดการสมาชิก สิทธิ์ โปรไฟล์การลงนาม และแฟ้มจัดเก็บ
- ใช้ฟอนต์ Sarabun ที่เก็บในโปรเจกต์ ขนาดพื้นฐาน 16px

## สิ่งที่ต้องเตรียม

- Node.js **22.12 ขึ้นไป** พร้อม npm
- MariaDB **10.4 ขึ้นไป** เช่นที่ติดตั้งพร้อม XAMPP; ขั้นตอนติดตั้งนี้ทดสอบกับ MariaDB 10.4.32
- Git สำหรับ Clone หรือดาวน์โหลด ZIP จาก GitHub
- บัญชีฐานข้อมูลที่สร้างฐานข้อมูล/ตารางได้สำหรับขั้นตอนติดตั้ง

ตัวแอป Node.js ไม่ต้องใช้ PHP หรือ Apache หากใช้ XAMPP ให้เริ่มบริการ MySQL/MariaDB ก่อน

## ติดตั้งใหม่

### 1. ดาวน์โหลดโค้ดและเตรียมโปรเจกต์

```powershell
git clone https://github.com/e29ckg/docz.git
cd docz
npm run setup
```

บน Windows ใช้ `install.bat` แทน `npm run setup` ได้ สคริปต์จะตรวจ Node.js, ติดตั้ง Dependencies ตาม Lockfile, Build ทั้งสองส่วน, สร้างโฟลเดอร์จัดเก็บ และสร้าง `server/.env` พร้อม JWT Secret แบบสุ่ม โดยไม่เขียนทับไฟล์ตั้งค่าที่มีอยู่

### 2. ตั้งค่าฐานข้อมูล

เปิด `server/.env` แล้วปรับค่าให้ตรงกับเครื่อง เช่น:

```dotenv
PORT=3000
NODE_ENV=production
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=docz_user
DB_PASSWORD=your_database_password
DB_NAME=docz
CORS_ORIGIN=http://localhost:3000,http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173
```

เก็บค่า `JWT_SECRET` ที่สคริปต์สร้างไว้ หากใช้ XAMPP ที่บัญชีฐานข้อมูลไม่มีรหัสผ่าน ให้ตั้ง `DB_PASSWORD=` ตามการตั้งค่าจริง

```powershell
npm run db:init
```

คำสั่งนี้สร้างฐานข้อมูลและตารางจาก `server/src/db/schema.sql` พร้อมสิทธิ์สารบรรณและคำนำหน้าพื้นฐาน **ไม่มีข้อมูลผู้ใช้หรือเอกสารจริง** และจะปฏิเสธการทำงานหากฐานข้อมูลมีตารางอยู่แล้ว

### 3. สร้างผู้ดูแลระบบคนแรก

```powershell
npm run admin:create
```

กรอกชื่อผู้ใช้ ชื่อแสดงผล และรหัสผ่านอย่างน้อย 12 ตัวอักษรใน Terminal รหัสผ่านจะไม่แสดงขณะพิมพ์ และจะเก็บเป็น bcrypt hash ไม่มีบัญชีหรือรหัสผ่านเริ่มต้นที่ใช้ร่วมกัน คำสั่งนี้ใช้ได้เมื่อยังไม่มีผู้ดูแลเท่านั้น

### 4. เริ่มใช้งาน

```powershell
npm start
```

เปิด **[http://localhost:3000/docz/](http://localhost:3000/docz/)** และเข้าสู่ระบบด้วยบัญชีที่สร้างไว้ จากนั้นเพิ่มสมาชิก กำหนดสิทธิ์ และสร้างโปรไฟล์การลงนามจากเมนูผู้ดูแลระบบ

ตรวจสถานะเซิร์ฟเวอร์ได้ที่ [http://localhost:3000/health](http://localhost:3000/health) จุดตรวจนี้แสดงสถานะ HTTP; ผลเชื่อมต่อฐานข้อมูลจะแสดงใน Terminal ตอนเริ่มเซิร์ฟเวอร์

## การพัฒนา

เปิด Terminal สองหน้าต่าง:

```powershell
# Terminal 1: Backend
npm run dev
```

```powershell
# Terminal 2: Frontend พร้อม Hot Reload
npm run dev:frontend
```

เปิด [http://127.0.0.1:5173/docz/](http://127.0.0.1:5173/docz/) โดย Vite ส่งคำขอ API ไปที่ Backend พอร์ต 3000 ปกติไม่ต้องสร้างไฟล์ตั้งค่า Frontend เพิ่ม หากกำหนด `VITE_API_URL` เอง ให้ดู `frontend/.env.example`

## อัปเดตระบบที่มีข้อมูลเดิม

1. สำรองฐานข้อมูล, `server/.env` และ `web/uploads/` ก่อนอัปเดต
2. ดาวน์โหลดโค้ดใหม่ แล้วติดตั้ง Dependencies และ Build:

   ```powershell
   npm --prefix server ci
   npm --prefix frontend ci
   npm run build
   ```

3. สำหรับการย้ายจาก Yii2 ให้ผู้ดูแลฐานข้อมูลตรวจและรัน `server/src/db/migrate_production.sql` บน MariaDB หลังสำรองข้อมูลแล้ว สคริปต์นี้ใช้ชื่อฐานข้อมูล `docz`; ปรับ `USE` หากใช้ชื่ออื่น
4. รีสตาร์ตโปรเซส Backend ที่กำลังใช้งานจริง เช่น หยุดด้วย `Ctrl + C` แล้วสั่ง `npm start` ใหม่ หรือ `pm2 restart docz-server` หากติดตั้งผ่าน PM2
5. กด **Ctrl + F5** ในเบราว์เซอร์

ไม่ต้องรัน `db:init` หรือ `admin:create` กับฐานข้อมูลเดิม ตารางหมุดส่วนตัวจะสร้างอัตโนมัติเมื่อเปิดหน้าหนังสือของฉัน หากบัญชีฐานข้อมูลไม่มีสิทธิ์สร้างตาราง ให้ผู้ดูแลสร้างตาราง `doc_user_pin` ตาม Migration ก่อน

## คำสั่งที่ใช้บ่อย

| คำสั่ง | การทำงาน |
| --- | --- |
| `npm run setup` | เตรียมไฟล์ตั้งค่า ติดตั้ง Dependencies และ Build |
| `npm run db:init` | สร้างฐานข้อมูลใหม่ที่ยังไม่มีตาราง |
| `npm run admin:create` | สร้างบัญชีผู้ดูแลคนแรก |
| `npm run build` | Build Backend และ Frontend |
| `npm start` | เริ่ม Backend และเสิร์ฟ Frontend ที่ Build แล้ว |
| `npm run dev` | เริ่ม Backend จาก TypeScript |
| `npm run dev:frontend` | เริ่ม Vite สำหรับพัฒนา |

## โครงสร้างหลัก

```text
scripts/setup.mjs                 เตรียมติดตั้งบน Windows/Linux
install.bat                       ตัวเรียกติดตั้งสำหรับ Windows
server/src/                       Backend และ API
server/src/db/schema.sql          ฐานข้อมูลเปล่าสำหรับติดตั้งใหม่
server/src/db/migrate_production.sql  Migration สำหรับฐานข้อมูลเดิม
server/src/scripts/               คำสั่งสร้างฐานข้อมูลและผู้ดูแล
server/tests/                     Regression tests
frontend/src/                     หน้าเว็บ Vue และฟอนต์ Sarabun
web/fonts/                        ฟอนต์สำหรับประทับ PDF
web/app/                          ผลลัพธ์ Frontend Build
web/uploads/                      เอกสารและรูปภาพผู้ใช้
```

## แก้ปัญหาเบื้องต้น

| อาการ | วิธีตรวจ |
| --- | --- |
| `Cannot POST .../pin` หลังอัปเดต | รีสตาร์ต Backend ให้โหลด API ใหม่ แล้วกด Ctrl + F5 |
| หน้าเว็บขึ้น Not Found | ใช้ URL `/docz/` และรัน `npm run build` ก่อน `npm start` |
| เชื่อมต่อฐานข้อมูลไม่ได้ | เริ่มบริการ MariaDB และตรวจ `DB_*` ใน `server/.env` |
| `Database is not empty` | ใช้ขั้นตอนอัปเดตระบบเดิม หรือกำหนดชื่อฐานข้อมูลใหม่สำหรับการติดตั้งใหม่ |
| ปักหมุดไม่ได้ | หนังสือต้องได้รับแจกจ่ายถึงบัญชีปัจจุบันในหน้าหนังสือของฉัน |
| พอร์ต 3000 ถูกใช้งาน | หยุด Backend ตัวเดิม หรือเปลี่ยน `PORT` และ URL ที่ใช้งาน; หากพัฒนาให้ปรับ Vite Proxy ด้วย |
| เปิดพอร์ต 80 ไม่ได้ | ใช้พอร์ต 3000 ได้ตามปกติ; Backend จะรายงานเมื่อพอร์ต 80 ไม่พร้อมใช้งาน |

ไฟล์ `.env`, เอกสารใน `web/uploads/`, Logs และฐานข้อมูลสำรองเป็นข้อมูลเฉพาะเครื่องและไม่ใช่ไฟล์สำหรับ Commit รายละเอียดการดูแลเซิร์ฟเวอร์เพิ่มเติมอยู่ใน [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)
