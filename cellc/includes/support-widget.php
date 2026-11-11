<?php
/** Floating "Chat with us" button and help form. Included once from the footer on public pages. */
require_once __DIR__ . '/support.php';
?>
<link rel="stylesheet" href="<?= e(asset('assets/css/support.css')) ?>">
<div class="sup" id="sup" data-api="<?= e(url('api/support.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <button class="sup-fab" type="button" aria-expanded="false" aria-controls="sup-panel"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i><span>Chat with us</span></button>
  <section class="sup-panel" id="sup-panel" role="dialog" aria-modal="false" aria-labelledby="sup-title" hidden>
    <header class="sup-head">
      <div><strong id="sup-title">Need help with <?= e(setting('comp_name')) ?>?</strong><span>We reply by email, usually within one working day.</span></div>
      <button class="sup-x" type="button" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </header>
    <form class="sup-form" novalidate>
      <label>What's it about?
        <select name="topic" required>
          <option value="">Choose one…</option>
          <?php foreach (SUPPORT_TOPICS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <p class="sup-tip" data-for="code" hidden>Codes can take a few minutes. Check your spam, junk or promotions folder first, then tap <strong>Resend code</strong> on the entry form. Still nothing? Send us a message below.</p>
      <label>Your name<input name="name" type="text" autocomplete="name" maxlength="120" required></label>
      <label>Your email<input name="email" type="email" autocomplete="email" inputmode="email" maxlength="190" required></label>
      <label>Your message<textarea name="message" rows="4" maxlength="2000" required placeholder="Tell us what happened"></textarea></label>
      <label class="sup-hp" aria-hidden="true">Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label>
      <p class="sup-err" role="alert" hidden></p>
      <button class="sup-send" type="submit">Send message</button>
      <p class="sup-note">For competition questions only. We use your details only to reply to you.</p>
    </form>
    <div class="sup-done" hidden><i class="fa-solid fa-circle-check"></i><strong>Message sent</strong><p></p></div>
  </section>
</div>
<script src="<?= e(asset('assets/js/support.js')) ?>" defer></script>
