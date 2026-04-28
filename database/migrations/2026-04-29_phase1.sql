-- HabeshAir — Phase 1: Analytics + Airlines Directory
-- Apply once via cPanel phpMyAdmin (paste & run) OR:
--   mysql -u <user> -p <database> < 2026-04-29_phase1.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- page_views — every public-page visit (excluding bots, /admin, /cron, /assets)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_views (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path          VARCHAR(255) NOT NULL,
  referrer      VARCHAR(500) NULL,
  ip_address    VARBINARY(16) NULL,
  visitor_hash  CHAR(16) NULL,
  country_code  CHAR(2)  NULL,
  country_name  VARCHAR(80) NULL,
  city          VARCHAR(120) NULL,
  user_agent    VARCHAR(255) NULL,
  device_type   ENUM('desktop','mobile','tablet','bot','other') NOT NULL DEFAULT 'other',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_path (path),
  KEY idx_country (country_code),
  KEY idx_created (created_at),
  KEY idx_visitor_day (visitor_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- visitor_geo_cache — cache geo lookups by IP (avoid hammering ipapi.co)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS visitor_geo_cache (
  ip_address    VARBINARY(16) NOT NULL,
  country_code  CHAR(2)  NULL,
  country_name  VARCHAR(80) NULL,
  city          VARCHAR(120) NULL,
  cached_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ip_address),
  KEY idx_cached (cached_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- airlines — synced from a Google Sheet (CSV publish)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS airlines (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sheet_row_id      VARCHAR(40)  NULL,
  name              VARCHAR(160) NOT NULL,
  iata_code         VARCHAR(4)   NULL,
  icao_code         VARCHAR(5)   NULL,
  base_country      VARCHAR(80)  NULL,
  contact_email     VARCHAR(190) NULL,
  contact_name      VARCHAR(160) NULL,
  phone             VARCHAR(40)  NULL,
  whatsapp          VARCHAR(40)  NULL,
  website           VARCHAR(255) NULL,
  fleet_types       JSON NULL,
  regions_served    JSON NULL,
  service_types     JSON NULL,
  capacity_pax_max  SMALLINT UNSIGNED NULL,
  capacity_kg_max   INT UNSIGNED NULL,
  rating            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  notes             TEXT NULL,
  active            TINYINT(1) NOT NULL DEFAULT 1,
  is_new            TINYINT(1) NOT NULL DEFAULT 1,
  synced_at         DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_sheet_row (sheet_row_id),
  KEY idx_active (active),
  KEY idx_country (base_country),
  KEY idx_synced (synced_at),
  KEY idx_is_new (is_new)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
