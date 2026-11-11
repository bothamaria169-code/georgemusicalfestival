<?php
require __DIR__ . '/_layout.php';

require_admin('admin');

$uploadDir = APP_ROOT . '/uploads/media';
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

function save_upload(array $file, string $dir, array $allowed): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > 12 * 1024 * 1024) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || !@getimagesize($file['tmp_name'])) {
        return null;
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    return move_uploaded_file($file['tmp_name'], $dir . '/' . $name) ? 'uploads/media/' . $name : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    $album = mb_substr(trim((string) ($_POST['album'] ?? '')), 0, 60) ?: 'Festival';

    if ($action === 'upload') {
        $kind = ($_POST['kind'] ?? '') === 'artist' ? 'artist' : 'image';
        $files = $_FILES['files'] ?? null;
        $ok = 0;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $n) {
                $path = save_upload(['error' => $files['error'][$i], 'size' => $files['size'][$i], 'tmp_name' => $files['tmp_name'][$i]], $uploadDir, $allowed);
                if ($path) {
                    q('INSERT INTO media (kind, title, caption, src, album, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                        $kind, mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160) ?: null, mb_substr(trim((string) ($_POST['caption'] ?? '')), 0, 255) ?: null,
                        $path, $album, (int) ($_POST['sort_order'] ?? 100), now(),
                    ]);
                    $ok++;
                }
            }
        }
        flash($ok ? 'success' : 'error', $ok ? "{$ok} file(s) uploaded." : 'No valid images uploaded (JPG, PNG, WebP or GIF up to 12 MB).');
    } elseif ($action === 'video') {
        $src = trim((string) ($_POST['src'] ?? ''));
        if (!filter_var($src, FILTER_VALIDATE_URL)) {
            flash('error', 'Please paste a valid video URL (YouTube, Vimeo or a direct .mp4 link).');
        } else {
            $poster = !empty($_FILES['poster']['name']) ? save_upload($_FILES['poster'], $uploadDir, $allowed) : null;
            q('INSERT INTO media (kind, title, caption, src, poster, album, featured, sort_order, created_at) VALUES (\'video\', ?, ?, ?, ?, ?, ?, ?, ?)', [
                mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160) ?: 'Festival film', null, $src, $poster, $album, !empty($_POST['featured']) ? 1 : 0, (int) ($_POST['sort_order'] ?? 100), now(),
            ]);
            flash('success', 'Video added.');
        }
    } elseif ($action === 'update') {
        q('UPDATE media SET title = ?, caption = ?, album = ?, sort_order = ?, featured = ? WHERE id = ?', [
            mb_substr(trim((string) $_POST['title']), 0, 160) ?: null, mb_substr(trim((string) $_POST['caption']), 0, 255) ?: null,
            $album, (int) $_POST['sort_order'], !empty($_POST['featured']) ? 1 : 0, (int) $_POST['id'],
        ]);
        flash('success', 'Saved.');
    } elseif ($action === 'delete') {
        $m = q('SELECT * FROM media WHERE id = ?', [(int) $_POST['id']])->fetch();
        if ($m) {
            foreach (['src', 'poster'] as $col) {
                if ($m[$col] && str_starts_with($m[$col], 'uploads/media/')) {
                    @unlink(APP_ROOT . '/' . $m[$col]);
                }
            }
            q('DELETE FROM media WHERE id = ?', [$m['id']]);
            flash('success', 'Deleted.');
        }
    }
    audit('media.' . $action);
    redirect('admin/media.php?tab=' . urlencode($_GET['tab'] ?? 'image'));
}

$tab = in_array($_GET['tab'] ?? '', ['image', 'video', 'artist'], true) ? $_GET['tab'] : 'image';
$items = q('SELECT * FROM media WHERE kind = ? ORDER BY featured DESC, sort_order, id', [$tab])->fetchAll();
$albums = q('SELECT DISTINCT album FROM media ORDER BY album')->fetchAll(PDO::FETCH_COLUMN);

