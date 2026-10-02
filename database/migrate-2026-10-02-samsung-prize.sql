-- Cell C amendment brief (2 Oct 2026): the Grand Prize is a Samsung Galaxy A50 bundled with
-- accessories to the value of R10,000 (replaces the Huawei placeholder). Competition renamed WIN A SAMSUNG.
SET NAMES utf8mb4;
UPDATE settings SET svalue = 'Win a Samsung Galaxy A50' WHERE skey = 'hero_headline';
UPDATE settings SET svalue = 'Samsung Galaxy A50 + Accessory Bundle' WHERE skey = 'prize_headline';
UPDATE settings SET svalue = 'R10,000' WHERE skey = 'prize_value';
UPDATE settings SET svalue = 'In partnership with Cell C, George Musical Festival is giving away a Samsung Galaxy A50, bundled with accessories to the value of R10,000. Enter free in under a minute, share your personal link, and climb the leaderboard to win.' WHERE skey = 'hero_text';
UPDATE settings SET svalue = 'WIN A SAMSUNG is live — enter free and win a Samsung Galaxy A50 with accessories to the value of R10,000.' WHERE skey = 'announcement';
REPLACE INTO settings (skey, svalue) VALUES ('collection_note', 'If you win, you''ll be notified and asked to collect your Samsung Galaxy A50 and accessory bundle at a Cell C store — bring your ID and this confirmation.');
UPDATE settings SET svalue = REPLACE(svalue, 'WIN A HUAWEI', 'WIN A SAMSUNG') WHERE svalue LIKE '%WIN A HUAWEI%';
UPDATE settings SET svalue = REPLACE(svalue,
  'one brand-new Huawei Pura 80 Pro smartphone with a recommended retail value of R18 000. The model, colour and storage are as supplied by the Promoter. The phone is delivered to, or collected by, the winner in South Africa as arranged with the Promoter.',
  'one Samsung Galaxy A50 smartphone, bundled with accessories to the value of R10,000. The colour, storage and accessories are as supplied by the Promoter. The winner collects the prize at a Cell C store arranged with the Promoter and must bring their ID and their entry confirmation.')
WHERE skey = 'terms_html';
UPDATE draws SET prize_description = 'Samsung Galaxy A50 smartphone\nAccessories to the value of R10,000\nCollect at a Cell C store — bring your ID' WHERE draw_key = 'final';
