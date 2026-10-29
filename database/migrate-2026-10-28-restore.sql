-- Entry restoration (28 Oct 2026): the live database was lost with the old server.
-- Share links already sent out keep working; these tables let entrants reclaim them.

-- Opens of share links whose owner hasn't restored their entry yet. Moved into
-- referral_clicks (origin 'orphan') once the claim is approved.
CREATE TABLE IF NOT EXISTS orphan_clicks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_code    VARCHAR(12)  NOT NULL,
  ip_hash     CHAR(64)     NULL,
  visitor_id  CHAR(32)     NOT NULL,
  user_agent  VARCHAR(255) NULL,
  clicked_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_visitor (ref_code, visitor_id),
  KEY ix_code (ref_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra share codes that lead to an entrant: an old code restored onto an entry that
-- already had one, or the temporary code a restored entrant held before approval.
CREATE TABLE IF NOT EXISTS ref_aliases (
  ref_code    VARCHAR(12)  NOT NULL PRIMARY KEY,
  entrant_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL,
  KEY ix_entrant (entrant_id),
  CONSTRAINT fk_alias_entrant FOREIGN KEY (entrant_id) REFERENCES entrants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per "Restore my entry" claim.
CREATE TABLE IF NOT EXISTS restore_requests (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrant_id     INT UNSIGNED NOT NULL,
  ref_code       VARCHAR(12)  NOT NULL,           -- the old share code being claimed
  new_entrant    TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = entrant row was created by this restore
  device_match   TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = this phone's gmf_own cookie holds the code
  claimed_opens  INT UNSIGNED NULL,               -- what the entrant says their score was
  evidence       TEXT         NULL,               -- entrant's note: where the link came from, screenshots sent, etc.
  status         ENUM('email','review','approved','rejected') NOT NULL DEFAULT 'email',
  admin_note     VARCHAR(255) NULL,
  reviewed_by    INT UNSIGNED NULL,
  reviewed_at    DATETIME     NULL,
  ip_hash        CHAR(64)     NULL,
  created_at     DATETIME     NOT NULL,
  KEY ix_status (status),
  KEY ix_code (ref_code),
  KEY ix_entrant (entrant_id),
  CONSTRAINT fk_restore_entrant FOREIGN KEY (entrant_id) REFERENCES entrants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link opens restored from evidence (archive snapshots, screenshots, draw records).
-- Each adjustment is backed by that many referral_clicks rows with origin 'restored'.
CREATE TABLE IF NOT EXISTS score_adjustments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrant_id  INT UNSIGNED NOT NULL,
  opens       INT UNSIGNED NOT NULL,
  as_at       DATETIME     NOT NULL,              -- when the evidence shows this score
  reason      VARCHAR(255) NOT NULL,
  evidence    TEXT         NULL,                  -- links / file names the attorney can check
  admin_id    INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL,
  KEY ix_entrant (entrant_id),
  CONSTRAINT fk_adjust_entrant FOREIGN KEY (entrant_id) REFERENCES entrants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Where each counted open came from: live | orphan (captured before the claim) | restored (from evidence)
ALTER TABLE referral_clicks ADD COLUMN IF NOT EXISTS origin VARCHAR(10) NOT NULL DEFAULT 'live' AFTER counted;
ALTER TABLE referral_clicks ADD COLUMN IF NOT EXISTS adjustment_id INT UNSIGNED NULL AFTER origin;
ALTER TABLE referral_clicks ADD KEY IF NOT EXISTS ix_adjustment (adjustment_id);

ALTER TABLE otps MODIFY purpose VARCHAR(10) NOT NULL;   -- entry | login | restore

INSERT IGNORE INTO settings (skey, svalue) VALUES ('restore_auto_approve_device', '1');
