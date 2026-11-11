-- Entry form additions (25 Sep 2026): age group and a separate, explicit POPIA consent in ACSA's wording.
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS age_group VARCHAR(12) NULL AFTER fly_frequency;
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS consent_popia TINYINT(1) NOT NULL DEFAULT 0 AFTER consent_terms;

-- Privacy notice (if already saved in settings): mention the new field and the POPIA consent.
UPDATE settings SET svalue = REPLACE(svalue, 'town, your answer to the travel question, and your consent choices.', 'town, age group, your answer to the travel question, and your consent choices (including your POPIA consent to ACSA).') WHERE skey = 'privacy_html';
UPDATE settings SET svalue = REPLACE(svalue, 'mostly as totals by channel, town and date', 'mostly as totals by channel, town, age group and date') WHERE skey = 'privacy_html';
