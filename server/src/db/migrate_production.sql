-- =====================================================================
-- DOCZ Production Database Migration Script
-- สำหรับปรับปรุงฐานข้อมูลเดิมขึ้นสู่ระบบ Node.js Express + Vue 3
-- สามารถนำไปรันบน phpMyAdmin หรือ MySQL Command Line บน Server จริงได้ทันที
-- =====================================================================

USE `docz`;

-- Personal document pins; each recipient maintains their own selection.
CREATE TABLE IF NOT EXISTS `doc_user_pin` (
  `user_id` INT NOT NULL,
  `doc_id` INT NOT NULL,
  `pinned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1. สร้างตารางใหม่ที่จำเป็น (New Tables)
-- ---------------------------------------------------------------------

-- 1.1 ตารางจัดการ Atomic Sequence เลขรับหนังสือ (ป้องกันเลขรับชนกันเมื่อกดพร้อมกัน)
CREATE TABLE IF NOT EXISTS `docz_receive_sequences` (
  `numbering_year` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`numbering_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1.2 ตารางหมวดที่เก็บเอกสาร
CREATE TABLE IF NOT EXISTS `doc_cat_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1.3 ตารางชุดคำสั่งทำลายเอกสาร
CREATE TABLE IF NOT EXISTS `doc_destruction_batch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `target_year` int(11) DEFAULT NULL,
  `order_number` varchar(100) DEFAULT NULL,
  `approval_number` varchar(100) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `total_docs` int(11) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `batch_number` (`batch_number`),
  KEY `idx_ddb_uid` (`uid`),
  KEY `idx_ddb_year` (`target_year`),
  KEY `idx_ddb_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.4 ตารางรายการเอกสารที่ถูกทำลาย
CREATE TABLE IF NOT EXISTS `doc_destruction_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `doc_id` int(11) NOT NULL,
  `doc_uid` char(36) NOT NULL,
  `receive_number` varchar(100) DEFAULT NULL,
  `letter_number` varchar(100) DEFAULT NULL,
  `subject` text DEFAULT NULL,
  `purge_files` tinyint(1) NOT NULL DEFAULT 1,
  `purged_file_paths` text DEFAULT NULL,
  `file_hash_sha256` varchar(64) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `order_number` varchar(100) DEFAULT NULL,
  `destroyed_by` int(11) NOT NULL,
  `destroyed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ddi_doc_id` (`doc_id`),
  KEY `idx_ddi_doc_uid` (`doc_uid`),
  KEY `idx_ddi_batch_id` (`batch_id`),
  KEY `idx_ddi_destroyed_at` (`destroyed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. เพิ่มคอลัมน์ใหม่ในตารางเดิม (Add Missing Columns)
-- ---------------------------------------------------------------------

-- 2.1 ตาราง user_profile (เพิ่ม id_card และ sign_photo หากยังไม่มี)
ALTER TABLE `user_profile`
  ADD COLUMN IF NOT EXISTS `id_card` varchar(20) DEFAULT NULL AFTER `sname`,
  ADD COLUMN IF NOT EXISTS `sign_photo` varchar(255) DEFAULT NULL AFTER `photo`;

-- 2.2 ตาราง docz (เพิ่ม destroyed_at สำหรับติดตามสถานะทำลายเอกสาร)
ALTER TABLE `docz`
  ADD COLUMN IF NOT EXISTS `destroyed_at` datetime DEFAULT NULL AFTER `st`;

-- 2.3 ตาราง doc_file (ตรวจสอบคอลัมน์พื้นฐาน)
ALTER TABLE `doc_file`
  ADD COLUMN IF NOT EXISTS `ext` varchar(255) DEFAULT NULL AFTER `file`;

-- ---------------------------------------------------------------------
-- 3. สถาปัตยกรรม Dual-Key: เพิ่ม UUID v4/v7 และเติมข้อมูลให้แถวเดิม
-- ---------------------------------------------------------------------

-- 3.1 ตาราง user
ALTER TABLE `user` ADD COLUMN IF NOT EXISTS `uid` char(36) DEFAULT NULL AFTER `id`;
UPDATE `user` SET `uid` = UUID() WHERE `uid` IS NULL OR `uid` = '';
ALTER TABLE `user` MODIFY COLUMN `uid` char(36) NOT NULL;

-- 3.2 ตาราง docz
ALTER TABLE `docz` ADD COLUMN IF NOT EXISTS `uid` char(36) DEFAULT NULL AFTER `id`;
UPDATE `docz` SET `uid` = UUID() WHERE `uid` IS NULL OR `uid` = '';
ALTER TABLE `docz` MODIFY COLUMN `uid` char(36) NOT NULL;

-- 3.3 ตาราง doc_file
ALTER TABLE `doc_file` ADD COLUMN IF NOT EXISTS `uid` char(36) DEFAULT NULL AFTER `id`;
UPDATE `doc_file` SET `uid` = UUID() WHERE `uid` IS NULL OR `uid` = '';
ALTER TABLE `doc_file` MODIFY COLUMN `uid` char(36) NOT NULL;

-- 3.4 ตาราง doc_profile
ALTER TABLE `doc_profile` ADD COLUMN IF NOT EXISTS `uid` char(36) DEFAULT NULL AFTER `id`;
UPDATE `doc_profile` SET `uid` = UUID() WHERE `uid` IS NULL OR `uid` = '';
ALTER TABLE `doc_profile` MODIFY COLUMN `uid` char(36) NOT NULL;

-- 3.5 ตาราง doc_cat_name
ALTER TABLE `doc_cat_name` ADD COLUMN IF NOT EXISTS `uid` char(36) DEFAULT NULL AFTER `id`;
UPDATE `doc_cat_name` SET `uid` = UUID() WHERE `uid` IS NULL OR `uid` = '';
ALTER TABLE `doc_cat_name` MODIFY COLUMN `uid` char(36) NOT NULL;

-- ---------------------------------------------------------------------
-- 4. เพิ่ม Indexes เร่งประสิทธิภาพระบบ (Performance Optimization)
-- ---------------------------------------------------------------------

-- ดัชนีตาราง user
ALTER TABLE `user`
  ADD UNIQUE INDEX IF NOT EXISTS `idx_user_uid` (`uid`);

-- ดัชนีตาราง docz
ALTER TABLE `docz`
  ADD UNIQUE INDEX IF NOT EXISTS `idx_docz_uid` (`uid`),
  ADD INDEX IF NOT EXISTS `idx_docz_st` (`st`),
  ADD INDEX IF NOT EXISTS `idx_docz_r_date` (`r_date`),
  ADD INDEX IF NOT EXISTS `idx_docz_r_number` (`r_number`),
  ADD INDEX IF NOT EXISTS `idx_docz_form_number` (`doc_form_number`),
  ADD INDEX IF NOT EXISTS `idx_docz_destroyed_at` (`destroyed_at`);

-- ดัชนีตาราง doc_file
ALTER TABLE `doc_file`
  ADD UNIQUE INDEX IF NOT EXISTS `idx_doc_file_uid` (`uid`),
  ADD INDEX IF NOT EXISTS `idx_doc_file_docz_id` (`docz_id`);

-- ดัชนีตาราง doc_profile
ALTER TABLE `doc_profile`
  ADD UNIQUE INDEX IF NOT EXISTS `idx_doc_profile_uid` (`uid`);

-- ดัชนีตาราง doc_cat_name
ALTER TABLE `doc_cat_name`
  ADD UNIQUE INDEX IF NOT EXISTS `idx_doc_cat_name_uid` (`uid`);

-- ดัชนีตาราง doc_user_read (เร่งความเร็วการนับหนังสือไม่อ่านและทะเบียนหนังสือ)
ALTER TABLE `doc_user_read`
  ADD INDEX IF NOT EXISTS `idx_dur_doc_user` (`doc_id`, `user_id`),
  ADD INDEX IF NOT EXISTS `idx_dur_user_doc` (`user_id`, `doc_id`);

-- ดัชนีตาราง doc_manage (เร่งความเร็วสายการลงนามและคิวรอเกษียน)
ALTER TABLE `doc_manage`
  ADD INDEX IF NOT EXISTS `idx_dm_doc_sort` (`doc_id`, `sort`),
  ADD INDEX IF NOT EXISTS `idx_dm_st_role` (`st`, `role_name_id`);

-- ดัชนีตาราง user_profile
ALTER TABLE `user_profile`
  ADD INDEX IF NOT EXISTS `idx_up_id_card` (`id_card`);

-- ---------------------------------------------------------------------
-- 5. ซิงค์เลขรับหนังสือล่าสุดเข้าตาราง docz_receive_sequences อัตโนมัติ
-- ---------------------------------------------------------------------
-- คำนวณหาเลขรับสูงสุดของแต่ละปีที่มีอยู่ในตาราง docz และบันทึกลง docz_receive_sequences
INSERT INTO `docz_receive_sequences` (`numbering_year`, `last_number`, `updated_at`)
SELECT 
  YEAR(COALESCE(r_date, doc_date, created, NOW())) AS y,
  MAX(CAST(REGEXP_REPLACE(r_number, '[^0-9]', '') AS UNSIGNED)) AS max_num,
  NOW()
FROM `docz`
WHERE r_number IS NOT NULL AND r_number != ''
GROUP BY y
ON DUPLICATE KEY UPDATE 
  `last_number` = GREATEST(`docz_receive_sequences`.`last_number`, VALUES(`last_number`)),
  `updated_at` = NOW();

-- ตรวจสอบผลลัพธ์
SELECT * FROM `docz_receive_sequences`;
