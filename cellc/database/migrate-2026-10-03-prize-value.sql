-- Vic, 3 Oct 2026: the Galaxy A56 and accessories are worth R10 000 altogether (not accessories alone).
SET NAMES utf8mb4;
UPDATE settings SET svalue = 'R10 000' WHERE skey = 'prize_value';
UPDATE settings SET svalue = REPLACE(svalue, 'giving away a Samsung Galaxy A56, bundled with accessories to the value of R10,000.', 'giving away a Samsung Galaxy A56 and accessories, worth R10 000 altogether.') WHERE skey = 'hero_text';
UPDATE settings SET svalue = REPLACE(svalue, 'win a Samsung Galaxy A56 with accessories to the value of R10,000.', 'win a Samsung Galaxy A56 and accessories worth R10 000.') WHERE skey = 'announcement';
UPDATE settings SET svalue = REPLACE(svalue, 'one Samsung Galaxy A56 smartphone, bundled with accessories to the value of R10,000.', 'one Samsung Galaxy A56 smartphone and an accessory bundle, with a combined value of R10 000.') WHERE skey = 'terms_html';
UPDATE draws SET prize_description = REPLACE(prize_description, 'Samsung Galaxy A56 smartphone\nAccessories to the value of R10,000', 'Samsung Galaxy A56 smartphone + accessories\nWorth R10 000 altogether') WHERE draw_key = 'final';
