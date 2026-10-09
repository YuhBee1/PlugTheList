<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Ledger;

$u = Auth::require('curator');
$bal = Ledger::userBalance((int)$u['id']);
$pending = (int)DB::val("SELECT COALESCE(SUM(price_kobo - curator_fee_kobo),0) FROM orders WHERE curator_id = ? AND status IN ('paid','in_progress','delivered','disputed')", [$u['id']]);
$todo = DB::all("SELECT * FROM orders WHERE curator_id = ? AND status IN ('paid','in_progress') ORDER BY (status = 'paid') DESC, id DESC LIMIT 10", [$u['id']]);
$nListings = (int)DB::val('SELECT COUNT(*) FROM listings WHERE curator_id = ?', [$u['id']]);
page_header(['title' => 'Dashboard', 'area' => 'curator']);
?>
<main class="wrap">
  <h1>Hi, <?= e((string)($u['display_name'] ?: $u['full_name'])) ?></h1>
  <div class="stats">
    <div><strong><?= e(naira($bal)) ?></strong><span><a href="<?= e(url('curator', '/wallet')) ?>">available to withdraw</a></span></div>
    <div><strong><?= e(naira($pending)) ?></strong><span>in escrow for you</span></div>
    <div><strong><?= count($todo) ?></strong><span>need action</span></div>
    <div><strong><?= $nListings ?></strong><span><a href="<?= e(url('curator', '/listings')) ?>">listings</a></span></div>
  </div>
  <?php if ($nListings === 0): ?>
    <p class="note">You have no listings yet. <a class="btn" href="<?= e(url('curator', '/listing')) ?>">Create your first listing</a></p>
  <?php endif; ?>
  <h2>Needs your action</h2>
  <?php if (!$todo): ?><p class="empty">Nothing waiting. New paid bookings show up here.</p><?php else: ?>
    <table class="tbl wide"><thead><tr><th>Ref</th><th>Song</th><th>You earn</th><th>Status</th><th>Deadline</th></tr></thead><tbody>
    <?php foreach ($todo as $o): ?>
      <tr><td><a href="<?= e(url('curator', '/order?id=' . (int)$o['id'])) ?>"><?= e((string)$o['ref']) ?></a></td><td><?= e((string)$o['track_title']) ?></td><td><?= e(naira((int)$o['price_kobo'] - (int)$o['curator_fee_kobo'])) ?></td><td><?= status_badge((string)$o['status']) ?></td><td><?= e($o['status'] === 'paid' ? 'Accept ' . until((string)$o['accept_by']) : 'Deliver ' . until((string)$o['due_at'])) ?></td></tr>
    <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</main>
<?php
page_footer();
