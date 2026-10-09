<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\Escrow;
use PTL\RateLimiter;
use PTL\Security;

$u = Auth::require('creative');
Security::requirePost();
RateLimiter::enforce('pay_start', (string)$u['id'], 15, 3600);
$id = (int)post('id');
try {
    redirect(Escrow::startPayment($id, (int)$u['id']));
} catch (DomainException $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[pay] ' . $e->getMessage());
    flash('error', 'We could not reach Paystack. Please try again in a moment.');
}
redirect(url('app', '/order?id=' . $id));
