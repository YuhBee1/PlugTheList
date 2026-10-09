<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Settings;
use PTL\Money;

$b = Settings::int('buyer_fee_bps');
$c = Settings::int('curator_fee_bps');
$ex = Money::fees(2000000, $b, $c);
$pct = static fn(int $bps): string => rtrim(rtrim(number_format($bps / 100, 2), '0'), '.') . '%';
page_header(['title' => 'Fees', 'area' => 'public', 'description' => 'Simple fees on both sides: a service fee for creatives and a commission for curators.']);
?>
<main class="wrap narrow">
  <h1>Fees</h1>
  <p class="lede">Both sides pay a small fee, shown before you commit. There are no listing fees and no monthly fees.</p>
  <table class="tbl">
    <tr><th>Creatives (buyers)</th><td><?= e($pct($b)) ?> service fee added to the curator's price</td></tr>
    <tr><th>Curators (sellers)</th><td><?= e($pct($c)) ?> commission taken from the curator's price</td></tr>
    <tr><th>Minimum listing price</th><td><?= e(naira(Settings::int('min_listing_price_kobo'))) ?></td></tr>
    <tr><th>Minimum withdrawal</th><td><?= e(naira(Settings::int('min_withdrawal_kobo'))) ?><?= Settings::int('withdrawal_fee_kobo') > 0 ? ' (payout fee ' . e(naira(Settings::int('withdrawal_fee_kobo'))) . ')' : ', no payout fee' ?></td></tr>
  </table>
  <h2>Example: a <?= e(naira($ex['price'])) ?> booking</h2>
  <table class="tbl">
    <tr><th>Artist pays</th><td><?= e(naira($ex['total'])) ?> (<?= e(naira($ex['price'])) ?> + <?= e(naira($ex['buyer_fee'])) ?> service fee)</td></tr>
    <tr><th>Curator receives</th><td><?= e(naira($ex['curator_net'])) ?> (<?= e(naira($ex['price'])) ?> − <?= e(naira($ex['curator_fee'])) ?> commission)</td></tr>
    <tr><th>PlugTheList keeps</th><td><?= e(naira($ex['platform'])) ?></td></tr>
  </table>
  <p class="muted">Card and bank charges from Paystack are covered by these fees. If a booking is refunded in full, the creative gets back everything they paid, including the service fee.</p>
</main>
<?php
page_footer();
