<?php
/**
 * The four-step entry journey (Entry → Verify → Share → Confirmed), shared by
 * fly-for-two.php (/flyfortwo/) and embed.php (/flyfortwo/embed). Expects: $src, $refCode, $towns, $flyOpts, $shareCap.
 * Optional $embed = true opens links in a new tab (the page is inside an iframe).
 */
$embed  = $embed ?? false;
$target = $embed ? ' target="_blank" rel="noopener"' : '';
?>
<div class="ff-progress" id="ffProgress" style="--progress:0%">
  <div class="ff-track"><span class="ff-plane"><i class="fa-solid fa-plane"></i></span></div>
  <div class="ff-labels"><span class="is-on">Enter</span><span>Verify</span><span>Share</span><span>Confirmed</span></div>
</div>

<div class="ff-body">
  <!-- STEP 1 · ENTRY -->
  <form class="ff-view is-active" id="view-1" novalidate autocomplete="on">
    <input type="hidden" name="action" value="start">
    <input type="hidden" name="timer" value="<?= e(form_timer()) ?>">
    <input type="hidden" name="fp" value="">
    <input type="hidden" name="channel" value="<?= e($src['channel']) ?>">
    <input type="hidden" name="pt" value="<?= e($src['activation_point']) ?>">
    <input type="hidden" name="town_src" value="<?= e($src['source_town']) ?>">
    <input type="hidden" name="slot" value="<?= e($src['time_slot']) ?>">
    <input type="hidden" name="utm_source" value="<?= e($src['utm_source']) ?>">
    <input type="hidden" name="utm_medium" value="<?= e($src['utm_medium']) ?>">
    <input type="hidden" name="utm_campaign" value="<?= e($src['utm_campaign']) ?>">
    <input type="hidden" name="ref" value="<?= e($refCode) ?>">
    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="ff-section-label">Your details</div>
    <div class="field"><label for="f-name">Full name</label><input id="f-name" name="full_name" type="text" autocomplete="name" placeholder="e.g. Thandiwe Nkosi" required></div>
    <div class="field"><label for="f-email">Email address</label><input id="f-email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com" required><small class="ff-hint">Your verification code is sent here.</small></div>
    <div class="form-row">
      <div class="field"><label for="f-phone">Cellphone</label><input id="f-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="082 123 4567" required></div>
      <div class="field"><label for="f-town">Your town</label>
        <select id="f-town" name="town"><option value="">Choose…</option><?php foreach ($towns as $t): ?><option<?= $t === $src['source_town'] ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="field"><label for="f-age">Age group</label>
      <select id="f-age" name="age_group" required><option value="">Choose…</option><?php foreach (lines((string) setting('age_groups')) as $a): ?><option><?= e($a) ?></option><?php endforeach; ?></select>
      <small class="ff-hint">Entrants must be 18 or older.</small>
    </div>
    <div class="field"><label for="f-fly"><?= e(setting('travel_question')) ?></label>
      <select id="f-fly" name="fly_frequency" required><option value="">Choose an answer…</option><?php foreach ($flyOpts as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select>
    </div>

    <div class="ff-consent is-required" data-field="consent_terms">
      <input type="checkbox" id="c-terms" name="consent_terms" value="1">
      <label for="c-terms"><span class="ff-tag">Required</span> I agree to the <?= e(setting('comp_name')) ?> <a href="<?= e(url('flyfortwo/terms')) ?>" target="_blank">competition terms &amp; conditions</a> and the <a href="<?= e(url('flyfortwo/privacy')) ?>" target="_blank">privacy notice</a>. My information is processed in line with POPIA, and my first name, surname initial and town may appear on the public leaderboard.</label>
    </div>
    <div class="ff-consent is-required" data-field="consent_popia">
      <input type="checkbox" id="c-popia" name="consent_popia" value="1">
      <label for="c-popia"><span class="ff-tag">Required</span> <strong>POPIA consent:</strong> <?= e(setting('popia_consent_text')) ?></label>
    </div>
    <div class="ff-consent is-optional">
      <input type="checkbox" id="c-mkt" name="consent_marketing" value="1">
      <label for="c-mkt"><span class="ff-tag ff-tag-soft">Optional</span> Keep me updated on future GMF × ACSA events and travel offers.</label>
    </div>

    <?php if (turnstile_enabled()): ?><div class="cf-turnstile" data-sitekey="<?= e(config('turnstile.site_key')) ?>" data-appearance="interaction-only" style="margin:6px 0"></div><?php endif; ?>
    <div class="ff-alert" role="alert" hidden></div>
    <button class="ff-btn ff-btn-primary" type="submit">Enter now <i class="fa-solid fa-arrow-right"></i></button>
    <p class="ff-fine">One entry per phone number and email. Already entered? <a href="<?= e(url('flyfortwo/login')) ?>"<?= $target ?>>Sign in</a></p>
  </form>

  <!-- STEP 2 · VERIFY -->
  <form class="ff-view" id="view-2" novalidate>
    <div class="ff-center">
      <div class="ff-icon"><i class="fa-regular fa-envelope-open"></i></div>
      <h2 class="ff-h">Check your email</h2>
      <p class="ff-sub">We've emailed <strong data-bind="email"></strong>. Tap <strong>Confirm my entry</strong> in that email, or type the 6-digit code here. You can leave this page — we'll remember where you were.</p>
    </div>
    <div class="ff-otp" role="group" aria-label="6-digit code">
      <?php for ($i = 0; $i < 6; $i++): ?><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit <?= $i + 1 ?>" <?= $i === 0 ? 'autocomplete="one-time-code"' : '' ?>><?php endfor; ?>
    </div>
    <div class="ff-dev" hidden></div>
    <div class="ff-alert" role="alert" hidden></div>
    <button class="ff-btn ff-btn-primary" type="submit">Confirm code</button>
    <button class="ff-btn ff-btn-ghost" type="button" data-resend>Resend code</button>
    <button class="ff-btn ff-btn-ghost ff-small" type="button" data-back><i class="fa-solid fa-pen"></i> Wrong email? Edit details</button>
    <p class="ff-fine">Can't find it after a minute? Check your spam, junk or promotions folder, then tap Resend code.</p>
  </form>

  <!-- STEP 3 · SHARE -->
  <div class="ff-view" id="view-3">
    <div class="ff-center">
      <div class="ff-check"><i class="fa-solid fa-check"></i></div>
      <h2 class="ff-h">You're entered!</h2>
      <p class="ff-sub">Now share your personal link. <strong>Every different person who opens it adds 1 to your score</strong> — the top score wins the Grand Prize.</p>
    </div>
    <div class="ff-link"><span data-bind="ref_link"></span><button type="button" data-copy aria-label="Copy link"><i class="fa-regular fa-copy"></i></button></div>
    <div class="ff-share-grid">
      <a class="ff-share is-wa" data-share="whatsapp" href="#" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>
      <button class="ff-share" type="button" data-copy><i class="fa-solid fa-link"></i><span>Copy link</span></button>
      <a class="ff-share" data-share="facebook" href="#" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i><span>Facebook</span></a>
      <button class="ff-share" type="button" data-share="native"><i class="fa-solid fa-share-nodes"></i><span>More</span></button>
    </div>
    <button class="ff-btn ff-btn-primary" type="button" data-goto="4" data-after-share hidden>Continue <i class="fa-solid fa-arrow-right"></i></button>
    <button class="ff-btn ff-btn-outline" type="button" data-goto="4">Skip for now</button>
  </div>

  <!-- STEP 4 · CONFIRMED -->
  <div class="ff-view" id="view-4">
    <div class="ff-center">
      <div class="ff-check"><i class="fa-solid fa-check"></i></div>
      <h2 class="ff-h">You're in!</h2>
      <div class="ff-shared-note" hidden><span class="ff-pill"><i class="fa-solid fa-share"></i> Link shared — every open adds to your score</span></div>
    </div>
    <div class="pass">
      <div class="pass-top"><span class="pass-brand"><?= e(setting('comp_name')) ?></span><span class="pass-tag">GMF × ACSA</span></div>
      <div class="pass-mid">
        <div><small>Entrant</small><strong data-bind="name"></strong></div>
        <div><small>Event</small><strong><?= e(mb_strimwidth((string) setting('event_name'), 0, 18, '…')) ?></strong></div>
      </div>
      <div class="pass-mid">
        <div><small>From</small><strong>Your town</strong></div>
        <div style="text-align:right"><small>To</small><strong>A weekend away</strong></div>
      </div>
      <div class="pass-divider"></div>
      <div class="pass-bottom">
        <div class="pass-entries"><span class="num">0</span><span>link opens so far · share to climb</span></div>
        <div class="pass-barcode" aria-hidden="true"></div>
      </div>
    </div>
    <p class="ff-sub ff-center"><?= e(setting('draw_reassurance')) ?></p>
    <a class="ff-btn ff-btn-primary" data-bind-href="dashboard" href="<?= e(url('flyfortwo/dashboard')) ?>"<?= $target ?>>Open my dashboard <i class="fa-solid fa-arrow-right"></i></a>
  </div>
</div>
