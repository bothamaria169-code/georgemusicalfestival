-- Cell C confirmed the phone is the Samsung Galaxy A56 (not the A50), 2 Oct 2026.
SET NAMES utf8mb4;
UPDATE settings SET svalue = REPLACE(svalue, 'Galaxy A50', 'Galaxy A56') WHERE svalue LIKE '%Galaxy A50%';
UPDATE draws SET prize_description = REPLACE(prize_description, 'Galaxy A50', 'Galaxy A56') WHERE prize_description LIKE '%Galaxy A50%';
