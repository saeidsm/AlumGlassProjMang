-- Migration 005: Content-Addressable File Storage (Deduplication)
-- Phase 4A Addendum: central file_store + file_references
--
-- Run on the common database (alumglas_common) — shared across modules.

CREATE TABLE IF NOT EXISTS `file_store` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `hash` CHAR(64) NOT NULL,
  `disk_path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL,
  `ref_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_referenced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_hash (hash),
  INDEX idx_mime (mime_type),
  INDEX idx_ref (ref_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file_references` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `file_store_id` INT UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `uploaded_by` INT NOT NULL,
  `display_name` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_file (file_store_id),
  INDEX idx_entity (module, entity_type, entity_id),
  INDEX idx_user (uploaded_by),
  FOREIGN KEY (file_store_id) REFERENCES file_store(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
