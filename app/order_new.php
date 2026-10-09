<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\Catalog;
use PTL\DB;
use PTL\Escrow;
use PTL\Money;
use PTL\RateLimiter;
use PTL\Security;
use PTL\Settings;
use PTL\Validator;

$u = Auth::require('creative');
$offerId = (int)qs('offer', post('offer'));
$r = DB::one("SELECT o.*, l.title AS l_title, l.platform, l.status AS l_status, l.curator_id, l.id AS lid, c.display_name AS curator_name FROM listing_offers o JOIN listings l ON l.id = o.listing_id JOIN users c ON c.id = l.curator_id WHERE o.id = ? AND o.active = 1 AND l.status = 'approved'", [$offerId]);
if ($r === null) {
    abort(404, 'That offer is no longer available.');
}
$f = Money::fees((int)$r['price_kobo'], Settings::int('buyer_fee_bps'), Settings::int('curator_fee_bps'));
$errs = [];
$v = ['track_title' => '', 'track_url' => '', 'brief' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    RateLimiter::enforce('order_new', (string)$u['id'], 20, 3600);
    $v = ['track_title' => post('track_title'), 'track_url' => post('track_url'), 'brief' => post('brief')];
    $t = Validator::name($v['track_title'], 2, 140);
    $url = Validator::url($v['track_url']);
    $brief = Validator::text($v['brief'], 0, 1500);
    if ($t === null) {
        $errs['track_title'] = 'Enter the song or project title.';
    }
    if ($url === null) {
        $errs['track_url'] = 'Paste a full https link to the song or video.';
    }
    if ($brief === null) {
        $errs['brief'] = 'Keep the brief under 1,500 characters.';
    }
    if (empty($_POST['terms'])) {
        $errs['terms'] = 'Please confirm the booking terms.';
    }
    if (!$errs) {
        try {
            $id = Escrow::create((int)$u['id'], $offerId, $t, $url, (string)$brief);
            redirect(url('app', '/order?id=' . $id));
        } catch (DomainException $e) {
            $errs['_'] = $e->getMessage();
        }
    }
}
page_header(['title' => 'Book ' . $r['l_title'], 'area' => 'creative']);
?>
<main class="wrap detail">
  <p class="crumb"><a href="<?= e(url('www', '/listing?id=' . (int)$r['lid'])) ?>">← <?= e((string)$r['l_title']) ?></a></p>
  <h1>Book: <?= e(Catalog::serviceLabel((string)$r['service'])) ?></h1>
  <?= isset($errs['_']) ? error_box([$errs['_']]) : '' ?>
  <div class="cols">
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="offer" value="<?= (int)$offerId ?>">
      <?= field('track_title', 'Song or project title', 'text', $v['track_title'], $errs['track_title'] ?? null, ['required' => true, 'maxlength' => 140]) ?>
      <?= field('track_url', 'Link to the song (https)', 'url', $v['track_url'], $errs['track_url'] ?? null, ['required' => true, 'maxlength' => 300], 'Spotify, Apple Music, YouTube, Audiomack, SoundCloud, a private link, anything the curator can open.') ?>
      <?= field('brief', 'Anything the curator should know?', 'textarea', $v['brief'], $errs['brief'] ?? null, ['rows' => 5, 'maxlength' => 1500], 'Genre, release date, what you want from the post. Optional.') ?>
      <label class="check"><input type="checkbox" name="terms" value="1"> I understand I am booking a service, not a guaranteed result, and I accept the <a href="<?= e(url('www', '/escrow-refunds')) ?>" target="_blank" rel="noopener">escrow and refund policy</a>.</label>
      <?= isset($errs['terms']) ? '<small class="err">' . e($errs['terms']) . '</small>' : '' ?>
      <button class="btn wide" type="submit">Continue to payment</button>
    </form>
    <aside class="book">
      <h2>Summary</h2>
      <table class="tbl compact">
        <tr><th>Curator</th><td><?= e((string)$r['curator_name']) ?></td></tr>
        <tr><th>Delivery</th><td>within <?= (int)$r['turnaround_days'] ?> days</td></tr>
        <tr><th>Price</th><td><?= e(naira($f['price'])) ?></td></tr>
        <tr><th>Service fee</th><td><?= e(naira($f['buyer_fee'])) ?></td></tr>
        <tr><th>You pay</th><td><strong><?= e(naira($f['total'])) ?></strong></td></tr>
      </table>
      <p class="muted small">Held in escrow. Refunded in full if the curator declines, does not accept in <?= Settings::int('accept_window_hours') ?> hours, or does not deliver.</p>
    </aside>
  </div>
</main>
<?php
page_footer();