admin_head('Website media', 'media');
?>
<div class="toolbar">
  <?php foreach (['image' => 'Gallery photos', 'video' => 'Videos', 'artist' => 'Line-up'] as $k => $v): ?>
    <a class="btn <?= $tab === $k ? 'btn-primary' : '' ?>" href="?tab=<?= $k ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="card" style="margin-bottom:16px">
  <?php if ($tab === 'video'): ?>
    <h2>Add a video</h2>
    <form method="post" enctype="multipart/form-data" class="grid g-3" style="gap:0 14px">
      <?= csrf_field() ?><input type="hidden" name="action" value="video">
      <div class="fg"><label class="f">Video URL</label><input class="in" name="src" placeholder="https://www.youtube.com/watch?v=…" required></div>
      <div class="fg"><label class="f">Title</label><input class="in" name="title" placeholder="e.g. Eden Soul & Jazz — the story"></div>
      <div class="fg"><label class="f">Category</label><input class="in" name="album" list="albums" value="Festival films"></div>
      <div class="fg"><label class="f">Poster image (optional — YouTube thumbnails are automatic)</label><input class="in" type="file" name="poster" accept="image/*"></div>
      <div class="fg"><label class="f">Order</label><input class="in" type="number" name="sort_order" value="100"></div>
      <div class="fg" style="display:flex;align-items:end;gap:14px"><label class="chk"><input type="checkbox" name="featured" value="1"> Feature first</label><button class="btn btn-primary" type="submit">Add video</button></div>
    </form>
  <?php else: ?>
    <h2><?= $tab === 'artist' ? 'Add line-up artists (one photo per artist)' : 'Upload gallery photos' ?></h2>
    <form method="post" enctype="multipart/form-data" class="grid g-3" style="gap:0 14px">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="kind" value="<?= e($tab) ?>">
      <div class="fg"><label class="f">Images</label><input class="in" type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required></div>
      <?php if ($tab === 'artist'): ?>
        <div class="fg"><label class="f">Artist name</label><input class="in" name="title" required></div>
        <div class="fg"><label class="f">Genre / billing</label><input class="in" name="caption" placeholder="e.g. Headliner · Soul"></div>
      <?php else: ?>
        <div class="fg"><label class="f">Album</label><input class="in" name="album" list="albums" value="Festival"></div>
        <div class="fg"><label class="f">Caption (applies to all)</label><input class="in" name="caption"></div>
      <?php endif; ?>
      <div class="fg"><label class="f">Order</label><input class="in" type="number" name="sort_order" value="100"></div>
      <div class="fg" style="display:flex;align-items:end"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-upload"></i> Upload</button></div>
    </form>
    <p class="help">Large landscape photos (1600px+ wide) look best — the site shows images big. Lower order numbers appear first; "featured" items lead the homepage.</p>
  <?php endif; ?>
  <datalist id="albums"><?php foreach ($albums as $a): ?><option value="<?= e($a) ?>"><?php endforeach; ?></datalist>
</div>

<div class="media-grid">
  <?php foreach ($items as $m): ?>
    <div class="media-item">
      <div class="thumb">
        <img src="<?= e($m['kind'] === 'video' ? video_poster($m) : media_url($m['src'])) ?>" alt="" loading="lazy">
        <?php if ($m['featured']): ?><span class="pill pill-warn">Featured</span><?php endif; ?>
      </div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
        <input class="in" name="title" value="<?= e($m['title']) ?>" placeholder="Title">
        <input class="in" name="caption" value="<?= e($m['caption']) ?>" placeholder="Caption">
        <div style="display:grid;grid-template-columns:1fr 70px;gap:6px"><input class="in" name="album" value="<?= e($m['album']) ?>" list="albums"><input class="in" type="number" name="sort_order" value="<?= (int) $m['sort_order'] ?>"></div>
        <label class="chk"><input type="checkbox" name="featured" value="1" <?= $m['featured'] ? 'checked' : '' ?>> Featured</label>
        <div style="display:flex;gap:6px"><button class="btn btn-sm" type="submit">Save</button>
          <button class="btn btn-sm btn-danger" type="submit" name="action" value="delete" onclick="return confirm('Delete this item?')"><i class="fa-solid fa-trash"></i></button></div>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$items): ?><p class="muted">Nothing here yet.</p><?php endif; ?>
</div>
<?php admin_foot(); ?>
