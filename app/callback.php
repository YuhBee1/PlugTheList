<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Escrow;
use PTL\Paystack;
use PTL\RateLimiter;

                                                                                                                                        
$u = Auth::require('creative');
RateLimiter::enforce('pay_cb', (string)$u['id'], 30, 600);
$ref = qs('reference', qs('trxref'));
$p = preg_match('/^ptl_[a-f0-9]{20}$/', $ref) ? DB::one('SELECT p.*, o.creative_id FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.reference = ?', [$ref]) : null;
if ($p === null || (int)$p['creative_id'] !== (int)$u['id']) {
    abort(404, 'We could not find that payment.');
}
try {
    $v = Paystack::verify($ref);
    if ($v !== null && $v['status'] === 'success') {
        Escrow::settlePayment($ref, $v['amount'], $v['currency'], $v['channel']);
        flash('ok', 'Payment received. Your money is in escrow and the curator has been told.');
    } elseif ($v !== null && in_array($v['status'], ['failed', 'abandoned', 'reversed'], true)) {
        flash('error', 'The payment did not go through. You were not charged. Try again.');
    } else {
        flash('info', 'We are still waiting for confirmation from your bank. This page will update when it arrives.');
    }
} catch (Throwable $e) {
    error_log('[callback] ' . $e->getMessage());
    flash('info', 'We are confirming your payment. Check back in a minute.');
}
redirect(url('app', '/order?id=' . (int)$p['order_id']));
