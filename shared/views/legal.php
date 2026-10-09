<?php
declare(strict_types=1);

use PTL\Settings;

function legal_page(string $slug): void
{
    $titles = ['terms' => 'Terms of service', 'privacy' => 'Privacy notice', 'acceptable-use' => 'Acceptable use policy', 'escrow-refunds' => 'Escrow and refund policy', 'cookies' => 'Cookie notice'];
    if (!isset($titles[$slug])) {
        abort(404);
    }
    $fee = static fn(string $k): string => rtrim(rtrim(number_format(Settings::int($k) / 100, 2), '0'), '.') . '%';
    $v = [
        'buyer_fee' => $fee('buyer_fee_bps'), 'curator_fee' => $fee('curator_fee_bps'),
        'accept_h' => (string)Settings::int('accept_window_hours'), 'review_h' => (string)Settings::int('auto_release_hours'),
        'grace_h' => (string)Settings::int('non_delivery_grace_hours'), 'min_price' => naira(Settings::int('min_listing_price_kobo')),
        'min_wd' => naira(Settings::int('min_withdrawal_kobo')),
        'support' => (string)env('SUPPORT_EMAIL', 'support@' . base_domain()),
        'company' => (string)env('COMPANY_NAME', 'Paramount Digital Services'),
        'address' => (string)env('COMPANY_ADDRESS', 'Uyo, Akwa Ibom State, Nigeria'),
        'updated' => (string)env('LEGAL_UPDATED', '8 October 2026'),
    ];
    page_header(['title' => $titles[$slug], 'area' => 'public', 'canonical' => url('www', '/' . $slug)]);
    echo '<main class="wrap narrow legal">';
    if (env('LEGAL_DRAFT', '1') === '1') {
        echo '<p class="draftbar"><strong>Draft.</strong> This text was prepared as a starting point and must be reviewed by a Nigerian lawyer before launch.</p>';
    }
    echo '<h1>' . e($titles[$slug]) . '</h1><p class="muted">Last updated ' . e($v['updated']) . '</p>';
    include PTL_SHARED . '/legal/' . $slug . '.php';
    echo '</main>';
    page_footer();
}
