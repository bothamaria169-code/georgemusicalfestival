<?php
/** "LIVE" strip of recent entries and referrals. Refreshes itself every 30 seconds. */
$activity = recent_activity(15);
?>
<div class="live-ticker" id="liveTicker" data-src="<?= e(url('api/activity.php')) ?>">
  <span class="live-badge"><span class="live-dot"></span>Live</span>
  <div class="live-window">
    <div class="live-track" id="liveTrack">
      <?php if ($activity): foreach ([0, 1] as $copy): foreach ($activity as $a): ?>
        <span class="live-item is-<?= e($a['type']) ?>"><i class="fa-solid <?= $a['type'] === 'referral' ? 'fa-user-plus' : 'fa-plane-departure' ?>"></i><?= e($a['text']) ?></span>
      <?php endforeach; endforeach; else: ?>
        <span class="live-item"><i class="fa-solid fa-plane-departure"></i>Be the first to enter FLY FOR TWO — new entries appear here live</span>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
(function () {
  var box = document.getElementById('liveTicker'), track = document.getElementById('liveTrack'), last = '';
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function refresh() {
    fetch(box.dataset.src, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok || !res.items || !res.items.length) return;
      var key = JSON.stringify(res.items); if (key === last) return; last = key;
      var html = res.items.map(function (a) {
        return '<span class="live-item is-' + a.type + '"><i class="fa-solid ' + (a.type === 'referral' ? 'fa-user-plus' : 'fa-plane-departure') + '"></i>' + esc(a.text) + '</span>';
      }).join('');
      track.innerHTML = html + html;
    }).catch(function () {});
  }
  setInterval(refresh, 30000);
})();
</script>
