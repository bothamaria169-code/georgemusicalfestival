/* George Musical Festival — site interactions (no dependencies) */
(function () {
  'use strict';
  var doc = document.documentElement;

  /* Mobile nav */
  var toggle = document.getElementById('navToggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.querySelectorAll('#mainNav a').forEach(function (a) {
      a.addEventListener('click', function () { document.body.classList.remove('nav-open'); toggle.setAttribute('aria-expanded', 'false'); });
    });
  }

  /* Scroll progress + parallax */
  var bar = document.querySelector('.scroll-progress');
  var parallax = [].slice.call(document.querySelectorAll('[data-parallax]'));
  var ticking = false;
  function onScroll() {
    var max = doc.scrollHeight - innerHeight;
    if (bar) bar.style.width = (max > 0 ? (scrollY / max) * 100 : 0) + '%';
    parallax.forEach(function (el) {
      var r = el.parentElement.getBoundingClientRect();
      if (r.bottom < 0 || r.top > innerHeight) return;
      var p = (r.top + r.height / 2 - innerHeight / 2) / innerHeight;
      el.style.transform = 'translate3d(0,' + (p * -12 - 8) + '%,0)';
    });
    ticking = false;
  }
  addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(onScroll); ticking = true; } }, { passive: true });
  onScroll();

  /* Reveal on scroll */
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* Lightbox — any [data-lightbox] link; grouped by attribute value */
  var lb = document.getElementById('lightbox');
  if (lb) {
    var lbImg = lb.querySelector('img'), lbCap = lb.querySelector('figcaption');
    var group = [], idx = 0;
    function show(i) {
      idx = (i + group.length) % group.length;
      var a = group[idx];
      lbImg.src = a.getAttribute('href');
      lbImg.alt = a.dataset.caption || '';
      lbCap.textContent = a.dataset.caption || '';
    }
    function close() { lb.classList.remove('is-open'); lb.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest('[data-lightbox]');
      if (!a) return;
      ev.preventDefault();
      var name = a.dataset.lightbox;
      group = [].slice.call(document.querySelectorAll('[data-lightbox="' + name + '"]')).filter(function (x) { return x.offsetParent !== null; });
      show(group.indexOf(a));
      lb.classList.add('is-open'); lb.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
    });
    lb.querySelector('.lb-close').addEventListener('click', close);
    lb.querySelector('.lb-prev').addEventListener('click', function () { show(idx - 1); });
    lb.querySelector('.lb-next').addEventListener('click', function () { show(idx + 1); });
    lb.addEventListener('click', function (ev) { if (ev.target === lb) close(); });
    document.addEventListener('keydown', function (ev) {
      if (!lb.classList.contains('is-open')) return;
      if (ev.key === 'Escape') close();
      if (ev.key === 'ArrowLeft') show(idx - 1);
      if (ev.key === 'ArrowRight') show(idx + 1);
    });
  }

  /* Video cards — swap the poster for the player on click */
  function embedUrl(url) {
    var m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/);
    if (m) return { type: 'iframe', src: 'https://www.youtube-nocookie.com/embed/' + m[1] + '?autoplay=1&rel=0' };
    m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (m) return { type: 'iframe', src: 'https://player.vimeo.com/video/' + m[1] + '?autoplay=1' };
    return { type: 'video', src: url };
  }
  document.addEventListener('click', function (ev) {
    var card = ev.target.closest('[data-video]');
    if (!card || card.querySelector('iframe,video')) return;
    ev.preventDefault();
    var v = embedUrl(card.dataset.video), el;
    if (v.type === 'iframe') {
      el = document.createElement('iframe');
      el.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      el.allowFullscreen = true;
    } else {
      el = document.createElement('video');
      el.controls = true; el.autoplay = true; el.playsInline = true;
    }
    el.src = v.src;
    card.appendChild(el);
  });

  /* Gallery filter chips */
  var chips = document.querySelectorAll('[data-filter]');
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      chips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
      var f = chip.dataset.filter;
      document.querySelectorAll('[data-album]').forEach(function (t) {
        t.style.display = (f === '*' || t.dataset.album === f) ? '' : 'none';
      });
    });
  });

  /* Countdown: <div data-countdown="2026-10-03T12:00:00+02:00"> with [data-d],[data-h],[data-m],[data-s] */
  document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var target = new Date(el.dataset.countdown).getTime();
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
      var d = Math.max(0, target - Date.now()), s = Math.floor(d / 1000);
      var set = function (k, v) { var x = el.querySelector('[data-' + k + ']'); if (x) x.textContent = v; };
      set('d', Math.floor(s / 86400)); set('h', pad(Math.floor(s % 86400 / 3600)));
      set('m', pad(Math.floor(s % 3600 / 60))); set('s', pad(s % 60));
    }
    tick(); setInterval(tick, 1000);
  });

  /* AJAX forms: <form data-ajax> posting JSON-returning endpoints */
  document.querySelectorAll('form[data-ajax]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('[type=submit]'), msg = form.querySelector('.form-msg');
      if (!msg) { msg = document.createElement('div'); msg.className = 'form-msg'; form.appendChild(msg); }
      btn && (btn.disabled = true);
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          msg.className = 'form-msg ' + (res.ok ? 'ok' : 'err');
          msg.textContent = res.message || res.error || (res.ok ? 'Done.' : 'Something went wrong.');
          if (res.ok) form.reset();
        })
        .catch(function () { msg.className = 'form-msg err'; msg.textContent = 'Network error — please try again.'; })
        .finally(function () { btn && (btn.disabled = false); });
    });
  });
})();
