-- 5 Oct 2026: one professional prize line — "Samsung Galaxy A56 + accessories worth R10 000".
-- The breakdown (smartphone R7 500; accessories R2 500) stays in the terms only.
SET NAMES utf8mb4;
UPDATE settings SET svalue = REPLACE(svalue, 'giving away a Samsung Galaxy A56 (R7 500) and accessories (R2 500), worth R10 000 altogether.', 'giving away a Samsung Galaxy A56 + accessories worth R10 000.') WHERE skey = 'hero_text';
UPDATE settings SET svalue = REPLACE(svalue, 'win a Samsung Galaxy A56 and accessories worth R10 000.', 'win a Samsung Galaxy A56 + accessories worth R10 000.') WHERE skey = 'announcement';
UPDATE settings SET svalue = REPLACE(svalue, 'one Samsung Galaxy A56 smartphone (value R7 500) and an accessory bundle (value R2 500), with a combined value of R10 000.', 'one Samsung Galaxy A56 smartphone and an accessory bundle, with a total retail value of R10 000 (smartphone R7 500; accessories R2 500).') WHERE skey = 'terms_html';
UPDATE draws SET prize_description = 'Samsung Galaxy A56 smartphone\nAccessory bundle\nPhone + accessories worth R10 000\nCollect at a Cell C store — bring your ID' WHERE draw_key = 'final';
