-- Cell C tagline is "Nothing should stop you" (replaces "The Unstoppables"), 28 Sep 2026.
SET NAMES utf8mb4;
UPDATE settings SET svalue = REPLACE(svalue, 'The Unstoppables', 'Nothing should stop you') WHERE svalue LIKE '%The Unstoppables%';
