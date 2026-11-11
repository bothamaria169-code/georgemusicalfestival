-- Use the full festival name "George Musical Festival" in saved settings (26 Sep 2026).
SET NAMES utf8mb4;
UPDATE settings SET svalue = REPLACE(svalue, 'George Music Festival', 'George Musical Festival')
WHERE skey IN ('hero_text', 'popia_consent_text', 'terms_html', 'privacy_html', 'announcement', 'event_tagline');
UPDATE settings SET svalue = 'Cell C × George Musical Festival: Change your world' WHERE skey = 'hero_kicker' AND svalue = 'Cell C × GMF: Change your world';
