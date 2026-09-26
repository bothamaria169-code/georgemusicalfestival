-- Use the full festival name "George Musical Festival" in saved settings and media (26 Sep 2026).
SET NAMES utf8mb4;
UPDATE settings SET svalue = REPLACE(svalue, 'George Music Festival', 'George Musical Festival') WHERE svalue LIKE '%George Music Festival%';
UPDATE media SET title = REPLACE(title, 'George Music Festival', 'George Musical Festival'), caption = REPLACE(caption, 'George Music Festival', 'George Musical Festival')
WHERE title LIKE '%George Music Festival%' OR caption LIKE '%George Music Festival%';
