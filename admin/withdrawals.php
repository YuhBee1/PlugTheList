<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Security;
use PTL\Wallet;

$u = Auth::require('admin');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    $id = (int)post('id');
    $a = post('action');
    $err = null;
    if ($a === 'approve') {
        $err = Wallet::approve((int)$u['id'], $id);
    } elseif ($a === 'reject') {
        $err = Wallet::reject((int)$u['id'], $id, mb_substr(post('note'), 0, 300));
    } elseif ($a === 'mark_paid') {                                      
        Wallet::complete($id);
        PTL\Audit::log((int)$u['id'], 'withdraw_marked_paid', 'withdrawal', $id);
    } elseif ($a === 'mark_failed') {
        Wallet::fail($id, 'Transfer failed (confirmed by admin).');
        PTL\Audit::log((int)$u['id'], 'withdraw_marked_failed', 'withdrawal', $id);
    }
    flash($err ? 'error' : 'ok', $err ?? 'Done.');
    redirect(url('admin', '/withdrawals'));
}
$rows = DB::all("SELECT w.*, u.display_name, u.email FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY (w.status IN ('requested','processing')) DESC, w.id DESC LIMIT 100");
page_header(['title' => 'Withdrawals', 'area' => 'admin']);
echo '<main class="wrap"><h1>Withdrawals</h1><table class="tbl wide"><thead><tr><th>Date</th><th>Curator</th><th>Amount</th><th>To</th><th>Status</th><th></th></tr></thead><tbody>';
foreach ($rows as $w) {
    $form = static fn(string $act, string $label, string $cls = 'btn small', string $extra = '') => '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$w['id'] . '"><input type="hidden" name="action" value="' . $act . '">' . $extra . '<button class="' . $cls . '" type="submit">' . $label . '</button></form>';
    $btns = '';
    if ($w['status'] === 'requested') {
        $btns = $form('approve', 'Approve and pay') . $form('reject', 'Reject', 'btn small ghost', '<input name="note" placeholder="Reason" maxlength="300">');
    } elseif ($w['status'] === 'processing') {
        $btns = $form('mark_paid', 'Mark paid') . $form('mark_failed', 'Mark failed', 'btn small ghost');
    }
    echo '<tr><td>' . e(fmt_dt((string)$w['created_at'])) . '</td><td>' . e((string)$w['display_name']) . '<br><small>' . e((string)$w['email']) . '</small></td><td>' . e(naira((int)$w['amount_kobo'])) . '</td><td>' . e((string)$w['account_name']) . '<br><small>' . e((string)$w['bank_name']) . ' ···' . e((string)$w['last4']) . '</small></td><td>' . status_badge((string)$w['status']) . '</td><td>' . $btns . '</td></tr>';
}
echo '</tbody></table></main>';
page_footer();
