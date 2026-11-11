-- Email health (26 Sep 2026): flag entrants whose code emails bounce or cannot be sent.
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS email_status VARCHAR(16) NULL AFTER email;
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS email_issue VARCHAR(255) NULL AFTER email_status;
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS email_issue_at DATETIME NULL AFTER email_issue;
