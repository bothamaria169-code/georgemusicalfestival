-- Carried-over leaderboard (30 Oct 2026): last known public standings from before the server
-- change, shown on the public leaderboard until each entrant's live entry is back.
-- Display only: these rows never enter a draw. Remove a row once its entrant is restored.
CREATE TABLE IF NOT EXISTS leaderboard_carryover (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,              -- as shown publicly: first name + surname initial
  town        VARCHAR(80)  NULL,
  opens       INT UNSIGNED NULL,                  -- NULL only for the hidden leader
  is_leader   TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = the hidden "Mystery leader" row
  ref_code    VARCHAR(12)  NULL,                  -- when known: hides this row as soon as that code is restored
  as_at       DATETIME     NULL,
  source      VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
