-- Migration 004: Real-time Chat System
-- Phase 4A: WebSocket chat with group support
--
-- Run on the common database (alumglas_common) — chat is cross-project.

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('direct', 'group', 'channel') NOT NULL DEFAULT 'direct',
  `name` VARCHAR(100) DEFAULT NULL,
  `avatar_path` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (type),
  INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_members` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('admin', 'member') DEFAULT 'member',
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` DATETIME DEFAULT NULL,
  `is_muted` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (conversation_id, user_id),
  INDEX idx_user (user_id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhance existing messages table (guarded so it's idempotent)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'conversation_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE `messages` ADD COLUMN `conversation_id` INT UNSIGNED DEFAULT NULL AFTER `id`',
  'SELECT "messages.conversation_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'reply_to_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE `messages` ADD COLUMN `reply_to_id` INT UNSIGNED DEFAULT NULL',
  'SELECT "messages.reply_to_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'reactions');
SET @sql := IF(@col = 0,
  'ALTER TABLE `messages` ADD COLUMN `reactions` JSON DEFAULT NULL',
  'SELECT "messages.reactions already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'idx_conversation');
SET @sql := IF(@idx = 0,
  'ALTER TABLE `messages` ADD INDEX idx_conversation (conversation_id, timestamp)',
  'SELECT "idx_conversation already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'idx_reply');
SET @sql := IF(@idx = 0,
  'ALTER TABLE `messages` ADD INDEX idx_reply (reply_to_id)',
  'SELECT "idx_reply already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- User presence tracking
CREATE TABLE IF NOT EXISTS `user_presence` (
  `user_id` INT PRIMARY KEY,
  `status` ENUM('online', 'away', 'offline') DEFAULT 'offline',
  `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `socket_id` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
