-- GMF × ACSA — FLY FOR TWO platform schema (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS entrants (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name         VARCHAR(120) NOT NULL,
  email             VARCHAR(190) NULL,
  email_status      VARCHAR(16)  NULL,                -- bounced | invalid | send_failed
  email_issue       VARCHAR(255) NULL,                -- plain-language reason shown in the admin
  email_issue_at    DATETIME     NULL,
  phone             VARCHAR(20)  NOT NULL,            -- normalised: 27XXXXXXXXX
  town              VARCHAR(80)  NULL,
  fly_frequency     VARCHAR(60)  NULL,                -- travel-profile answer
  age_group         VARCHAR(12)  NULL,                -- 18–24, 25–34 …
  consent_terms     TINYINT(1)   NOT NULL DEFAULT 0,
  consent_popia     TINYINT(1)   NOT NULL DEFAULT 0,  -- explicit POPIA consent (ACSA wording)
  consent_marketing TINYINT(1)   NOT NULL DEFAULT 0,
  consent_at        DATETIME     NULL,
  status            ENUM('pending','verified','disqualified') NOT NULL DEFAULT 'pending',
  verified_at       DATETIME     NULL,
  verified_via      VARCHAR(20)  NULL,                -- email | ussd
  ref_code          VARCHAR(12)  NOT NULL,
  referred_by       INT UNSIGNED NULL,
  channel           VARCHAR(30)  NOT NULL DEFAULT 'web',   -- web | qr_ground | qr_event | ussd | acsa_embed | referral
  activation_point  VARCHAR(120) NULL,
  source_town       VARCHAR(80)  NULL,
  time_slot         VARCHAR(40)  NULL,
  utm_source        VARCHAR(80)  NULL,
  utm_medium        VARCHAR(80)  NULL,
  utm_campaign      VARCHAR(80)  NULL,
  device_fp         CHAR(64)     NULL,
  ip_hash           CHAR(64)     NULL,
  user_agent        VARCHAR(255) NULL,
  notes             VARCHAR(255) NULL,
  photo             VARCHAR(255) NULL,                -- uploads/entrants/… shown on the winner reveal
  created_at        DATETIME     NOT NULL,
  last_login_at     DATETIME     NULL,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_phone (phone),
  UNIQUE KEY uq_ref (ref_code),
  KEY ix_status (status),
  KEY ix_channel (channel),
  KEY ix_created (created_at),
  KEY ix_referred (referred_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per draw ticket. kind: base | share_bonus | referral_bonus
CREATE TABLE IF NOT EXISTS entries (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrant_id      INT UNSIGNED NOT NULL,
  kind            VARCHAR(20)  NOT NULL,
  source_entrant  INT UNSIGNED NOT NULL DEFAULT 0,  -- referred entrant for referral_bonus, else 0
  created_at      DATETIME     NOT NULL,
  KEY ix_entrant (entrant_id),
  KEY ix_created (created_at),
  UNIQUE KEY uq_once (entrant_id, kind, source_entrant),
  CONSTRAINT fk_entries_entrant FOREIGN KEY (entrant_id) REFERENCES entrants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otps (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrant_id   INT UNSIGNED NOT NULL,
  purpose      VARCHAR(10)  NOT NULL,       -- entry | login
  code_hash    CHAR(64)     NOT NULL,
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sent_at      DATETIME     NOT NULL,
  expires_at   DATETIME     NOT NULL,
  consumed_at  DATETIME     NULL,
  KEY ix_entrant (entrant_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_clicks (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_id  INT UNSIGNED NOT NULL,
  ip_hash      CHAR(64)     NULL,
  visitor_id   CHAR(32)     NULL,
  user_agent   VARCHAR(255) NULL,
  counted      TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = this click earned the share bonus
  clicked_at   DATETIME     NOT NULL,
  KEY ix_referrer (referrer_id),
  UNIQUE KEY uq_visitor (referrer_id, visitor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_events (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action      VARCHAR(40) NOT NULL,
  subject     CHAR(64)    NOT NULL,
  created_at  DATETIME    NOT NULL,
  KEY ix_lookup (action, subject, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS draws (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  draw_key          VARCHAR(30)  NOT NULL,
  title             VARCHAR(120) NOT NULL,
  prize_description TEXT         NULL,
  scheduled_for     DATE         NULL,
  status            ENUM('scheduled','drawn','confirmed','published') NOT NULL DEFAULT 'scheduled',
  created_at        DATETIME     NOT NULL,
  UNIQUE KEY uq_key (draw_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log of every draw and redraw — the certification record.
CREATE TABLE IF NOT EXISTS draw_runs (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  draw_id            INT UNSIGNED NOT NULL,
  run_no             INT UNSIGNED NOT NULL,
  is_redraw          TINYINT(1)   NOT NULL DEFAULT 0,
  redraw_reason      VARCHAR(255) NULL,
  cutoff_at          DATETIME     NOT NULL,
  pool_entrants      INT UNSIGNED NOT NULL,
  pool_entries       INT UNSIGNED NOT NULL,
  pool_hash          CHAR(64)     NOT NULL,
  random_proof       CHAR(64)     NOT NULL,
  selected_index     INT UNSIGNED NOT NULL,
  selected_entry_id  INT UNSIGNED NOT NULL,
  selected_entrant_id INT UNSIGNED NOT NULL,
  outcome            ENUM('selected','confirmed','disqualified') NOT NULL DEFAULT 'selected',
  outcome_note       VARCHAR(255) NULL,
  witness            VARCHAR(160) NULL,
  triggered_by       INT UNSIGNED NULL,
  created_at         DATETIME     NOT NULL,
  KEY ix_draw (draw_id),
  CONSTRAINT fk_runs_draw FOREIGN KEY (draw_id) REFERENCES draws(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
  created_at     DATETIME     NOT NULL,
  last_login_at  DATETIME     NULL,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey   VARCHAR(60) PRIMARY KEY,
  svalue MEDIUMTEXT  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Website media: gallery photos, videos and line-up cards.
CREATE TABLE IF NOT EXISTS media (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind         ENUM('image','video','artist') NOT NULL DEFAULT 'image',
  title        VARCHAR(160) NULL,
  caption      VARCHAR(255) NULL,
  src          VARCHAR(255) NOT NULL,      -- image path, or video URL (YouTube / Vimeo / .mp4)
  poster       VARCHAR(255) NULL,          -- video poster image
  album        VARCHAR(60)  NOT NULL DEFAULT 'Festival',
  featured     TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL,
  KEY ix_kind (kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscribers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(20)  NULL,
  source      VARCHAR(40)  NOT NULL DEFAULT 'website',
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,
  detail      TEXT         NULL,
  ip_hash     CHAR(64)     NULL,
  created_at  DATETIME     NOT NULL,
  KEY ix_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
