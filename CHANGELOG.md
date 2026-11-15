# DOCZ - Changelog / ประวัติการปรับปรุง

ดูรายละเอียดภาษาไทยฉบับสมบูรณ์ได้ที่: [ประวัติการปรับปรุง.md](./ประวัติการปรับปรุง.md)

---

## [2026-11-16]

- ตั้งปลายทางเริ่มต้นเป็น `D:\DOCZ_BACKUP` และอ่าน uploads, action-files และ fonts จากต้นทางเข้า ZIP ในคำสั่งเดียว ลดการคัดลอกเอกสารลง staging โดยคงโครงสร้าง ZIP เดิม ตรวจกรณีไฟล์ซ่อน/ชื่อไทย/โฟลเดอร์ย่อยและการแตกคืนด้วยไฟล์จำลองผ่านแล้ว

- เปลี่ยนการสร้าง ZIP เป็น 7-Zip แบบ Store (`ZIP_LEVEL=0`) พร้อมแสดงความคืบหน้า และตรวจหาเครื่องมือก่อนส่งออกฐานข้อมูล/คัดลอกไฟล์
- ใช้ไฟล์ `.zip.partial` จนสร้างสำเร็จ เพื่อไม่ให้ชุดที่ยังสร้างไม่ครบปรากฏเป็น ZIP สำรองที่เสร็จแล้ว
- เปลี่ยนการแตก ZIP ในสคริปต์กู้คืนเป็น 7-Zip และหยุดก่อนนำเข้า SQL หากแตกไฟล์ผิดพลาด
- ปรับคู่มือเรื่องการติดตั้ง 7-Zip ระดับบีบอัด และพื้นที่ชั่วคราวสำหรับชุดข้อมูลขนาดใหญ่
- ตรวจด้วยไฟล์จำลองผ่าน: ตรวจหา/ไม่พบ 7-Zip, ZIP แบบ Store, เปลี่ยนชื่อไฟล์ชั่วคราว, ตรวจ CRC และแตกคืนไฟล์ซ่อน/ชื่อไทย/โฟลเดอร์ย่อยตรงต้นฉบับ รวมกรณี ZIP เสียต้องหยุดก่อนนำเข้า SQL ยังไม่ได้วัดความเร็วชุดจริง 30 GB

## [2026-11-15]

- เพิ่ม `backup.bat` สำหรับเรียกสำรองข้อมูลแบบดับเบิลคลิก และรองรับ `--no-pause`
- เพิ่ม `server/storage/action-files/` ใน ZIP และการกู้คืน โดยหยุดสำรองเมื่อคัดลอกโฟลเดอร์นี้ไม่สำเร็จ
- ปรับระยะเก็บ ZIP สำรองเป็น 7 วันตาม `LastWriteTime` ลบเฉพาะชื่อชุดสำรองที่ตรงรูปแบบหลังสร้าง ZIP ใหม่สำเร็จ
- จัดทำ [คู่มือสำรองและกู้คืน](BACKUP_GUIDE.md) รวมการตั้งเวลา ข้อจำกัดของสคริปต์ และการตรวจ/ปรับตารางระบบ MariaDB เมื่อพบ error 1728
- ปรับ README และคู่มือติดตั้งให้ตรงกับสคริปต์ปัจจุบัน ระบุเอกสาร PHP/Yii ว่าเป็นข้อมูลย้อนหลัง และปรับลิงก์ประวัติให้ใช้บน GitHub ได้
- การตรวจรอบนี้: ทดสอบเงื่อนไขลบไฟล์เก่าด้วยไฟล์จำลองผ่านแล้ว ยังไม่ได้ยืนยัน backup/restore ครบชุดหรือผลแก้ MariaDB บน Server จริง

## [2026-10-28]
- **Role Management Enhancement**: Added Role Name creation, editing, sorting, and status toggling (`AdminRoles.vue` & API).
- **Document Signing Profiles**: Added signing workflow profile management (`AdminDocProfiles.vue`, `doc_profile`, `doc_profile_sub`).
- **Node.js Express Backend**: Implemented modern RESTful API backend with TypeScript under `server/` (MySQL2 connection pool, Bcrypt password compatibility with Yii2 hashes, JWT authentication).
- **Frontend Integration**: Updated Vue 3 frontend to seamlessly communicate with Node.js Express Backend via REST endpoints and credentials handling.
- **User Management**: SPA-based user management with search, create, update, password reset, and status activation/deactivation.

## [2026-10-27]
- **PHP 8.2 Modernization**: Upgraded Yii to 2.0.55 and mPDF to 8.3.1 for PHP 8.2.35 compatibility.
- **Vue 3 Frontend SPA**: Created Vue 3 + TypeScript + Vite frontend under `frontend/` deploying to `web/app/`.
- **Security & File Gateway**: Implemented secure file download gateway (`web/file.php`) and access permission checks.
- **Incoming Document Registration Architecture**: Designed two-phase upload and atomic numbering sequence lock mechanisms.
- **Automation Scripts**: Created `deploy-frontend.bat`, `dev-frontend.bat`, `start-server.bat`, and `start-all.bat`.
