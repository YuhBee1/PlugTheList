<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Ledger;
use PTL\Money;
use PTL\Paystack;
use PTL\Security;
use PTL\Settings;
use PTL\Wallet;

$u = Auth::require('curator');
$uid = (int)$u['id'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    $act = post('action');
    if ($act === 'bank') {
                                                             
        if (!password_verify((string)($_POST['current'] ?? ''), (string)DB::val('SELECT password_hash FROM users WHERE id = ?', [$uid]))) {
            flash('error', 'Your password was not right.');
        } else {
            $err = Wallet::saveBank($uid, post('bank_code'), preg_replace('/\D/', '', post('account_number')) ?? '');
            flash($err ? 'error' : 'ok', $err ?? 'Bank account saved.');
        }
    } elseif ($act === 'withdraw') {
        $k = Money::parseNaira(post('amount'));
        if ($k === null) {
            flash('error', 'Enter an amount in naira.');
        } else {
            $err = Wallet::request($uid, $k);
            flash($err ? 'error' : 'ok', $err ?? (Settings::int('withdrawals_need_approval') ? 'Withdrawal requested. We approve payouts within one working day.' : 'Withdrawal on its way.'));
        }
    }
    redirect(url('curator', '/wallet'));
}
$bal = Ledger::userBalance($uid);
$bank = Wallet::bank($uid);
$wds = DB::all('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$uid]);
$earn = DB::all("SELECT ref, track_title, price_kobo, curator_fee_kobo, completed_at FROM orders WHERE curator_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 20", [$uid]);
$banks = Paystack::banks();
page_header(['title' => 'Wallet', 'area' => 'curator']);
?>
<main class="wrap">
  <h1>Wallet</h1>
  <div class="stats"><div><strong><?= e(naira($bal)) ?></strong><span>available</span></div><div><strong><?= e(naira(Settings::int('min_withdrawal_kobo'))) ?></strong><span>minimum withdrawal</span></div></div>
  <div class="cols">
    <section class="panel">
      <h2>Withdraw</h2>
      <?php if (!$bank): ?>
        <p class="muted">Add a bank account first.</p>
      <?php else: ?>
        <p>To <?= e((string)$bank['account_name']) ?>, <?= e((string)$bank['bank_name']) ?> ending <?= e((string)$bank['last4']) ?>.</p>
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><?= field('amount', 'Amount (₦)', 'text', '', null, ['inputmode' => 'decimal', 'required' => true, 'placeholder' => '10,000']) ?><button class="btn" type="submit">Request withdrawal</button></form>
      <?php endif; ?>
    </section>
    <section class="panel">
      <h2>Bank account</h2>
      <p class="muted small">Must be in your own name. Payouts to other names are refused.</p>
      <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="bank">
        <div class="field"><label for="f_bank_code">Bank</label><select id="f_bank_code" name="bank_code" required><option value="">Choose your bank</option><?php foreach ($banks as $b): ?><option value="<?= e($b['code']) ?>"<?= $bank && $bank['bank_code'] === $b['code'] ? ' selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <?= field('account_number', 'Account number (10 digits)', 'text', '', null, ['inputmode' => 'numeric', 'maxlength' => 10, 'required' => true, 'autocomplete' => 'off']) ?>
        <?= field('current', 'Your password', 'password', '', null, ['autocomplete' => 'current-password', 'required' => true]) ?>
        <button class="btn ghost" type="submit"><?= $bank ? 'Replace bank account' : 'Save bank account' ?></button>
      </form>
    </section>
  </div>
  <h2>Withdrawals</h2>
  <?php if (!$wds): ?><p class="empty">None yet.</p><?php else: ?>
    <table class="tbl wide"><thead><tr><th>Date</th><th>Amount</th><th>To</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($wds as $w): ?><tr><td><?= e(fmt_dt((string)$w['created_at'])) ?></td><td><?= e(naira((int)$w['amount_kobo'])) ?></td><td><?= e((string)$w['bank_name']) ?> ···<?= e((string)$w['last4']) ?></td><td><?= status_badge((string)$w['status'] === 'paid' ? 'completed' : (string)$w['status'], ucfirst((string)$w['status'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  <h2>Earnings</h2>
  <?php if (!$earn): ?><p class="empty">Completed orders will appear here.</p><?php else: ?>
    <table class="tbl wide"><thead><tr><th>Order</th><th>Song</th><th>Price</th><th>Commission</th><th>You got</th></tr></thead><tbody>
    <?php foreach ($earn as $o): ?><tr><td><?= e((string)$o['ref']) ?></td><td><?= e((string)$o['track_title']) ?></td><td><?= e(naira((int)$o['price_kobo'])) ?></td><td>−<?= e(naira((int)$o['curator_fee_kobo'])) ?></td><td><?= e(naira((int)$o['price_kobo'] - (int)$o['curator_fee_kobo'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</main>
<?php
page_footer();
