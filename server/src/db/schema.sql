-- DOCZ fresh-install schema. Structure only; no user or document records.

SET NAMES utf8mb4;

CREATE TABLE `doc_cat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) DEFAULT NULL,
  `doc_cat_name_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_cat_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_doc_cat_name_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_destruction_batch` (
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `batch_number` (`batch_number`),
  KEY `idx_ddb_uid` (`uid`),
  KEY `idx_ddb_year` (`target_year`),
  KEY `idx_ddb_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_destruction_item` (
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
  `destroyed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ddi_doc_id` (`doc_id`),
  KEY `idx_ddi_doc_uid` (`doc_uid`),
  KEY `idx_ddi_batch_id` (`batch_id`),
  KEY `idx_ddi_destroyed_at` (`destroyed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) NOT NULL,
  `docz_id` int(11) DEFAULT NULL,
  `doc_form` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file` varchar(255) DEFAULT NULL,
  `ext` varchar(255) DEFAULT NULL,
  `user_id_create` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_doc_file_uid` (`uid`),
  KEY `idx_doc_file_docz_id` (`docz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `doc_manage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_form` varchar(255) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `role_name_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sort` int(11) DEFAULT NULL,
  `ty` text DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `st` int(11) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_dm_doc_sort` (`doc_id`,`sort`),
  KEY `idx_dm_st_role` (`st`,`role_name_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `doc_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_doc_profile_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `doc_profile_sub` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `doc_profile_id` varchar(255) NOT NULL,
  `role_name_id` int(11) NOT NULL,
  `sort` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `doc_user_pin` (
  `user_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `pinned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_user_read` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `doc_id` int(11) DEFAULT NULL,
  `check` int(11) DEFAULT NULL,
  `pin` tinyint(1) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_dur_doc_user` (`doc_id`,`user_id`),
  KEY `idx_dur_user_doc` (`user_id`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `docz` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) NOT NULL,
  `r_number` varchar(255) NOT NULL,
  `r_date` datetime DEFAULT NULL,
  `doc_speed` varchar(255) DEFAULT NULL,
  `doc_form_number` varchar(255) DEFAULT NULL,
  `doc_date` datetime DEFAULT NULL,
  `doc_form` varchar(255) DEFAULT NULL,
  `doc_to` varchar(255) NOT NULL,
  `name` varchar(1000) NOT NULL,
  `file` varchar(255) DEFAULT NULL,
  `user_create` int(11) DEFAULT NULL,
  `st` int(11) DEFAULT 1,
  `destroyed_at` datetime DEFAULT NULL,
  `start` datetime DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_docz_uid` (`uid`),
  KEY `idx_docz_st` (`st`),
  KEY `idx_docz_r_date` (`r_date`),
  KEY `idx_docz_r_number` (`r_number`),
  KEY `idx_docz_form_number` (`doc_form_number`),
  KEY `idx_docz_destroyed_at` (`destroyed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `docz_receive_sequences` (
  `numbering_year` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`numbering_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profile_pfname` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_name_id` int(11) NOT NULL,
  `name_dep1` varchar(255) DEFAULT NULL,
  `name_dep2` varchar(255) DEFAULT NULL,
  `name_dep3` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `role_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sort` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `role_power` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(36) NOT NULL,
  `username` varchar(255) NOT NULL,
  `auth_key` varchar(32) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` smallint(6) NOT NULL DEFAULT 10,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `username` (`username`) USING BTREE,
  UNIQUE KEY `idx_user_uid` (`uid`),
  UNIQUE KEY `password_reset_token` (`password_reset_token`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `user_dep_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `user_group_work` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `user_profile` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `pfname` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `sname` varchar(255) DEFAULT NULL,
  `id_card` varchar(20) DEFAULT NULL,
  `dep_name` varchar(255) DEFAULT NULL,
  `group_work` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `line_id` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `sign_photo` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`) USING BTREE,
  KEY `idx_up_id_card` (`id_card`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT INTO role_name (id, name, sort, status) VALUES (1, 'สารบรรณ', 1, 1);

INSERT INTO profile_pfname (name, sort) VALUES ('นาย', 1), ('นาง', 2), ('นางสาว', 3);
