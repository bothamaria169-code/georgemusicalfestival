-- 5 Oct 2026: show the prize split — Galaxy A56 R7 500 + accessories R2 500 = R10 000.
SET NAMES utf8mb4;
UPDATE draws SET prize_description = 'Samsung Galaxy A56 smartphone — worth R7 500\nAccessory bundle — worth R2 500\nTotal value R10 000\nCollect at a Cell C store — bring your ID' WHERE draw_key = 'final';
UPDATE settings SET svalue = REPLACE(svalue, 'one Samsung Galaxy A56 smartphone and an accessory bundle, with a combined value of R10 000.', 'one Samsung Galaxy A56 smartphone (value R7 500) and an accessory bundle (value R2 500), with a combined value of R10 000.') WHERE skey = 'terms_html';
UPDATE settings SET svalue = REPLACE(svalue, 'giving away a Samsung Galaxy A56 and accessories, worth R10 000 altogether.', 'giving away a Samsung Galaxy A56 (R7 500) and accessories (R2 500), worth R10 000 altogether.') WHERE skey = 'hero_text';
