# DOCZ — สำรองและกู้คืนข้อมูลบน Windows

ตรวจทานล่าสุด: 16 พฤศจิกายน 2026 (Asia/Bangkok) ใช้กับระบบ Node.js/Express + Vue 3 และสคริปต์ที่อยู่โฟลเดอร์หลักของโปรเจกต์

## สำรองทันที

ดับเบิลคลิก `backup.bat` หรือรันจาก Command Prompt:

```bat
cd /d C:\xampp\htdocs\docz
backup.bat
```

หากเรียกจากเครื่องมืออื่นและไม่ต้องการรอกดปุ่ม ใช้ `backup.bat --no-pause` ตัวเรียกส่งคืน exit code ของ `backup-auto.bat` โดย 0 หมายถึงสคริปต์จบสำเร็จ และค่าที่ไม่ใช่ 0 หมายถึงต้องตรวจข้อความและ Log

## สิ่งที่อยู่ใน ZIP

| ต้นทาง | ตำแหน่งใน ZIP |
| --- | --- |
| ฐานข้อมูลตาม `DB_NAME` | `database_<DB_NAME>_<timestamp>.sql` |
| `web/uploads/` | `uploads/` |
| `server/storage/action-files/` | `server/storage/action-files/` |
| `web/fonts/` หากมี | `fonts/` |
| `server/.env` หากมี | `server_env_backup.txt` |

ชื่อ ZIP คือ `DOCZ_BACKUP_YYYYMMDD_HHMMSS.zip` แต่ละชุดเป็น full backup อ่านไฟล์เอกสารจากต้นทางโดยตรง ไม่ใช่ incremental backup และไม่ได้รวมซอร์สโค้ดหรือ runtime ทั้งโปรเจกต์

`action-files` เก็บ PDF หลักฐานการปฏิบัติตามหนังสือ รวมไฟล์ที่นำออกจากรายการแล้วแต่เก็บไว้ภายในเพื่อการตรวจสอบ ต้องกู้คืนคู่กับข้อมูลตาราง `doc_user_action_file` ใน SQL ชุดเดียวกัน

## ตั้งค่าบน Server

