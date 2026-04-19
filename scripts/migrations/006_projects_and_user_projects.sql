-- Migration 006: Projects registry and user-project assignments
--
-- Creates the project routing tables expected by select_project.php,
-- project_switch_handler.php, and the per-module header files (header_ghom.php,
-- header_pardis.php). The original cPanel database dump did not include these
-- tables, so every page that calls getCommonDBConnection() + SELECT ... FROM projects
-- raised a PDOException and users saw "خطا در بارگذاری اطلاعات پروژه".
--
-- Run against the common database (alumglas_common).

-- 1. Projects registry -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `project_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `project_name`     VARCHAR(255) NOT NULL,
  `project_code`     VARCHAR(50)  NOT NULL,
  `config_key`       VARCHAR(50)  NOT NULL COMMENT 'Key used by getProjectDBConnection() — ghom | pardis',
  `ro_config_key`    VARCHAR(50)  DEFAULT NULL COMMENT 'Optional read-only connection key',
  `base_path`        VARCHAR(100) NOT NULL COMMENT 'URL prefix, e.g. /ghom or /pardis',
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_project_code` (`project_code`),
  UNIQUE KEY `uniq_config_key`   (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User ↔ project assignments ---------------------------------------------
CREATE TABLE IF NOT EXISTS `user_projects` (
  `user_id`     INT NOT NULL,
  `project_id`  INT NOT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `project_id`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `fk_user_projects_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`project_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. users.default_project_id (guarded add) ---------------------------------
-- Adds the column only if it doesn't exist so the migration is idempotent.
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'users'
    AND column_name  = 'default_project_id'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN default_project_id INT NULL AFTER is_admin',
  'SELECT "default_project_id already exists" AS note'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Seed the two known projects --------------------------------------------
INSERT INTO `projects` (`project_name`, `project_code`, `config_key`, `ro_config_key`, `base_path`, `is_active`)
VALUES
  ('قم',    'GHM', 'ghom',   'ghom',   '/ghom',   1),
  ('پردیس', 'PRD', 'pardis', 'pardis', '/pardis', 1)
ON DUPLICATE KEY UPDATE
  project_name  = VALUES(project_name),
  config_key    = VALUES(config_key),
  ro_config_key = VALUES(ro_config_key),
  base_path     = VALUES(base_path),
  is_active     = VALUES(is_active);

-- 5. Grant every active admin access to every active project ---------------
INSERT IGNORE INTO `user_projects` (`user_id`, `project_id`)
SELECT u.id, p.project_id
FROM users u
CROSS JOIN projects p
WHERE u.role = 'admin'
  AND u.is_active = 1
  AND p.is_active = 1;
