<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Audit;
use PTL\Auth;
use PTL\Money;
use PTL\Security;
use PTL\Settings;
use PTL\Validator;

$u = Auth::require('admin');
                                                             
$defs = [
    'buyer_fee_bps' => ['Creative service fee (%)', 'pct'], 'curator_fee_bps' => ['Curator commission (%)', 'pct'],
    'min_listing_price_kobo' => ['Minimum listing price (₦)', 'naira'], 'max_listing_price_kobo' => ['Maximum listing price (₦)', 'naira'],
    'min_withdrawal_kobo' => ['Minimum withdrawal (₦)', 'naira'], 'withdrawal_fee_kobo' => ['Withdrawal fee (₦)', 'naira'],
    'withdrawals_need_approval' => ['Admin must approve withdrawals (1 = yes, 0 = no)', 'bool'],
    'accept_window_hours' => ['Hours curator has to accept', 'int'], 'auto_release_hours' => ['Hours creative has to approve after delivery', 'int'],
    'non_delivery_grace_hours' => ['Grace hours after due date before auto-refund', 'int'], 'unpaid_expiry_hours' => ['Hours before an unpaid order expires', 'int'],
    'guaranteed_placement_platforms' => ['Platforms that may sell guaranteed features (comma list). Spotify and Apple Music are always review-only.', 'csv'],
];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    $errs = [];
    $new = [];
    foreach ($defs as $k => [$label, $kind]) {
        $raw = post($k);
        $val = match ($kind) {
            'pct' => preg_match('/^\d{1,2}(\.\d{1,2})?$/', $raw) && (float)$raw <= 30 ? (string)(int)round((float)$raw * 100) : null,
            'naira' => ($n = Money::parseNaira($raw)) !== null ? (string)$n : null,
            'int' => ($n = Validator::intRange($raw, 1, 720)) !== null ? (string)$n : null,
            'bool' => in_array($raw, ['0', '1'], true) ? $raw : null,
            default => preg_match('/^[a-z_,]{0,200}$/', $raw) ? $raw : null,
        };
        if ($val === null) {
            $errs[] = $label . ' is not valid.';
        } else {
            $new[$k] = $val;
        }
    }
    if (!$errs && (int)$new['min_listing_price_kobo'] > (int)$new['max_listing_price_kobo']) {
        $errs[] = 'Minimum price cannot be above the maximum.';
    }
    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        foreach ($new as $k => $v) {
            if (Settings::get($k) !== $v) {
                Audit::log((int)$u['id'], 'setting_changed', 'setting', null, ['key' => $k, 'from' => Settings::get($k), 'to' => $v]);
                Settings::set($k, $v);
            }
        }
        flash('ok', 'Settings saved. Existing orders keep the fees they were booked with.');
    }
    redirect(url('admin', '/settings'));
}
page_header(['title' => 'Settings', 'area' => 'admin']);
echo '<main class="wrap narrow"><h1>Settings</h1><form method="post" class="form">' . csrf_field();
foreach ($defs as $k => [$label, $kind]) {
    $v = Settings::get($k);
    $show = match ($kind) { 'pct' => rtrim(rtrim(number_format((int)$v / 100, 2, '.', ''), '0'), '.'), 'naira' => (string)((int)$v / 100), default => $v };
    echo field($k, $label, 'text', (string)$show);
}
echo '<button class="btn" type="submit">Save settings</button></form></main>';
page_footer();
