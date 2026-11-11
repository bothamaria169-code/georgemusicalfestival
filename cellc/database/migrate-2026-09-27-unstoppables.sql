-- Cell C's current tagline is "The Unstoppables" ("Change your world" is retired), 27 Sep 2026.
SET NAMES utf8mb4;
UPDATE settings SET svalue = REPLACE(svalue, 'Change your world', 'The Unstoppables') WHERE svalue LIKE '%Change your world%';
