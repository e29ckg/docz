# DOCZ: PHP 8.2.35 + Vue 3 — งานปรับปรุงชุดแรก

> สถานะเอกสาร ณ 15 พฤศจิกายน 2026: เนื้อหาด้านล่างเป็นข้อกำหนดและแผนออกแบบย้อนหลังของระบบเดิม ปัจจุบันใช้ Node.js/Express/TypeScript + Vue 3 วิธีติดตั้งและสถานะการใช้งานให้อ้างอิง [README.md](README.md), [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) และ [BACKUP_GUIDE.md](BACKUP_GUIDE.md) ข้อเสนอในเอกสารนี้ไม่ได้หมายความว่าพัฒนาและตรวจรับครบแล้ว

ปรับปรุง 27 ตุลาคม 2026 (Asia/Bangkok)  
โฟลเดอร์หลัก: `C:\xampp\htdocs\docz`

## เปิดใช้งาน

- Vue: [http://localhost:8099/docz/](http://localhost:8099/docz/) หรือ [หน้าแอปโดยตรง](http://localhost:8099/docz/web/app/)
- API session: [http://localhost:8099/docz/web/api.php?r=session/index](http://localhost:8099/docz/web/api.php?r=session/index)
- หน้าเดิม: [http://localhost:8099/docz/web/index.php?r=site/login](http://localhost:8099/docz/web/index.php?r=site/login)

ใช้บัญชีเดิมในตาราง user เฉพาะ status=10 ไม่สร้างบัญชีใหม่ ไม่แก้รหัสผ่าน และแยก session ของ Vue จากหน้าเดิม ช่วงเปลี่ยนผ่านจึงต้องเข้าสู่ระบบแยกในแต่ละหน้า

## สิ่งที่ทำแล้ว

- ต่อเนื่องจาก Yii 2 เดิม: อัปเดต Yii 2.0.41.1 → 2.0.55, mPDF 8.0.10 → 8.3.1 และ dependencies พร้อม composer.lock ใหม่
- Backend PHP API: bootstrap/CSRF, login/logout, หนังสือของฉัน, ค้นหาหนังสือที่มีสิทธิ์, แบ่งหน้าฝั่ง server, ช่วงวันที่, รายละเอียดและดาวน์โหลดหนังสือนำ/ไฟล์แนบ
- Vue 3 + TypeScript + Vite: login, เมนูตามขอบเขตงาน, ตารางค้นหา, pagination, loading/error/empty states และรายละเอียด พร้อม responsive layout
- ตรวจสิทธิ์ API และไฟล์ก่อนอ่านข้อมูล ไม่ serialize hash, auth_key, storage paths หรือความเห็นเกษียนที่ยังไม่ได้สรุปสิทธิ์
- ลิงก์เดิมใน web/uploads/docz ผ่าน file gateway ที่ตรวจสิทธิ์ รองรับทั้ง session เดิมและ Vue; เปิด PDF/ภาพ inline เฉพาะ MIME ที่อนุญาต
- ปิด directory listing/การเข้าถึง source/config และการ execute script ใน uploads ผ่าน Apache rules; แก้ rewrite เดิมที่ชี้กลับ docz/web ซ้ำ
- เลิกสร้าง mPDF ทุกครั้งที่เปิดเว็บ; แก้ static calls สองจุดใน model เดิมที่เกิด fatal error บน PHP 8
- ไม่รัน schema migration ไม่เพิ่ม indexes และไม่เขียนข้อมูลในฐานข้อมูล docz ระหว่างพัฒนา/ทดสอบรอบนี้

## โครงสร้างจริง

~~~text
docz/
  api/
    controllers/      JSON/session/documents/file gateway controllers
    services/         DocumentRepository, LegacyFileResolver
  config/
    api.php           แยก API configuration จากหน้าเดิม
    db.php            ใช้ connection ของฐานข้อมูลเดิม
  frontend/
    src/              Vue + TypeScript
    package.json
    package-lock.json
    vite.config.ts
  web/
    api.php           API entry point
    file.php          ลิงก์ไฟล์เดิมผ่านการตรวจสิทธิ์
    app/              Vue production build
    index.php         หน้า Yii เดิม
    uploads/          ที่เก็บไฟล์เดิม ยังไม่ได้ย้าย physical storage
  controllers/        ระบบเดิม ระหว่างทยอยย้าย API
  models/             ActiveRecord เดิม
  views/              Yii views เดิม
  tests/
    modernization.php
    modernization-http.mjs
    modernization-mysql-readonly.php
~~~

ใช้โครงสร้างนี้แทนตัวอย่าง apps/api และ apps/web ในเอกสารออกแบบก่อนเห็น source เดิม ไม่สร้าง framework ใหม่คู่กับ Yii โดยไม่มีเหตุจำเป็น

## กฎสิทธิ์ชุดแรก

- บทบาทสารบรรณอ้างอิง role_power.role_name_id=1 ตามการใช้งานจริงใน sidebar เดิม จึงอ่านทะเบียนได้; ยังไม่ใช้เป็นสิทธิ์เพิ่ม/แก้ไขข้อมูลผ่าน API ใหม่
- ผู้ใช้ทั่วไปเห็นหนังสือที่ตนเป็นผู้สร้าง ได้รับแจกจ่ายใน doc_user_read หรือเป็นผู้รับผิดชอบบทบาทในขั้นที่ active/completed (doc_manage.st=2/3)
- ตรวจ doc_manage.doc_form ตรงกับ docz.doc_form ป้องกัน ID ของงานต่างชนิดชนกัน; ขั้นอนาคตไม่ให้สิทธิ์เข้าถึง
- “หนังสือของฉัน” ใช้เฉพาะรายการแจกจ่ายถึงผู้ใช้ ไม่รวมทุกงานที่มีสิทธิ์ดู
- ไม่อนุมานว่าผู้ดูแลทุกคนอ่านทุกเอกสารได้ และไม่เปิดเผยข้อความเกษียนจนกว่าจะสรุปขอบเขตการมองเห็น
- ไม่อัปเดตสถานะอ่านโดยอัตโนมัติเมื่อเปิดรายละเอียดใน Vue ชุดแรก

Query ชุดแรกเปลี่ยนจาก correlated EXISTS เป็น IN subqueries ที่ materialize ได้ เนื่องจากตาราง legacy มีเพียง primary indexes ต้องวัด p95 และ EXPLAIN เพิ่มก่อนกำหนด indexes/เป้าหมายโหลด

## API ปัจจุบัน

เส้นทางช่วงเปลี่ยนผ่านใช้ `web/api.php?r=...` ยังไม่ได้เปิด REST `/api/v1` ตามร่างเดิม

| Method | r | ข้อมูล |
| --- | --- | --- |
| GET | session/index | user ปัจจุบันหรือ null และ csrfToken |
| POST | session/login | JSON username/password + X-CSRF-Token |
| POST | session/logout | X-CSRF-Token และ session cookie |
| GET | documents/index | scope=mine/visible, q, from/to แบบ YYYY-MM-DD, page, pageSize ไม่เกิน 100 |
| GET | documents/view | id |
| GET | documents/download | id และ attachmentId ถ้าเป็นไฟล์แนบ |

GET responses ของข้อมูลส่วนตัวใช้ Cache-Control: no-store; API ไม่ redirect ไป login เมื่อหมด session แต่คืน 401, ไม่มีสิทธิ์เอกสารคืน 404, input ผิดคืน 400 และ method ผิดคืน 405

## เครื่องมือและการรัน

Node ใช้ build Vue เท่านั้น เครื่องที่ตรวจมี Node 24.22.1/npm 11.7.0 และ PHP ทั้ง CLI/Apache เป็น 8.2.35

~~~powershell
Set-Location C:\xampp\htdocs\docz
npm --prefix frontend ci
npm --prefix frontend run build
# frontend development (proxy ไป Apache port 8099)
npm --prefix frontend run dev
php tests\modernization.php
node tests\modernization-http.mjs
# คำสั่งนี้อ่านฐานข้อมูลจริง แต่ใช้ READ ONLY transaction
php tests\modernization-mysql-readonly.php
~~~

API ใช้ config/db.php เดิมโดยไม่แสดง credentials รองรับ environment overrides: DOCZ_DB_DSN, DOCZ_DB_USER, DOCZ_DB_PASSWORD, DOCZ_COOKIE_KEY ค่า DB ของ API ใช้ utf8mb4 เฉพาะ connection ไม่เปลี่ยน charset ของตารางเดิม

ส่วนขยาย gd, openssl, curl, intl มี DLL ใน XAMPP แต่ยังไม่ได้เปิดใน php.ini ที่ตรวจครั้งแรก การติดตั้ง/ตรวจ Composer รอบนี้ใช้ `php -d extension=openssl -d extension=curl -d extension=gd -d extension=intl C:\ProgramData\ComposerSetup\bin\composer.phar ...` จึงผ่าน platform checks ภายใต้การโหลดเหล่านี้ ไม่ได้แก้ php.ini ส่วนกลาง ต้องเปิดส่วนขยายที่จำเป็นใน web SAPI และทดสอบงานสร้าง PDF ก่อนนำฟังก์ชันดังกล่าวไปใช้จริง API ค้นหา/ดาวน์โหลดในชุดนี้ไม่เรียก PDF engine

Asset-packagist คืน metadata 404 ระหว่าง update จึงประกาศ legacy Bower packages จาก lockfile เดิมเป็น package repositories และอัปเดต Inputmask/Punycode จาก upstream ตามที่ Yii ใหม่ต้องใช้ ไม่ใช้ ignore-platform-reqs หรือแก้ vendor ด้วยมือ

## หลักฐานตรวจสอบ

- PHP syntax: 172 app/config/view/test files ผ่านก่อนเพิ่ม read-only integration probe ซึ่งตรวจ syntax แยก
- Fixtures: 26 กรณีสิทธิ์/ไฟล์/filters ผ่าน; SQLite ใน memory ไม่แตะ docz
- HTTP integration: 20 กรณี รวม login ด้วย bcrypt, บัญชี inactive, CSRF, session rotation, logout, unauthorized downloads และ file gateway ผ่าน; ใช้ SQLite ชั่วคราวและลบหลังทดสอบ
- Read-only MySQL/MariaDB: ตรวจ 29 active account scopes และ 116 detail requests ผ่าน โดยไม่แสดงชื่อผู้ใช้/เนื้อหาเอกสาร
- Vue type check และ production build ผ่าน; npm audit ไม่พบ vulnerability และ Composer audit ไม่พบ security advisories ในรอบติดตั้ง
- Apache: API bootstrap 200, guest documents/file gateway 401, legacy login 200, source/config paths 403 และ X-Powered-By ยืนยัน PHP/8.2.35
- ตรวจหน้า login/list/detail จริงผ่าน browser ด้วยข้อมูลสมมติ ไม่ล็อกอินบัญชีผู้ใช้จริง

## งานถัดไปและข้อจำกัด

1. ลงรับ/ร่าง/อัปโหลดหลายไฟล์/เลขทะเบียน: ยืนยันกฎรอบปีและเลขเดิมก่อนเขียน transaction/idempotency และ migration ที่ทดสอบบนสำเนา
2. เกษียนสองเส้นทาง → กลับสารบรรณ → แจกจ่าย: ยืนยันผู้รับจริง การมอบอำนาจ/ตีกลับ สิทธิ์ความเห็น และ snapshot ของงานเก่า
3. Admin/แฟ้ม/รายงาน: ทยอยย้ายไป Vue และตรวจ authorization ของ controllers เดิมทุก action ก่อนเปิดใช้ทั้งระบบ; legacy controllers ยังไม่ได้ผ่าน security audit ครบทุกเส้นทาง
4. ทดสอบไฟล์จริง, ดาวน์โหลดสำเร็จแบบ end-to-end และ PDF generation พร้อม GD/fonts; HTTP fixtures ชุดนี้ตรวจการกันไฟล์นอกสิทธิ์ แต่ยังไม่ได้ทดสอบดาวน์โหลดเอกสารจริงผ่านบัญชีจริง
5. ย้าย storage ออกนอก public root หลังมี manifest/backup และ mapping ของไฟล์/รูป/ลายเซ็น; uploads/profile เดิมยังไม่ได้ปรับกฎการมองเห็นทั้งระบบ
6. ทดสอบ UAT ของ legacy forms และ dependency ที่อัปเดต; ยังไม่ได้รัน Codeception template tests เดิมซึ่งอ้างบัญชี demo/admin และ test DB คนละชุด ห้ามนำผล syntax มาใช้แทนผล UAT
7. lavrentiev/yii2-toastr และ SwiftMailer ถูกแจ้ง abandoned ต้องวางงานทดแทน; asset รุ่นเดิมไม่ได้อัปเดตครบทั้งหมดในชุดนี้
8. PHP 8.2 สิ้นสุด security support 31 ธันวาคม 2026 ต้องทดสอบอัปเกรด runtime รุ่นถัดไปก่อนวันดังกล่าว

ไฟล์เดิมที่แก้มีสำเนาใน `C:\project\docz\implementation-backups\2026-10-27` ซึ่งอยู่นอก web root เก็บ manifest/lock, web/index.php, web/.htaccess และ models ที่เปลี่ยนไว้ ยังไม่ได้ซ้อม restore ทั้งระบบ ชุดนี้เป็นการส่งมอบพื้นฐานและเส้นทางอ่านเอกสาร ไม่ใช่การรับรองว่า workflow/Admin/ฟังก์ชันเดิมทั้งหมดพร้อม production แล้ว
