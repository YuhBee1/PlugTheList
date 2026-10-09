<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Ledger;

$u = Auth::require('admin');
$k = static fn(string $sql, array $p = []) => (int)DB::val($sql, $p);
[$escL, $escE] = Ledger::escrowCheck();
$drift = Ledger::drift();
page_header(['title' => 'Admin overview', 'area' => 'admin']);
?>
<main class="wrap">
  <h1>Overview</h1>
  <div class="stats">
    <div><strong><?= $k("SELECT COUNT(*) FROM listings WHERE status = 'pending'") ?></strong><span><a href="<?= e(url('admin', '/listings?status=pending')) ?>">listings to review</a></span></div>
    <div><strong><?= $k("SELECT COUNT(*) FROM orders WHERE status = 'disputed'") ?></strong><span><a href="<?= e(url('admin', '/disputes')) ?>">open disputes</a></span></div>
    <div><strong><?= $k("SELECT COUNT(*) FROM withdrawals WHERE status IN ('requested','processing')") ?></strong><span><a href="<?= e(url('admin', '/withdrawals')) ?>">withdrawals pending</a></span></div>
    <div><strong><?= e(naira(Ledger::balance('escrow'))) ?></strong><span>held in escrow</span></div>
    <div><strong><?= e(naira(Ledger::balance('platform'))) ?></strong><span>platform earnings</span></div>
    <div><strong><?= $k("SELECT COUNT(*) FROM users WHERE role = 'curator'") ?> / <?= $k("SELECT COUNT(*) FROM users WHERE role = 'creative'") ?></strong><span>curators / creatives</span></div>
    <div><strong><?= $k("SELECT COUNT(*) FROM orders WHERE status = 'completed'") ?></strong><span>completed orders</span></div>
    <div><strong><?= e(naira($k("SELECT COALESCE(SUM(price_kobo),0) FROM orders WHERE status = 'completed'"))) ?></strong><span>booking value completed</span></div>
  </div>
  <h2>Health checks</h2>
  <ul class="plain">
    <li><?= status_badge($drift === 0 ? 'completed' : 'disputed', $drift === 0 ? 'OK' : 'PROBLEM') ?> Ledger balances to zero (drift <?= e((string)$drift) ?>)</li>
    <li><?= status_badge($escL === $escE ? 'completed' : 'disputed', $escL === $escE ? 'OK' : 'PROBLEM') ?> Escrow account <?= e(naira($escL)) ?> equals live orders <?= e(naira($escE)) ?></li>
    <li><?= status_badge(PTL\Paystack::mock() ? 'disputed' : 'completed', PTL\Paystack::mock() ? 'MOCK' : 'LIVE') ?> Paystack mode</li>
    <li><?= status_badge((int)(DB::val("SELECT COUNT(*) FROM audit_logs WHERE action = 'refund_needs_manual' AND created_at > ?", [date('Y-m-d H:i:s', time() - 86400 * 7)])) === 0 ? 'completed' : 'disputed', 'Refunds') ?> Manual refunds needed in the last 7 days: <?= $k("SELECT COUNT(*) FROM audit_logs WHERE action = 'refund_needs_manual' AND created_at > ?", [date('Y-m-d H:i:s', time() - 86400 * 7)]) ?></li>
  </ul>
</main>
<?php
page_footer();
