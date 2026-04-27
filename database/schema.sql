-- HabeshAir — initial schema
-- Import via cPanel phpMyAdmin or MySQL CLI:
--   mysql -u <user> -p <database> < schema.sql
-- After import, replace the placeholder admin password hash. See database/README.md.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- charter_requests
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS charter_requests (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_code       VARCHAR(32)  NOT NULL,
  service_type         ENUM('VIP','Cargo','Humanitarian','Emergency-Medevac','Group-Event') NOT NULL,
  trip_type            ENUM('One-way','Round-trip','Multi-leg') NOT NULL,
  origin               VARCHAR(160) NOT NULL,
  destination          VARCHAR(160) NOT NULL,
  travel_date          DATE NULL,
  return_date          DATE NULL,
  time_pref            ENUM('Any','Morning','Afternoon','Evening') NOT NULL DEFAULT 'Any',
  passengers           SMALLINT UNSIGNED NULL,
  approx_weight_kg     INT UNSIGNED NULL,
  cargo_type           ENUM('General','Dangerous Goods','Live Animals','Perishable') NULL,
  urgency_level        ENUM('Flexible','72h','24h','Emergency') NOT NULL DEFAULT 'Flexible',
  budget_range         VARCHAR(80)  NULL,
  special_requirements TEXT NULL,
  full_name            VARCHAR(160) NOT NULL,
  email                VARCHAR(190) NOT NULL,
  phone                VARCHAR(40)  NOT NULL,
  company              VARCHAR(160) NULL,
  contact_method       ENUM('WhatsApp','Email','Phone') NOT NULL DEFAULT 'WhatsApp',
  consent              TINYINT(1)   NOT NULL DEFAULT 0,
  status               ENUM('New','Reviewing','Quoted','Waiting','Confirmed','Cancelled','Closed') NOT NULL DEFAULT 'New',
  is_urgent            TINYINT(1)   NOT NULL DEFAULT 0,
  internal_notes       TEXT NULL,
  ip_address           VARBINARY(16) NULL,
  user_agent           VARCHAR(255) NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_reference (reference_code),
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_email (email),
  KEY idx_urgent_status (is_urgent, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- contact_messages
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name   VARCHAR(160) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(40)  NULL,
  subject     VARCHAR(200) NOT NULL,
  message     TEXT NOT NULL,
  status      ENUM('New','Read','Replied','Archived') NOT NULL DEFAULT 'New',
  ip_address  VARBINARY(16) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- admin_users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(80)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(120) NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_username (username),
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed an admin row. The password_hash below MUST be replaced with a real bcrypt
-- hash generated locally with:
--   php -r "echo password_hash('YourTempPass', PASSWORD_BCRYPT, ['cost'=>12]);"
-- Then run:
--   UPDATE admin_users SET password_hash='<paste hash>' WHERE username='admin';
INSERT INTO admin_users (username, email, password_hash, display_name, is_active)
VALUES ('admin', 'info@habeshair.com',
        '$2y$12$REPLACE_WITH_REAL_BCRYPT_HASH_GENERATED_BY_PASSWORD_HASH',
        'HabeshAir Admin', 1)
ON DUPLICATE KEY UPDATE username = username;

SET FOREIGN_KEY_CHECKS = 1;
