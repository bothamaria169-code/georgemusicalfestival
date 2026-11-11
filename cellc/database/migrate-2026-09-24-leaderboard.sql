-- Leaderboard rules (24 Sep 2026): entrant photos, and the two draws become 1st / 2nd place on the referral leaderboard.
ALTER TABLE entrants ADD COLUMN IF NOT EXISTS photo VARCHAR(255) NULL AFTER notes;
UPDATE draws SET title = 'Grand Prize — Top Referrer', scheduled_for = '2026-10-31',
       prize_description = 'Return flights for two\nWeekend accommodation\nShuttle transfers included' WHERE draw_key = 'final';
UPDATE draws SET title = 'Second Prize — Runner-up', scheduled_for = '2026-10-31',
       prize_description = '' WHERE draw_key = 'event_day';
