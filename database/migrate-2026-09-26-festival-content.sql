-- Copies the festival content (line-up, gallery, videos, ticket link, event details) from the
-- FLY FOR TWO database into the Cell C database, so the festival sections stay identical.
-- Competition, prize and legal settings are NOT copied. Run as a user with access to both:
--   mysql --default-character-set=utf8mb4 gmf_cellc < database/migrate-2026-09-26-festival-content.sql
SET NAMES utf8mb4;

DELETE FROM gmf_cellc.media;
INSERT INTO gmf_cellc.media (kind, title, caption, src, poster, album, featured, sort_order, created_at)
SELECT kind, title, caption, src, poster, album, featured, sort_order, created_at FROM gmf_flyfortwo.media ORDER BY id;

REPLACE INTO gmf_cellc.settings (skey, svalue)
SELECT skey, svalue FROM gmf_flyfortwo.settings
WHERE skey IN ('event_name', 'event_tagline', 'event_date', 'event_time', 'event_venue', 'ticket_url', 'ticket_note',
               'hero_video_url', 'contact_email', 'contact_phone', 'social_facebook', 'social_instagram', 'social_tiktok',
               'upcoming_events', 'towns');

UPDATE gmf_cellc.draws SET prize_description = 'Brand-new Huawei Pura 80 Pro\nRetail value R18 000\nSponsored by Cell C'
WHERE draw_key = 'final' AND prize_description LIKE 'Return flights%';
