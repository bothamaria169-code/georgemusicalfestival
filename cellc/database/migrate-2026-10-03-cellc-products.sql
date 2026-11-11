-- Cell C product tick-boxes (3 Oct 2026). Replaces the network question on the form (kept in Settings, switched off).
SET NAMES utf8mb4;
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS cellc_products VARCHAR(160) NULL AFTER age_group;
REPLACE INTO settings (skey, svalue) VALUES ('product_question', 'Cell C Products — Select all that apply');
REPLACE INTO settings (skey, svalue) VALUES ('product_options', 'Prepaid\nFibre\nPostpaid\nOther/Not a Cell C Customer');
UPDATE settings SET svalue = '' WHERE skey = 'travel_options';
