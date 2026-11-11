/* "Chat with us": opens the help form and sends it to api/support.php */
(function () {
  var root = document.getElementById('sup');
  if (!root) return;
  var fab = root.querySelector('.sup-fab');
  var panel = root.querySelector('.sup-panel');
  var form = root.querySelector('.sup-form');
  var err = root.querySelector('.sup-err');
  var done = root.querySelector('.sup-done');
  var send = root.querySelector('.sup-send');
  var topic = form.elements.topic;

  function open() {
    panel.hidden = false;
    root.classList.add('is-open');
    fab.setAttribute('aria-expanded', 'true');
    // Prefill from the entry form when the visitor already typed their details there.
    var n = document.getElementById('f-name'), m = document.getElementById('f-email');
    if (n && n.value && !form.elements.name.value) form.elements.name.value = n.value;
    if (m && m.value && !form.elements.email.value) form.elements.email.value = m.value;
    topic.focus();
  }
  function close() {
    panel.hidden = true;
    root.classList.remove('is-open');
    fab.setAttribute('aria-expanded', 'false');
    fab.focus();
  }
  fab.addEventListener('click', open);
  root.querySelector('.sup-x').addEventListener('click', close);
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !panel.hidden) close(); });
  topic.addEventListener('change', function () {
    root.querySelectorAll('.sup-tip').forEach(function (t) { t.hidden = t.getAttribute('data-for') !== topic.value; });
  });
  // Any link to #help opens the form.
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a[href="#help"]');
    if (a) { ev.preventDefault(); open(); }
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    err.hidden = true;
    var data = {
      csrf: root.getAttribute('data-csrf'),
      topic: topic.value,
      name: form.elements.name.value.trim(),
      email: form.elements.email.value.trim(),
      message: form.elements.message.value.trim(),
      website: form.elements.website.value,
      page: location.pathname
    };
    if (!data.topic || !data.name || !data.email || !data.message) {
      err.textContent = 'Please fill in every field.';
      err.hidden = false;
      return;
    }
    send.disabled = true;
    send.textContent = 'Sending…';
    fetch(root.getAttribute('data-api'), {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data)
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok) throw new Error(res.error || 'Something went wrong.');
      form.hidden = true;
      done.hidden = false;
      done.querySelector('p').textContent = 'Thanks, ' + data.name.split(' ')[0] + '. We\'ll reply to ' + data.email + (res.id ? ' (reference #' + res.id + ')' : '') + '.';
    }).catch(function (e) {
      err.textContent = e.message || 'Could not send. Please try again.';
      err.hidden = false;
    }).then(function () {
      send.disabled = false;
      send.textContent = 'Send message';
    });
  });
})();
