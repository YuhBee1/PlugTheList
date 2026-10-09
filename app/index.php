<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Notifier;

$u = Auth::require('creative');
$counts = [];
foreach (DB::all('SELECT status, COUNT(*) AS n FROM orders WHERE creative_id = ? GROUP BY status', [$u['id']]) as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$active = DB::all("SELECT * FROM orders WHERE creative_id = ? AND status IN ('awaiting_payment','paid','in_progress','delivered','disputed') ORDER BY id DESC LIMIT 10", [$u['id']]);
$needs = (int)($counts['delivered'] ?? 0);
page_header(['title' => 'Dashboard', 'area' => 'creative']);
?>
<main class="wrap">
  <h1>Hi, <?= e((string)($u['display_name'] ?: $u['full_name'])) ?></h1>
  <div class="stats">
    <div><strong><?= (int)array_sum(array_intersect_key($counts, array_flip(['paid', 'in_progress', 'delivered', 'disputed']))) ?></strong><span>in progress</span></div>
    <div><strong><?= $needs ?></strong><span>waiting for your approval</span></div>
    <div><strong><?= (int)($counts['completed'] ?? 0) ?></strong><span>completed</span></div>
    <div><strong><?= Notifier::unread((int)$u['id']) ?></strong><span><a href="<?= e(url('app', '/notifications')) ?>">unread updates</a></span></div>
  </div>
  <p><a class="btn" href="<?= e(url('www', '/browse')) ?>">Find a curator</a></p>
  <h2>Active bookings</h2>
  <?php if (!$active): ?>
    <p class="empty">Nothing in progress. Browse curators and book your first slot.</p>
  <?php else: ?>
    <table class="tbl wide"><thead><tr><th>Ref</th><th>Song</th><th>Listing</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($active as $o): ?>
      <tr><td><a href="<?= e(url('app', '/order?id=' . (int)$o['id'])) ?>"><?= e((string)$o['ref']) ?></a></td><td><?= e((string)$o['track_title']) ?></td><td><?= e((string)$o['listing_title']) ?></td><td><?= status_badge((string)$o['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</main>
<?php
page_footer();
