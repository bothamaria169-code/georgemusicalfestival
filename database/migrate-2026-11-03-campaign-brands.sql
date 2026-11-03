-- Email campaigns per brand (3 Nov 2026): FLY FOR TWO (ACSA) and WIN A SAMSUNG (Cell C) kept apart,
-- saved templates, and where each recipient came from. Applied automatically by campaign_schema();
-- this file is the record for setting up a database by hand.
ALTER TABLE email_campaigns ADD COLUMN brand VARCHAR(10) NOT NULL DEFAULT 'acsa' AFTER id;
ALTER TABLE email_queue ADD COLUMN source VARCHAR(120) NULL AFTER ref_code;   -- file name, "pasted" or the audience

CREATE TABLE IF NOT EXISTS email_templates (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand       VARCHAR(10)  NOT NULL DEFAULT 'acsa',
  name        VARCHAR(120) NOT NULL,
  subject     VARCHAR(190) NOT NULL,
  heading     VARCHAR(190) NOT NULL,
  body_html   MEDIUMTEXT   NOT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (skey, svalue) VALUES ('campaigns_schema', '2') ON DUPLICATE KEY UPDATE svalue = '2';
