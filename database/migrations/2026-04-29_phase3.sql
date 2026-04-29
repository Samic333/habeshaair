-- HabeshAir — Phase 3: Reply ingestion (IMAP) + Quote management

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- rfq_replies — raw inbound emails routed to a dispatch
-- (one row per airline reply that the IMAP cron parses)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfq_replies (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dispatch_id     INT UNSIGNED NOT NULL,
  from_email      VARCHAR(190) NOT NULL,
  from_name       VARCHAR(190) NULL,
  subject         VARCHAR(255) NULL,
  body_text       MEDIUMTEXT NULL,
  body_html       MEDIUMTEXT NULL,
  has_attachments TINYINT(1) NOT NULL DEFAULT 0,
  attachments     JSON NULL,
  imap_uid        INT UNSIGNED NULL,
  received_at     DATETIME NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dispatch (dispatch_id),
  KEY idx_received (received_at),
  CONSTRAINT fk_reply_dispatch FOREIGN KEY (dispatch_id)
    REFERENCES rfq_dispatches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- quotes — structured price record for an RFQ dispatch.
-- Computed columns keep margin maths in the DB so admin can't
-- accidentally save a client_price that disagrees with operator
-- + markup + service fee.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS quotes (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id          INT UNSIGNED NOT NULL,
  dispatch_id         INT UNSIGNED NULL,
  airline_id          INT UNSIGNED NOT NULL,
  operator_price      DECIMAL(12,2) NOT NULL,
  currency            CHAR(3) NOT NULL DEFAULT 'USD',
  markup_pct          DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  markup_amount       DECIMAL(12,2) GENERATED ALWAYS AS (operator_price * markup_pct / 100) STORED,
  client_price        DECIMAL(12,2) GENERATED ALWAYS AS (operator_price + (operator_price * markup_pct / 100)) STORED,
  service_fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_to_client     DECIMAL(12,2) GENERATED ALWAYS AS (operator_price + (operator_price * markup_pct / 100) + service_fee) STORED,
  validity_until      DATE NULL,
  client_facing_text  TEXT NULL,
  status              ENUM('Draft','Sent','Accepted','Rejected','Expired','Won','Lost')
                      NOT NULL DEFAULT 'Draft',
  sent_at             DATETIME NULL,
  accepted_at         DATETIME NULL,
  notes               TEXT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_request (request_id),
  KEY idx_status (status),
  CONSTRAINT fk_quote_request FOREIGN KEY (request_id)
    REFERENCES charter_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_quote_airline FOREIGN KEY (airline_id)
    REFERENCES airlines(id),
  CONSTRAINT fk_quote_dispatch FOREIGN KEY (dispatch_id)
    REFERENCES rfq_dispatches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
