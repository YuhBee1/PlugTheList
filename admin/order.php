<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\Escrow;
use PTL\Money;
use PTL\OrderState;
use PTL\Security;

$u = Auth::require('admin');
$id = (int)qs('id', post('id'));
$o = DB::one('SELECT o.*, a.display_name AS creative_name, a.email AS creative_email, c.display_name AS curator_name, c.email AS curator_email FROM orders o JOIN users a ON a.id = o.creative_id JOIN users c ON c.id = o.curator_id WHERE o.id = ?', [$id]);
if ($o === null) {
    abort(404);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    try {
        $share = Money::parseNaira(post('award'));
        $note = PTL\Validator::text(post('note'), 10, 500);
        if ($share === null || $note === null || $share > (int)$o['price_kobo']) {
            throw new DomainException('Enter the amount awarded to the curator (0 to ' . naira((int)$o['price_kobo']) . ') and a reason of at least 10 characters.');
        }
        Escrow::resolve((int)$u['id'], $id, $share, $note);
        flash('ok', 'Decision recorded and both sides notified.');
    } catch (DomainException $e) {
        flash('error', $e->getMessage());
    }
    redirect(url('admin', '/order?id=' . $id));
}
$events = DB::all('SELECT e.*, u.display_name FROM order_events e LEFT JOIN users u ON u.id = e.user_id WHERE order_id = ? ORDER BY e.id', [$id]);
$msgs = DB::all('SELECT m.*, u.display_name FROM order_messages m JOIN users u ON u.id = m.user_id WHERE order_id = ? ORDER BY m.id', [$id]);
$pays = DB::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]);
page_header(['title' => 'Order ' . $o['ref'], 'area' => 'admin']);
echo '<main class="wrap detail"><p class="crumb"><a href="' . e(url('admin', '/orders')) . '">Orders</a> / ' . e((string)$o['ref']) . '</p><h1>' . e((string)$o['track_title']) . ' ' . status_badge((string)$o['status']) . '</h1>';
echo '<p>Creative: ' . e((string)$o['creative_name']) . ' (' . e((string)$o['creative_email']) . ') · Curator: ' . e((string)$o['curator_name']) . ' (' . e((string)$o['curator_email']) . ')</p>';
echo '<p>Song: ' . ext_link((string)$o['track_url']) . ($o['delivery_url'] ? ' · Delivery: ' . ext_link((string)$o['delivery_url']) : '') . ($o['delivery_file_id'] ? ' · <a href="' . e(url('www', '/file?id=' . (int)$o['delivery_file_id'])) . '" target="_blank" rel="noopener">Screenshot</a>' : '') . '</p>';
echo '<table class="tbl compact"><tr><th>Price</th><td>' . e(naira((int)$o['price_kobo'])) . '</td><th>Buyer fee</th><td>' . e(naira((int)$o['buyer_fee_kobo'])) . '</td><th>Curator fee</th><td>' . e(naira((int)$o['curator_fee_kobo'])) . '</td><th>Total paid</th><td>' . e(naira((int)$o['total_kobo'])) . '</td></tr></table>';
if ($o['brief']) {
    echo '<h2>Brief</h2><p class="prose">' . nl2br(e((string)$o['brief'])) . '</p>';
}
if ($o['dispute_reason']) {
    echo '<h2>Dispute</h2><p class="prose">' . nl2br(e((string)$o['dispute_reason'])) . '</p>';
}
if ($o['status'] === OrderState::DISPUTED) {
    echo '<section class="panel"><h2>Decide</h2><p class="muted">Enter how much of the booking price (' . e(naira((int)$o['price_kobo'])) . ') the curator keeps. Fees are charged only on that part. The rest is refunded to the creative.</p><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
        . field('award', 'Award to curator (₦)', 'text', '', null, ['required' => true, 'inputmode' => 'decimal'], '0 = full refund to the creative. ' . naira((int)$o['price_kobo']) . ' = full payment to the curator.')
        . field('note', 'Reason (both sides will see this)', 'textarea', '', null, ['rows' => 3, 'required' => true, 'maxlength' => 500]) . '<button class="btn danger" type="submit">Record decision</button></form></section>';
}
echo '<h2>Messages</h2><ul class="msgs">';
foreach ($msgs as $m) {
    echo '<li class="theirs"><strong>' . e((string)$m['display_name']) . '</strong> <small>' . e(fmt_dt((string)$m['created_at'])) . '</small><p>' . nl2br(e((string)$m['body'])) . '</p></li>';
}
echo '</ul><h2>Timeline</h2><ul class="timeline">';
foreach ($events as $ev) {
    echo '<li><strong>' . e((string)$ev['event']) . '</strong> ' . e((string)$ev['display_name']) . ' <small>' . e(fmt_dt((string)$ev['created_at'])) . '</small> ' . e((string)$ev['note']) . '</li>';
}
echo '</ul><h2>Payments</h2><ul class="plain">';
foreach ($pays as $p) {
    echo '<li><code>' . e((string)$p['reference']) . '</code> ' . e(naira((int)$p['amount_kobo'])) . ' ' . status_badge((string)$p['status']) . '</li>';
}
echo '</ul></main>';
page_footer();
