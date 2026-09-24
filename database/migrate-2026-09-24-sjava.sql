-- Line-up: Sjava as headliner, with his photos in a "Sjava" gallery album (safe to run twice).
INSERT INTO media (kind, title, caption, src, album, featured, sort_order, created_at)
SELECT 'artist', 'Sjava', 'Headliner · Award-winning artist', 'assets/img/artists/sjava-headliner.jpg', 'Line-up', 1, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM media WHERE src = 'assets/img/artists/sjava-headliner.jpg');
INSERT INTO media (kind, title, caption, src, album, featured, sort_order, created_at)
SELECT 'image', 'Sjava live', 'Sjava on stage', 'assets/img/artists/sjava-crown.jpg', 'Sjava', 0, 5, NOW()
WHERE NOT EXISTS (SELECT 1 FROM media WHERE src = 'assets/img/artists/sjava-crown.jpg');
INSERT INTO media (kind, title, caption, src, album, featured, sort_order, created_at)
SELECT 'image', 'Sjava', 'Sjava performing live', 'assets/img/artists/sjava-live.jpg', 'Sjava', 0, 6, NOW()
WHERE NOT EXISTS (SELECT 1 FROM media WHERE src = 'assets/img/artists/sjava-live.jpg');
INSERT INTO media (kind, title, caption, src, album, featured, sort_order, created_at)
SELECT 'image', 'Sjava portrait', 'Sjava', 'assets/img/artists/sjava-portrait.jpg', 'Sjava', 0, 7, NOW()
WHERE NOT EXISTS (SELECT 1 FROM media WHERE src = 'assets/img/artists/sjava-portrait.jpg');
