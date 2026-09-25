/* FLY FOR TWO — four-step entry journey: Enter → Verify → Share → Confirmed */
(function () {
  'use strict';
  var card = document.getElementById('entryCard');
  var form1 = document.getElementById('view-1');
  if (!card || !form1 || !window.FF) return;

  var state = { token: null, data: {}, shared: false, cooldownTimer: null };
  var progress = document.getElementById('ffProgress');
  var labels = progress ? progress.querySelectorAll('.ff-labels span') : [];

  /* ── helpers ─────────────────────────────────────────── */
  function $(sel, root) { return (root || card).querySelector(sel); }
  function $$(sel, root) { return [].slice.call((root || card).querySelectorAll(sel)); }

  function goTo(n) {
    $$('.ff-view').forEach(function (v) { v.classList.toggle('is-active', v.id === 'view-' + n); });
    if (progress) {
      progress.style.setProperty('--progress', ((n - 1) / 3 * 100) + '%');
      labels.forEach(function (l, i) { l.classList.toggle('is-on', i < n); });
    }
    if (n === 4) renderConfirmation();
    var top = card.getBoundingClientRect().top + scrollY - 90;
    if (Math.abs(scrollY - top) > 120) scrollTo({ top: top, behavior: 'smooth' });
    if (n === 2) setTimeout(function () { var f = $('.ff-otp input'); f && f.focus(); }, 250);
  }

  function bind(key, value) {
    $$('[data-bind="' + key + '"]').forEach(function (el) { el.textContent = value; });
  }

  function alertIn(view, msg, ok) {
    var box = $('.ff-alert', view);
    if (!box) return;
    if (!msg) { box.hidden = true; return; }
    box.hidden = false;
    box.className = 'ff-alert' + (ok ? ' is-ok' : '');
    box.innerHTML = msg;
  }

  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function loading(btn, on) { if (btn) { btn.classList.toggle('is-loading', on); btn.disabled = on; } }

  function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(FF.api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; }); })
      .catch(function () { return { ok: false, error: 'No connection — check your mobile data and try again.' }; });
  }

  /* ── device fingerprint (secondary fraud signal only) ─── */
  function fingerprint() {
    var parts = [navigator.userAgent, navigator.language, (navigator.languages || []).join(','), screen.width + 'x' + screen.height + 'x' + screen.colorDepth,
      new Date().getTimezoneOffset(), navigator.hardwareConcurrency || '', navigator.deviceMemory || '', navigator.platform || '', 'ontouchstart' in window];
    try {
      var c = document.createElement('canvas'), x = c.getContext('2d');
      x.textBaseline = 'top'; x.font = '14px Arial'; x.fillStyle = '#f60'; x.fillRect(0, 0, 80, 20);
      x.fillStyle = '#069'; x.fillText('GMF✈ACSA', 2, 2); parts.push(c.toDataURL().slice(-64));
    } catch (e) {}
    var s = parts.join('|');
    if (window.crypto && crypto.subtle && window.TextEncoder) {
      return crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)).then(function (buf) {
        return [].map.call(new Uint8Array(buf), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      });
    }
    return Promise.resolve(s.length + '-' + s.slice(0, 50));
  }
  fingerprint().then(function (fp) { form1.elements.fp.value = fp; });

  /* ── memory: survive leaving the page to check email (mobile browsers often reload it) ── */
  var PENDING = 'ff_pending_v1', DRAFT = 'ff_draft_v1', MAX_AGE = 30 * 60 * 1000;
  function store(key, val) { try { val === null ? localStorage.removeItem(key) : localStorage.setItem(key, JSON.stringify(val)); } catch (e) {} }
  function load(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } }
  var draftFields = ['full_name', 'email', 'phone', 'town', 'age_group', 'fly_frequency', 'consent_terms', 'consent_popia', 'consent_marketing'];
  function saveDraft() {
    var d = { t: Date.now() };
    draftFields.forEach(function (n) { var el = form1.elements[n]; if (el) d[n] = el.type === 'checkbox' ? el.checked : el.value; });
    store(DRAFT, d);
  }
  (function restoreDraft() {
    var d = load(DRAFT);
    if (!d || Date.now() - d.t > 24 * 3600 * 1000) return;
    draftFields.forEach(function (n) {
      var el = form1.elements[n];
      if (!el || d[n] === undefined) return;
      if (el.type === 'checkbox') el.checked = !!d[n]; else if (!el.value || n !== 'town') el.value = d[n];
    });
  })();
  form1.addEventListener('input', saveDraft);
  form1.addEventListener('change', saveDraft);

  /* ── STEP 1: entry form ──────────────────────────────── */
  function clearErrors() {
    $$('.field-error', form1).forEach(function (e) { e.remove(); });
    $$('.has-error', form1).forEach(function (e) { e.classList.remove('has-error'); });
  }
  function showErrors(errors) {
    Object.keys(errors).forEach(function (name) {
      var input = form1.elements[name];
      var wrap = input ? (input.closest('.field') || input.closest('.ff-consent')) : null;
      if (!wrap) return;
      wrap.classList.add('has-error');
      var s = document.createElement('small');
      s.className = 'field-error'; s.textContent = errors[name];
      wrap.appendChild(s);
    });
    var first = form1.querySelector('.has-error input, .has-error select');
    first && first.focus();
  }
  function localValidate() {
    var e = {}, f = form1.elements;
    if (f.full_name.value.trim().split(/\s+/).length < 2) e.full_name = 'Please enter your first name and surname.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.value.trim())) e.email = 'Please enter a valid email address.';
    if (f.phone.value.replace(/\D/g, '').length < 9) e.phone = 'Please enter a valid cellphone number.';
    if (!f.age_group.value) e.age_group = 'Please choose your age group.';
    else if (/^\s*(under|<)\s*18/i.test(f.age_group.value)) e.age_group = 'Sorry — you must be 18 or older to enter.';
    if (!f.fly_frequency.value) e.fly_frequency = 'Please answer the travel question.';
    if (!f.consent_popia.checked) e.consent_popia = 'POPIA consent is required to enter.';
    if (!f.consent_terms.checked) e.consent_terms = 'You need to accept the competition terms to enter.';
    return e;
  }

  form1.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearErrors(); alertIn(form1, '');
    var errs = localValidate();
    if (Object.keys(errs).length) { showErrors(errs); return; }
    var btn = form1.querySelector('[type=submit]');
    loading(btn, true);
    var data = {};
    new FormData(form1).forEach(function (v, k) { data[k] = v; });
    post(data).then(function (res) {
      loading(btn, false);
      if (res.refresh_timer) form1.elements.timer.value = res.refresh_timer;
      if (!res.ok) {
        if (res.errors) showErrors(res.errors);
        var msg = esc(res.error || 'Something went wrong.');
        if (res.login_url) msg += ' <a href="' + res.login_url + '">Sign in</a>';
        alertIn(form1, msg);
        if (window.turnstile) try { turnstile.reset(); } catch (e) {}
        return;
      }
      state.token = res.token;
      store(PENDING, { token: res.token, email: res.email, t: Date.now() });
      bind('email', res.email);
      showDevCode(res.dev_code);
      startCooldown(res.cooldown || FF.cooldown);
      goTo(2);
    });
  });

  /* ── STEP 2: verification code ───────────────────────── */
  var form2 = document.getElementById('view-2');
  var boxes = $$('.ff-otp input', form2);

  boxes.forEach(function (input, i) {
    input.addEventListener('input', function () {
      var digits = input.value.replace(/\D/g, '');
      if (digits.length > 1) { fillCode(digits); return; }
      input.value = digits;
      if (digits && boxes[i + 1]) boxes[i + 1].focus();
      if (code().length === 6) submitCode();
    });
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Backspace' && !input.value && boxes[i - 1]) { boxes[i - 1].focus(); boxes[i - 1].value = ''; }
      if (ev.key === 'ArrowLeft' && boxes[i - 1]) boxes[i - 1].focus();
      if (ev.key === 'ArrowRight' && boxes[i + 1]) boxes[i + 1].focus();
    });
    input.addEventListener('paste', function (ev) {
      var t = (ev.clipboardData || window.clipboardData).getData('text');
      if (t) { ev.preventDefault(); fillCode(t); }
    });
  });
  function fillCode(str) {
    var d = str.replace(/\D/g, '').slice(0, 6).split('');
    boxes.forEach(function (b, i) { b.value = d[i] || ''; });
    (boxes[Math.min(d.length, 5)] || boxes[5]).focus();
    if (d.length === 6) submitCode();
  }
  function code() { return boxes.map(function (b) { return b.value; }).join(''); }
  function showDevCode(c) {
    var dev = $('.ff-dev', form2);
    if (c) { dev.hidden = false; dev.textContent = 'Test mode (email not configured): your code is ' + c; }
    else dev.hidden = true;
  }

  var verifying = false;
  function submitCode() {
    if (verifying) return;
    var c = code();
    var otp = $('.ff-otp', form2);
    otp.classList.remove('has-error');
    if (c.length !== 6) { alertIn(form2, 'Enter all 6 digits of your code.'); return; }
    verifying = true;
    var btn = form2.querySelector('[type=submit]');
    loading(btn, true); alertIn(form2, '');
    post({ action: 'verify', token: state.token, code: c }).then(function (res) {
      verifying = false; loading(btn, false);
      if (!res.ok) {
        void otp.offsetWidth; otp.classList.add('has-error');
        alertIn(form2, esc(res.error || 'That code did not work.'));
        boxes.forEach(function (b) { b.value = ''; });
        boxes[0].focus();
        return;
      }
      store(PENDING, null); store(DRAFT, null);
      state.data = res;
      bind('name', res.name);
      bind('ref_link', res.ref_link.replace(/^https?:\/\//, ''));
      $$('[data-bind-href="dashboard"]').forEach(function (a) { a.href = res.dashboard; });
      setupShare(res);
      goTo(3);
    });
  }
  form2.addEventListener('submit', function (ev) { ev.preventDefault(); submitCode(); });

  var resendBtn = $('[data-resend]', form2);
  function startCooldown(sec) {
    clearInterval(state.cooldownTimer);
    var left = sec;
    function tick() {
      if (left <= 0) { clearInterval(state.cooldownTimer); resendBtn.disabled = false; resendBtn.textContent = 'Resend code'; return; }
      resendBtn.disabled = true; resendBtn.textContent = 'Resend code in ' + left + 's'; left--;
    }
    tick(); state.cooldownTimer = setInterval(tick, 1000);
  }
  resendBtn.addEventListener('click', function () {
    loading(resendBtn, true);
    post({ action: 'resend', token: state.token }).then(function (res) {
      loading(resendBtn, false);
      if (res.ok) {
        alertIn(form2, 'A new code is on its way to ' + esc(res.email) + '.', true);
        boxes.forEach(function (b) { b.value = ''; }); boxes[0].focus();
        showDevCode(res.dev_code);
        startCooldown(res.cooldown || FF.cooldown);
      } else {
        alertIn(form2, esc(res.error || 'Could not resend the code.'));
        if (res.cooldown) startCooldown(res.cooldown);
      }
    });
  });
  $('[data-back]', form2).addEventListener('click', function () { alertIn(form2, ''); goTo(1); });

  /* ── STEP 3: share ───────────────────────────────────── */
  function setupShare(res) {
    var text = res.share_text, link = res.ref_link;
    var wa = $('[data-share="whatsapp"]'), fb = $('[data-share="facebook"]');
    wa.href = 'https://wa.me/?text=' + encodeURIComponent(text);
    fb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link);
    [wa, fb].forEach(function (a) { a.addEventListener('click', function () { markShared(a); }); });
    $$('[data-copy]').forEach(function (b) {
      b.addEventListener('click', function () {
        copy(link).then(function () { markShared(b); toast('Link copied — paste it anywhere'); });
      });
    });
    var native = $('[data-share="native"]');
    native.addEventListener('click', function () {
      if (navigator.share) {
        navigator.share({ title: 'FLY FOR TWO', text: text, url: link }).then(function () { markShared(native); }).catch(function () {});
      } else {
        copy(text).then(function () { markShared(native); toast('Message copied — paste it in any app'); });
      }
    });
  }
  function markShared(el) {
    state.shared = true;
    el.classList.add('is-done');
    var cont = $('[data-after-share]');
    if (cont) cont.hidden = false;
  }
  function copy(text) {
    function legacy() {
      var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      ta.remove();
    }
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).catch(legacy);
    legacy(); return Promise.resolve();
  }
  $$('[data-goto]').forEach(function (b) { b.addEventListener('click', function () { goTo(+b.dataset.goto); }); });

  /* ── STEP 4: confirmation ────────────────────────────── */
  function renderConfirmation() {
    var n = state.data.entries || 1;
    bind('entries', n);
    bind('plural', n === 1 ? 'y' : 'ies');
    var note = $('.ff-shared-note');
    if (note) note.hidden = !state.shared;
  }

  function toast(msg) {
    var t = document.querySelector('.toast');
    if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('is-on');
    clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('is-on'); }, 2400);
  }

  /* ── resume a saved entry that is waiting for its code ── */
  (function resume() {
    var p = load(PENDING);
    if (!p || !p.token || Date.now() - p.t > MAX_AGE) { store(PENDING, null); return; }
    post({ action: 'status', token: p.token }).then(function (res) {
      if (!res.ok) return;
      if (res.status === 'pending') {
        state.token = p.token;
        bind('email', p.email);
        startCooldown(res.cooldown || 0);
        goTo(2);
        alertIn(form2, 'Welcome back — enter the 6-digit code we emailed you, or tap the button in the email.', true);
      } else if (res.status === 'verified') {
        store(PENDING, null); store(DRAFT, null);
        form1.reset();
        if (res.signedIn) { location.reload(); return; }
        alertIn(form1, 'Your entry is already confirmed — you’re in the draw. <a href="' + (FF.login || '#') + '">Sign in to see your dashboard</a>.', true);
      } else {
        store(PENDING, null);
      }
    });
  })();
})();
