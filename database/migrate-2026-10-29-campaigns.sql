-- Bulk email (29 Oct 2026): campaigns sent in batches from Admin → Email campaigns.

CREATE TABLE IF NOT EXISTS email_campaigns (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  subject     VARCHAR(190) NOT NULL,
  heading     VARCHAR(190) NOT NULL,
  body_html   MEDIUMTEXT   NOT NULL,           -- merge fields: {first_name} {full_name} {share_link} {restore_link} {entry_link}
  status      ENUM('draft','sending','paused','done') NOT NULL DEFAULT 'draft',
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL,
  started_at  DATETIME     NULL,
  finished_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id  INT UNSIGNED NOT NULL,
  email        VARCHAR(190) NOT NULL,
  full_name    VARCHAR(120) NULL,
  ref_code     VARCHAR(12)  NULL,
  status       ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  error        VARCHAR(255) NULL,
  sent_at      DATETIME     NULL,
  UNIQUE KEY uq_once (campaign_id, email),
  KEY ix_status (campaign_id, status),
  CONSTRAINT fk_queue_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who used the unsubscribe link. Never sent another campaign email.
CREATE TABLE IF NOT EXISTS email_suppressions (
  email       VARCHAR(190) NOT NULL PRIMARY KEY,
  reason      VARCHAR(40)  NOT NULL DEFAULT 'unsubscribed',
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