- ค่าเริ่มต้น `BACKUP_DIR=D:\DOCZ_BACKUP` ในทั้ง `backup-auto.bat` และ `restore-backup.bat` หากใช้ตำแหน่งอื่นให้แก้ทั้งสองไฟล์ให้ตรงกัน สคริปต์สำรองจะหยุดหากสร้างโฟลเดอร์ปลายทางไม่ได้
- `RETENTION_DAYS=7` กำหนดจำนวนวันเก็บย้อนหลัง
- ติดตั้ง 7-Zip บน Server จาก [เว็บไซต์ทางการ](https://www.7-zip.org/) ทั้งสคริปต์สำรองและกู้คืนตรวจ `Program Files\7-Zip`, `Program Files (x86)\7-Zip` และ `7z.exe` ใน PATH หากไม่พบหรือรันไม่ได้จะหยุดก่อนทำงานกับข้อมูล
- `ZIP_LEVEL=0` ใช้ 7-Zip สร้าง ZIP แบบ Store ไม่บีบอัด เพื่อลดภาระ CPU สำหรับชุดข้อมูลขนาดใหญ่ เช่น 30 GB ขนาด ZIP จะใกล้เคียงต้นฉบับ หากต้องการบีบอัดแบบเร็วให้เปลี่ยนเป็น `ZIP_LEVEL=1`
- สคริปต์อ่าน `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` จาก `server/.env` รูปแบบควรเป็น `KEY=value` ตามตัวอย่างของโปรเจกต์ ตัวอ่านใน BAT ไม่ใช่ dotenv parser เต็มรูปแบบ โดยเฉพาะค่าที่มีเครื่องหมายคำพูดหรือ `!` ต้องตรวจการทำงานก่อนตั้งเวลา
- ใช้ `C:\xampp\mysql\bin\mysqldump.exe` เป็นค่าเริ่มต้น ตรวจให้เป็นเครื่องมือจากชุด MariaDB/MySQL ที่ใช้งานจริง หากติดตั้งที่อื่นให้กำหนด `MYSQL_DUMP` เป็นพาธเต็ม
- บัญชีที่รันต้องอ่านโฟลเดอร์ข้อมูลและเขียนโฟลเดอร์สำรองกับ `logs/` ได้ ชุด ZIP มีรหัสผ่านใน `.env` จึงต้องจำกัดสิทธิ์เข้าถึง

## ตั้งเวลาทุกวัน

คลิกขวา `setup-backup-task.bat` แล้วเลือก **Run as Administrator** สคริปต์ลงทะเบียนงาน `DOCZ_Daily_Backup` เวลา **23:00 ตามเวลาเครื่อง Server** ให้เรียก `backup-auto.bat`

ตรวจบัญชีที่ใช้รันและเงื่อนไขการทำงานใน Task Scheduler รวมถึงกรณีไม่มีผู้ใช้ล็อกอิน แล้วทดสอบ:

```bat
schtasks /run /tn "DOCZ_Daily_Backup"
```

ตรวจผลใน Task Scheduler พร้อม `logs/backup.log` และตรวจว่ามี ZIP ใหม่ที่เปิดอ่านได้ ข้อความปลายทางในตัวเรียกและตัวติดตั้งงานเป็นข้อความค่าเริ่มต้น ให้ยึดค่า `BACKUP_DIR` ในสคริปต์จริง

## ความเร็วและพื้นที่สำหรับชุดข้อมูลขนาดใหญ่

ใช้ 7-Zip สร้าง ZIP ลง `D:\DOCZ_BACKUP` โดยอ่าน `web/uploads/`, `server/storage/action-files/` และ `web/fonts/` จากต้นทางในคำสั่งเดียว ไม่คัดลอกโฟลเดอร์เอกสารลง staging และไม่สร้าง ZIP บน C: ก่อนย้ายไป D: โฟลเดอร์ staging มีเฉพาะ SQL dump และสำเนา `.env` ที่เปลี่ยนชื่อแล้ว โครงสร้างภายใน ZIP ยังคงตามตารางด้านบน

สำหรับเอกสารประมาณ 30 GB แบบ Store เตรียมพื้นที่เพิ่มประมาณ 30 GB สำหรับเอกสารใน ZIP ใหม่ บวกพื้นที่ SQL ทั้งไฟล์ชั่วคราวและสำเนาใน ZIP ไฟล์ตั้งค่า และพื้นที่เผื่อ โดยต้องเผื่อชุดเก่าที่ยังเก็บอยู่ด้วย การมีไดรฟ์ C: และ D: ไม่ได้ยืนยันว่าเป็นดิสก์คนละลูก หากเป็นคนละพาร์ทิชันบน HDD เดียวกัน ความเร็วยังคงถูกจำกัดโดยดิสก์เดียว

สร้างไฟล์นามสกุล `.zip.partial` ก่อน เมื่อ 7-Zip ส่งคืนรหัส 0 จึงเปลี่ยนเป็น `.zip` และเข้าสู่การลบชุดเก่า รหัสคำเตือนของ 7-Zip ก็ถือว่ารอบสำรองล้มเหลวเพื่อไม่รายงานชุดที่อาจขาดไฟล์ว่าสำเร็จ วิธีนี้ไม่ได้รันตรวจ CRC ทั้ง ZIP อัตโนมัติทุกครั้งเพราะจะเพิ่มการอ่านข้อมูลอีกทั้งชุด ผู้ดูแลสามารถตรวจแยกเมื่อเหมาะสม:

```bat
"C:\Program Files\7-Zip\7z.exe" t "D:\DOCZ_BACKUP\DOCZ_BACKUP_YYYYMMDD_HHMMSS.zip"
```

ชื่อไฟล์ด้านบนเป็นตัวอย่าง ให้แทนด้วยชื่อชุดจริง การเปลี่ยนวิธีสร้าง ZIP ไม่แก้ข้อผิดพลาดฐานข้อมูล `mysql.proc` ต้องแก้การส่งออก SQL ให้สำเร็จก่อน

## ลบไฟล์สำรองเก่ากว่า 7 วัน

ขั้นตอนลบทำงานหลังสร้าง ZIP ใหม่สำเร็จ โดยเลือกเฉพาะไฟล์ที่มีชื่อรูปแบบ `DOCZ_BACKUP_YYYYMMDD_HHMMSS.zip` อยู่ตรงใน `BACKUP_DIR` และมีเวลาแก้ไขไฟล์ (`LastWriteTime`) เก่ากว่าเวลาปัจจุบันลบ 7 วัน ไม่ได้คำนวณอายุจากวันที่ในชื่อไฟล์

ไฟล์ชื่ออื่นและไฟล์ในโฟลเดอร์ย่อยไม่อยู่ในเงื่อนไข หากสำรองล้มเหลวก่อนสร้าง ZIP จะไม่ถึงขั้นลบไฟล์เก่า หากลบไฟล์เก่าล้มเหลว ZIP ใหม่ยังอยู่ แต่สคริปต์ส่งคืน exit code 1 และแสดงคำเตือน

## ตรวจผลสำรอง

1. ตรวจ `logs/backup.log` และข้อความบนหน้าจอ โดยเฉพาะ error จาก `mysqldump` และ 7-Zip
2. เปิด ZIP ตรวจ SQL, `uploads/` และ `server/storage/action-files/` เทียบกับต้นทาง หากมีไฟล์หลักฐานจริงต้องพบ PDF ในชุดสำรองด้วย
3. ซ้อมกู้คืนไปยังฐานข้อมูลและโฟลเดอร์แยก แล้วตรวจเอกสารและหลักฐานผ่านแอป

สคริปต์หยุดเมื่อไม่พบ `web/uploads/` หรือ `server/storage/action-files/` เมื่อสำเนา `.env` ไม่สำเร็จ หรือเมื่อ 7-Zip ส่งคืนรหัสอื่นที่ไม่ใช่ 0 หากไม่มี `web/fonts/` หรือ `server/.env` จะไม่รวมส่วนนั้น จึงต้องตรวจเนื้อหา ZIP เทียบกับข้อมูลที่ต้องการกู้คืนด้วย

SQL ใช้ `--single-transaction --quick --routines --triggers --hex-blob --default-character-set=utf8mb4` แต่ไม่ได้ทำ snapshot ไฟล์พร้อมฐานข้อมูลในจังหวะเดียวกัน ให้หยุดการเขียนข้อมูลตามช่วงเวลาที่หน่วยงานกำหนดหากต้องการความสอดคล้องทั้งชุด ตารางที่ไม่รองรับ transaction ไม่ได้รับการรับประกันจาก `--single-transaction`

## กู้คืน

สำรองสถานะปัจจุบันและหยุดการเขียนข้อมูลก่อนกู้คืน โดยซ้อมกับระบบแยกก่อน เครื่องมือ `restore-backup.bat` จะเขียนทับข้อมูลปลายทางหลังผู้ใช้ยืนยัน

1. ตั้ง `BACKUP_DIR` ให้ตรงกับที่เก็บ ZIP
2. ตรวจค่าเชื่อมต่อใน `restore-backup.bat` ก่อนใช้ ปัจจุบันเรียก `C:\xampp\mysql\bin\mysql.exe -u root docz` ไม่ได้อ่าน host, port, user, password หรือชื่อฐานข้อมูลจาก `.env` ให้ปรับเป็นค่าจริงและสร้างฐานข้อมูลปลายทางไว้ก่อน
3. ตรวจโฟลเดอร์ `temp_restore` ว่าไม่มีไฟล์ตกค้างจากรอบก่อน เพราะสคริปต์ใช้โฟลเดอร์เดิมในการแตก ZIP
4. เรียก `restore-backup.bat` กรอกชื่อ ZIP และยืนยันเมื่อพร้อม
5. ตรวจผลนำเข้า SQL และจำนวนไฟล์ แล้วเปิดแอปทดสอบการอ่านหนังสือและหลักฐาน

เครื่องมือกู้คืน SQL, `uploads/` และ `server/storage/action-files/` หาก ZIP เก่าไม่มี `action-files` จะแสดงคำเตือนและคงไฟล์ปลายทางไว้ การคัดลอกใช้ `/E` จึงไม่ลบไฟล์ส่วนเกินที่มีอยู่เดิม

ฟอนต์ใน `fonts/` และค่าตั้งใน `server_env_backup.txt` ต้องนำกลับด้วยตนเองหลังตรวจสอบความเหมาะสม เครื่องมือใช้ 7-Zip แตกไฟล์และหยุดก่อนนำเข้า SQL หากแตกไฟล์ไม่สำเร็จ แต่ยังไม่ได้ตรวจผลนำเข้า SQL ครบทุกขั้น อย่าใช้ข้อความ `[COMPLETED]` เป็นหลักฐานเพียงอย่างเดียวว่ากู้คืนครบแล้ว

## แก้ error 1728: Cannot load from mysql.proc

อาการที่พบคือ `mysqldump --routines` ล้มเหลวขณะ `SHOW FUNCTION STATUS` ทำให้ยังไม่ถึงขั้นคัดลอกไฟล์หรือสร้าง ZIP ของรอบนั้น การตัด `--routines` ออกจะทำให้ไม่รวม stored routines จึงไม่ใช่การสำรองครบตามเดิม

ตรวจเวอร์ชันเครื่องมือจาก Command Prompt:

```bat
C:\xampp\mysql\bin\mysql.exe --version
C:\xampp\mysql\bin\mysqldump.exe --version
```

เชื่อมต่อฐานข้อมูลตามค่าใน `.env` แล้วตรวจ:

```sql
SELECT VERSION(), @@hostname, @@port, @@datadir;
CHECK TABLE mysql.proc;
CHECK TABLE mysql.proc FOR UPGRADE;
SHOW FUNCTION STATUS WHERE Db = 'docz';
SHOW WARNINGS;
SHOW CREATE TABLE mysql.proc\G
SHOW VARIABLES LIKE 'log_error';
```

กรณีที่ตรวจบน Server วันที่ 15 พฤศจิกายน 2026: Server และเครื่องมือเป็น MariaDB 10.11.16 แต่ `mysql.proc` ยังมี `definer char(141)`, `collation_connection char(32)` และ `db_collation char(32)` ซึ่งต่างจากโครงสร้าง MariaDB สาย 10.11 ที่ใช้ `varchar(384)`, `char(64)` และ `char(64)` ตามลำดับ แม้ `CHECK TABLE` ได้ `OK` แต่ `SHOW FUNCTION STATUS` ยังเกิด 1728 ผลนี้ชี้ว่าต้องตรวจการปรับตารางระบบหลังอัปเกรด ยังไม่มีผลยืนยันว่าแก้ไขบน Server สำเร็จแล้ว

หากยืนยันว่าเป็นปัญหาตารางระบบหลังอัปเกรด ให้ผู้ดูแลดำเนินการในช่วงบำรุงรักษา:

1. หยุดการเขียนระบบและ Stop MySQL ผ่าน XAMPP รอโปรเซสหยุดก่อนคัดลอกทั้ง `C:\xampp\mysql\data` และไฟล์ `C:\xampp\mysql\bin\my.ini` ไปยังพื้นที่สำรองแยก ห้ามใช้การคัดลอกโฟลเดอร์ data ขณะบริการยังทำงานแทน cold backup
2. เมื่อสำรองสำเร็จ Start MySQL แล้วเปิด Command Prompt แบบ Administrator ตรวจ `dir C:\xampp\mysql\bin\*upgrade*.exe`
3. ใช้เครื่องมือจากชุด MariaDB 10.11.16 เดียวกัน ขณะที่บริการทำงาน และปรับ host/port/user ให้ตรงกับ Server:

   ```bat
   C:\xampp\mysql\bin\mariadb-upgrade.exe --host=127.0.0.1 --port=3306 --user=root --password --force --upgrade-system-tables
   ```

   หากมีเฉพาะ `mysql_upgrade.exe` ให้ใช้ชื่อนั้นแทน หากไม่พบทั้งสองให้จัดหาเครื่องมือที่ตรงกับชุดติดตั้งก่อน ไม่ใช้ไฟล์จากเวอร์ชันอื่น หากคำสั่งเกิด error ให้เก็บผลและตรวจต่อก่อนดำเนินการขั้นถัดไป

4. เมื่อ upgrade สำเร็จ Restart MySQL แล้วทดสอบ `SHOW FUNCTION STATUS WHERE Db = 'docz';` และ `SHOW PROCEDURE STATUS WHERE Db = 'docz';` จากนั้นรัน backup ใหม่และตรวจ ZIP

เมื่อ `@@datadir` เป็น `C:\xampp\mysql\data\` และ `log_error` เป็น `.\mysql_error.log` ให้ตรวจไฟล์ `C:\xampp\mysql\data\mysql_error.log` โดยไม่ลบหรือแทนที่ `mysql.proc` ด้วยไฟล์จากเครื่องอื่น

อ้างอิง: [mariadb-upgrade](https://mariadb.com/docs/server/clients-and-utilities/deployment-tools/mariadb-upgrade), [โครงสร้างตารางระบบ MariaDB 10.11](https://github.com/MariaDB/server/blob/10.11/scripts/mysql_system_tables.sql)
