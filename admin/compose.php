<?php
/**
 * Write an email like in a normal mail app: choose the competition, say who it goes to (one person,
 * a list or a spreadsheet), write it and press Send. Sends through the campaign queue, so every
 * email keeps its unsubscribe link and shows up under Sent emails.
 */
require __DIR__ . '/_layout.php';

$admin = require_admin('admin');
campaign_schema();

$brands = campaign_brands();
$brand = isset($brands[$_POST['brand'] ?? '']) ? $_POST['brand'] : (isset($brands[$_GET['brand'] ?? '']) ? $_GET['brand'] : 'acsa');
$to = '';
$subject = '';
$heading = '';
$body = '';
$picked = [];
$error = '';

// "Email this person" on the Entrants page, and "Reply" on the Replies page.
if (filter_var((string) ($_GET['to'] ?? ''), FILTER_VALIDATE_EMAIL)) {
    $to = mb_strtolower((string) $_GET['to']);
}
if (isset($_GET['subject'])) {
    $subject = mb_substr(trim((string) $_GET['subject']), 0, 190);
}
if (!empty($_GET['entrant'])) {
    $to = (string) q('SELECT email FROM entrants WHERE id = ?', [(int) $_GET['entrant']])->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = (string) ($_POST['action'] ?? '');
    $to = trim((string) ($_POST['to'] ?? ''));
    $subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 190);
    $heading = mb_substr(trim((string) ($_POST['heading'] ?? '')), 0, 190) ?: $subject;
    $body = trim(preg_replace('#<script\b.*?</script>#is', '', (string) ($_POST['body_html'] ?? '')) ?? '');
    $picked = array_values(array_intersect((array) ($_POST['lists'] ?? []), $brands[$brand]['audiences']));

    if ($subject === '' || trim(strip_tags($body)) === '') {
        $error = 'Add a subject and write your message.';
    } elseif ($action === 'test') {
        [$s, $html] = campaign_render(['brand' => $brand, 'subject' => $subject, 'heading' => $heading, 'body_html' => $body],
            ['email' => $admin['email'], 'full_name' => $admin['name'], 'ref_code' => 'ABC2345']);
        $ok = send_campaign_mail($admin['email'], '[TEST] ' . $s, $html);
        audit('compose.test', ['to' => $admin['email'], 'ok' => $ok]);
        flash($ok ? 'success' : 'error', $ok ? 'Test sent to ' . $admin['email'] . '.' : 'The test could not be sent. Check the mail settings.');
    } elseif ($action === 'send') {
        $people = [];
        foreach (preg_split('/[\s,;]+/', mb_strtolower($to)) ?: [] as $address) {
            if ($address !== '') {
                $people[] = ['email' => $address];
            }
        }
        $sheet = [];
        $sheetName = '';
        if (!empty($_FILES['sheet']['tmp_name']) && is_uploaded_file($_FILES['sheet']['tmp_name'])) {
            $sheetName = (string) $_FILES['sheet']['name'];
            try {
                $sheet = campaign_sheet_recipients(spreadsheet_rows($_FILES['sheet']['tmp_name'], $sheetName));
                if (!$sheet) {
                    $error = 'No email addresses found in ' . $sheetName . '. The sheet needs a column headed "Email".';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        if ($error === '' && !$people && !$sheet && !$picked) {
            $error = 'Add at least one person in the To box, or choose a list.';
        }
        if ($error === '') {
            q('INSERT INTO email_campaigns (brand, name, subject, heading, body_html, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$brand, mb_substr($subject, 0, 120), $subject, $heading, $body, 'draft', $admin['id'], now()]);
            $id = (int) db()->lastInsertId();
            $total = ['added' => 0, 'invalid' => 0, 'duplicate' => 0, 'unsubscribed' => 0];
            $add = function (array $n) use (&$total) {
                foreach ($n as $k => $v) {
                    $total[$k] += $v;
                }
            };
            $add(campaign_add_recipients($id, $people, 'Typed'));
            if ($sheet) {
                $add(campaign_add_recipients($id, $sheet, $sheetName));
            }
            foreach ($picked as $list) {
                $add(campaign_add_recipients($id, campaign_audience_rows($list, $brand), CAMPAIGN_AUDIENCES[$list]));
            }
            if (!$total['added']) {
                q('DELETE FROM email_campaigns WHERE id = ?', [$id]);
                $error = $total['unsubscribed'] ? 'Nobody to send to: these people have unsubscribed.' : 'Nobody to send to: check the email addresses.';
            } else {
                q("UPDATE email_campaigns SET status = 'sending', started_at = ? WHERE id = ?", [now(), $id]);
                audit('compose.send', ['id' => $id, 'brand' => $brand] + $total);
                set_time_limit(120);
                [$sent, $failed] = campaign_send_batch(10);
                $left = max(0, $total['added'] - $sent - $failed);
                $notes = array_filter([
                    $total['invalid'] ? number_format($total['invalid']) . ' not valid' : '',
                    $total['unsubscribed'] ? number_format($total['unsubscribed']) . ' unsubscribed' : '',
                ]);
                flash($failed ? 'error' : 'success', 'Sent to ' . number_format($sent) . ($failed ? ', ' . number_format($failed) . ' failed' : '')
                    . ($left ? '. The other ' . number_format($left) . ' go out automatically, about 40 every 5 minutes' : '') . '.'
                    . ($notes ? ' Skipped: ' . implode(', ', $notes) . '.' : ''));
                redirect('admin/campaigns.php?id=' . $id);
            }
        }
    }
}

$lists = [];
foreach ($brands as $key => $b) {
    foreach ($b['audiences'] as $audience) {
        $lists[] = ['brand' => $key, 'key' => $audience, 'label' => CAMPAIGN_AUDIENCES[$audience], 'count' => count(campaign_audience_rows($audience, $key))];
    }
}
$templates = [];
foreach (array_keys($brands) as $key) {
    foreach (campaign_templates($key) as $tplKey => $t) {
        $close = ['{close_date}', $brands[$key]['close'] !== '' ? $brands[$key]['close'] : '31 December 2026'];
        $templates[$tplKey] = ['brand' => $key, 'name' => (!empty($t['saved']) ? 'Saved: ' : '') . $t['name'], 'subject' => str_replace($close[0], $close[1], $t['subject']),
            'heading' => str_replace($close[0], $close[1], $t['heading']), 'body' => str_replace($close[0], $close[1], $t['body'])];
    }
}

admin_head('Write an email', 'compose');
?>
<?php if ($error !== ''): ?><div class="a-flash a-flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="mail" id="mailForm">
  <?= csrf_field() ?>
  <input type="hidden" name="brand" id="mBrand" value="<?= e($brand) ?>">
  <input type="hidden" name="heading" id="mHeading" value="<?= e($heading) ?>">
  <textarea name="body_html" id="mBody" hidden><?= e($body) ?></textarea>

  <div class="mail-row">
    <span class="mail-label">From</span>
    <div class="mail-brands">
      <?php foreach ($brands as $key => $b): ?>
        <button type="button" class="mail-brand <?= $key === $brand ? 'is-on' : '' ?>" data-brand="<?= e($key) ?>"><?= e($b['label']) ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mail-row">
    <label class="mail-label" for="mTo">To</label>
    <input class="mail-input" id="mTo" name="to" value="<?= e($to) ?>" placeholder="Type one or more email addresses, separated by commas" autocomplete="off">
  </div>

  <div class="mail-row mail-row-lists">
    <span class="mail-label">Or send to</span>
    <div class="mail-lists">
      <?php foreach ($lists as $l): ?>
        <label class="mail-list" data-brand="<?= e($l['brand']) ?>"><input type="checkbox" name="lists[]" value="<?= e($l['key']) ?>" data-count="<?= (int) $l['count'] ?>" <?= $l['brand'] === $brand && in_array($l['key'], $picked, true) ? 'checked' : '' ?>><span><?= e($l['label']) ?> · <strong><?= number_format($l['count']) ?></strong></span></label>
      <?php endforeach; ?>
      <label class="mail-list mail-file"><input type="file" name="sheet" id="mSheet" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"><span id="mSheetName"><i class="fa-solid fa-file-excel"></i> Upload Excel or CSV</span></label>
    </div>
  </div>

  <div class="mail-row">
    <label class="mail-label" for="mSubject">Subject</label>
    <input class="mail-input" id="mSubject" name="subject" value="<?= e($subject) ?>" maxlength="190" required>
  </div>

  <div class="mail-row">
    <label class="mail-label" for="mTemplate">Template</label>
    <select class="mail-input mail-template" id="mTemplate"><option value="">Blank email</option></select>
  </div>

  <div class="mail-tools" role="toolbar" aria-label="Formatting">
    <button type="button" data-cmd="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
    <button type="button" data-cmd="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
    <button type="button" data-cmd="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
    <span class="mail-sep"></span>
    <button type="button" data-block="h2" title="Heading"><i class="fa-solid fa-heading"></i></button>
    <button type="button" data-cmd="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
    <button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
    <span class="mail-sep"></span>
    <button type="button" id="mLink" title="Add a link"><i class="fa-solid fa-link"></i></button>
    <button type="button" id="mButton" title="Add an Enter now button"><i class="fa-solid fa-square-plus"></i> Enter button</button>
    <button type="button" id="mName" title="Add the person's first name"><i class="fa-solid fa-user"></i> First name</button>
    <span class="mail-sep"></span>
    <button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
  </div>
  <div class="mail-editor" id="mEditor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Your message"><?= $body ?></div>

  <div class="mail-actions">
    <button class="btn btn-primary btn-send" type="submit" name="action" value="send" id="mSend"><i class="fa-solid fa-paper-plane"></i> Send</button>
    <button class="btn" type="submit" name="action" value="test" formnovalidate><i class="fa-regular fa-envelope"></i> Send a test to me</button>
    <span class="muted" id="mCount"></span>
  </div>
  <p class="mail-warn" id="mLeadsWarn" hidden><i class="fa-solid fa-triangle-exclamation"></i> These leads have not opted in. Send them one email inviting them to enter or join the list, and keep it small: the mailbox allows only a few hundred emails a day.</p>
  <p class="help">Every email includes an unsubscribe link, and people who have unsubscribed are skipped. Up to 10 go out straight away, then about 40 every 5 minutes. See what went out under Sent emails.</p>
</form>

<script>
(function () {
  var templates = <?= json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
  var buttons = <?= json_encode(array_map(fn ($k) => campaign_button($k, 'Enter now'), array_combine(array_keys($brands), array_keys($brands))), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var form = document.getElementById('mailForm'), editor = document.getElementById('mEditor'), brand = document.getElementById('mBrand');
  var tpl = document.getElementById('mTemplate'), subject = document.getElementById('mSubject'), heading = document.getElementById('mHeading');
  var to = document.getElementById('mTo'), sheet = document.getElementById('mSheet'), count = document.getElementById('mCount');

  function addresses() { return to.value.split(/[\s,;]+/).filter(function (a) { return a.indexOf('@') > 0; }).length; }
  function total() {
    var n = addresses();
    form.querySelectorAll('input[name="lists[]"]:checked').forEach(function (c) { n += +c.dataset.count; });
    return n;
  }
  function showCount() {
    var n = total();
    count.textContent = sheet.files.length ? 'To ' + (n ? n + ' people plus ' : '') + 'your spreadsheet' : (n ? 'To ' + n + (n === 1 ? ' person' : ' people') : '');
  }
  function setBrand(key) {
    brand.value = key;
    form.querySelectorAll('.mail-brand').forEach(function (b) { b.classList.toggle('is-on', b.dataset.brand === key); });
    form.querySelectorAll('.mail-list[data-brand]').forEach(function (l) {
      var on = l.dataset.brand === key;
      l.hidden = !on;
      if (!on) { l.querySelector('input').checked = false; }
    });
    tpl.length = 1;
    Object.keys(templates).forEach(function (k) {
      if (templates[k].brand === key) { tpl.add(new Option(templates[k].name, k)); }
    });
    showCount();
  }
  function place(html) { editor.focus(); document.execCommand('insertHTML', false, html); }

  form.querySelectorAll('.mail-brand').forEach(function (b) { b.addEventListener('click', function () { setBrand(b.dataset.brand); }); });
  form.querySelectorAll('[data-cmd]').forEach(function (b) {
    b.addEventListener('mousedown', function (ev) { ev.preventDefault(); document.execCommand(b.dataset.cmd); });
  });
  form.querySelector('[data-block]').addEventListener('mousedown', function (ev) { ev.preventDefault(); document.execCommand('formatBlock', false, 'h2'); });
  document.getElementById('mLink').addEventListener('mousedown', function (ev) {
    ev.preventDefault();
    var url = prompt('Link address (https://...)');
    if (url) { document.execCommand('createLink', false, url); }
  });
  document.getElementById('mButton').addEventListener('mousedown', function (ev) { ev.preventDefault(); place(buttons[brand.value]); });
  document.getElementById('mName').addEventListener('mousedown', function (ev) { ev.preventDefault(); place('{first_name}'); });
  tpl.addEventListener('change', function () {
    var t = templates[tpl.value];
    if (!t) { return; }
    subject.value = t.subject; heading.value = t.heading; editor.innerHTML = t.body;
  });
  tpl.addEventListener('change', function () { if (!tpl.value) { subject.value = ''; heading.value = ''; editor.innerHTML = ''; } });
  subject.addEventListener('input', function () { if (!tpl.value) { heading.value = ''; } });
  to.addEventListener('input', showCount);
  sheet.addEventListener('change', function () {
    document.getElementById('mSheetName').textContent = sheet.files.length ? sheet.files[0].name : 'Upload Excel or CSV';
    showCount();
  });
  var leadsWarn = document.getElementById('mLeadsWarn');
  function leadsCheck() { leadsWarn.hidden = !form.querySelector('input[value="leads_wc"]:checked'); }
  form.querySelectorAll('input[name="lists[]"]').forEach(function (c) { c.addEventListener('change', function () { showCount(); leadsCheck(); }); });
  form.querySelectorAll('.mail-brand').forEach(function (b) { b.addEventListener('click', leadsCheck); });
  form.addEventListener('submit', function (ev) {
    document.getElementById('mBody').value = editor.innerHTML;
    var n = total();
    if (ev.submitter && ev.submitter.id === 'mSend' && (n > 1 || sheet.files.length)) {
      var what = sheet.files.length ? 'everyone selected, including your spreadsheet' : n + ' people';
      if (!confirm('Send this email to ' + what + '?')) { ev.preventDefault(); }
    }
  });
  setBrand(brand.value);
  var start = <?= json_encode((string) ($_GET['tpl'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var keepSubject = <?= json_encode($_SERVER['REQUEST_METHOD'] === 'GET' ? $subject : '', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  if (start && templates[start] && <?= $_SERVER['REQUEST_METHOD'] === 'GET' ? 'true' : 'false' ?>) { tpl.value = start; tpl.dispatchEvent(new Event('change')); if (keepSubject) { subject.value = keepSubject; } }
})();
</script>
<?php admin_foot();
