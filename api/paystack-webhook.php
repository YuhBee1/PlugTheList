<?php
declare(strict_types=1);

define('PTL_NO_SESSION', true);
define('PTL_API', true);
require __DIR__ . '/../shared/bootstrap.php';

use PTL\Audit;
use PTL\DB;
use PTL\Escrow;
use PTL\Notifier;
use PTL\Paystack;
use PTL\RateLimiter;
use PTL\Security;
use PTL\Wallet;

Security::apiHeaders();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false], 405);
}
RateLimiter::enforce('webhook_ip', client_ip(), 600, 60);
$allow = trim((string)env('PAYSTACK_IPS', ''));
if ($allow !== '' && !in_array(client_ip(), array_map('trim', explode(',', $allow)), true)) {
    json_out(['ok' => false], 403);
}
$raw = file_get_contents('php://input', false, null, 0, 65536) ?: '';
if (!Paystack::signatureValid($raw, (string)($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''))) {
    Audit::log(null, 'webhook_bad_signature', 'webhook', null, ['ip' => client_ip()]);
    json_out(['ok' => false], 401);
}
$e = json_decode($raw, true);
if (!is_array($e) || !isset($e['event'], $e['data']) || !is_array($e['data'])) {
    json_out(['ok' => false], 400);
}
$d = $e['data'];
$event = (string)$e['event'];
$key = $event . ':' . (string)($d['reference'] ?? $d['id'] ?? sha1($raw));
$new = DB::insertIgnore('webhook_events', ['provider' => 'paystack', 'event_key' => $key, 'status' => 'received']);
if (!$new) {
    $st = (string)DB::val('SELECT status FROM webhook_events WHERE provider = ? AND event_key = ?', ['paystack', $key]);
    if ($st === 'done') {
        json_out(['ok' => true, 'duplicate' => true]);
    }
}
try {
    switch ($event) {
        case 'charge.success':
            Escrow::settlePayment((string)($d['reference'] ?? ''), (int)($d['amount'] ?? 0), (string)($d['currency'] ?? ''), (string)($d['channel'] ?? ''));
            break;
        case 'transfer.success':
        case 'transfer.failed':
        case 'transfer.reversed':
            $w = DB::one('SELECT id FROM withdrawals WHERE transfer_ref = ?', [(string)($d['reference'] ?? '')]);
            if ($w) {
                $event === 'transfer.success' ? Wallet::complete((int)$w['id']) : Wallet::fail((int)$w['id'], 'Transfer ' . substr($event, 9));
            }
            break;
        case 'refund.failed':
            Notifier::admins('Paystack refund failed', 'Refund for ' . (string)($d['transaction_reference'] ?? '?') . ' failed. Refund it manually.', '');
            break;
        default:
            break;                                         
    }
    DB::update('webhook_events', ['status' => 'done'], 'provider = ? AND event_key = ?', ['paystack', $key]);
    json_out(['ok' => true]);
} catch (Throwable $ex) {
    error_log('[webhook] ' . $ex->getMessage());
    DB::update('webhook_events', ['status' => 'error'], 'provider = ? AND event_key = ?', ['paystack', $key]);
    json_out(['ok' => false], 500);                    
}
