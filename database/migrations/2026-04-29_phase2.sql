-- HabeshAir — Phase 2: Request → Airline matching + outbound RFQs
-- Apply once via cPanel phpMyAdmin (paste & run).

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Augment charter_requests status enum
-- (adds RFQ-Sent, RFQ-Received, Flown between existing values)
-- -----------------------------------------------------------
ALTER TABLE charter_requests
  MODIFY COLUMN status ENUM(
    'New','Reviewing','RFQ-Sent','RFQ-Received',
    'Quoted','Waiting','Confirmed','Flown','Cancelled','Closed'
  ) NOT NULL DEFAULT 'New';

-- -----------------------------------------------------------
-- rfq_dispatches — one row per outbound RFQ (request × airline)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfq_dispatches (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id      INT UNSIGNED NOT NULL,
  airline_id      INT UNSIGNED NOT NULL,
  reply_token     CHAR(16) NOT NULL,
  message_id      VARCHAR(255) NULL,
  to_email        VARCHAR(190) NOT NULL,
  subject         VARCHAR(255) NOT NULL,
  body_text       TEXT NOT NULL,
  status          ENUM('Sent','Replied','Quoted','Declined','No-Response','Cancelled')
                  NOT NULL DEFAULT 'Sent',
  sent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reply_at        DATETIME NULL,
  reply_snippet   VARCHAR(500) NULL,
  quoted_amount   DECIMAL(12,2) NULL,
  quoted_currency CHAR(3) NULL,
  internal_notes  TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (reply_token),
  KEY idx_request (request_id),
  KEY idx_airline (airline_id),
  KEY idx_status (status),
  KEY idx_sent (sent_at),
  CONSTRAINT fk_rfq_request FOREIGN KEY (request_id)
    REFERENCES charter_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_rfq_airline FOREIGN KEY (airline_id)
    REFERENCES airlines(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
