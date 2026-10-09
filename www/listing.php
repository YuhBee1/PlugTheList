<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Catalog;
use PTL\DB;
use PTL\Listings;
use PTL\Money;
use PTL\Session;
use PTL\Settings;

$id = (int)qs('id');
$l = DB::one("SELECT l.*, u.display_name AS curator_name FROM listings l JOIN users u ON u.id = l.curator_id WHERE l.id = ? AND l.status = 'approved' AND u.status = 'active'", [$id]);
if ($l === null) {
    abort(404, 'This listing is not available.');
}
$u = Session::user();
$offers = Listings::offers($id);
$proofs = Listings::proofs($id);
$reviews = DB::all('SELECT r.rating, r.comment, r.created_at, u.display_name FROM reviews r JOIN users u ON u.id = r.creative_id WHERE r.listing_id = ? ORDER BY r.id DESC LIMIT 10', [$id]);
$bps = Settings::int('buyer_fee_bps');
$isDsp = Catalog::isDsp((string)$l['platform']);
page_header(['title' => $l['title'], 'area' => 'public', 'description' => mb_substr((string)$l['description'], 0, 150), 'canonical' => url('www', '/listing?id=' . $id)]);
?>
<main class="wrap detail">
  <p class="crumb"><a href="<?= e(url('www', '/browse')) ?>">Browse</a> / <?= e(Catalog::platformLabel((string)$l['platform'])) ?></p>
  <h1><?= e($l['title']) ?></h1>
  <p class="meta"><?= e(short_num((int)$l['followers'])) ?> followers · <?= e(str_replace(',', ', ', (string)$l['genres'])) ?> · by <?= e($l['curator_name']) ?> · <?= e(Listings::rating($l)) ?> · <?= pluralise((int)$l['orders_done'], 'job done', 'jobs done') ?> <?= $l['verified'] ? '<span class="vtag">Verified owner</span>' : '' ?></p>
  <div class="cols">
    <div>
      <h2>About this audience</h2>
      <p class="prose"><?= nl2br(e((string)$l['description'])) ?></p>
      <p><?= ext_link((string)$l['url'], 'Open the ' . Catalog::platformLabel((string)$l['platform']) . ' page') ?></p>
      <?php if ($isDsp): ?>
        <p class="note">Streaming-service playlists cannot be bought. This booking pays for a listen and honest consideration. The curator decides whether your song fits, and a placement is never guaranteed.</p>
      <?php endif; ?>

      <h2>Proof of past work</h2>
      <?php if ($proofs): ?>
        <ul class="plain"><?php foreach ($proofs as $p) { ?>
          <li><?= e($p['caption']) ?>
            <?= $p['link'] ? ' · ' . ext_link((string)$p['link'], 'View') : '' ?>
            <?= $p['file_id'] ? ' · <a href="' . e(url('www', '/file?id=' . (int)$p['file_id'])) . '" target="_blank" rel="noopener">Screenshot</a>' : '' ?></li>
        <?php } ?></ul>
      <?php else: ?><p class="muted">The curator has not added examples yet. Ratings from completed jobs appear below.</p><?php endif; ?>

      <h2>Ratings from artists</h2>
      <?php if ($reviews): foreach ($reviews as $r) { ?>
        <blockquote class="review"><strong><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></strong> <?= e((string)$r['comment']) ?><footer><?= e($r['display_name']) ?>, <?= e(fmt_d($r['created_at'])) ?></footer></blockquote>
      <?php } else: ?><p class="muted">No ratings yet.</p><?php endif; ?>
    </div>

    <aside class="book">
      <h2>Book a service</h2>
      <?php foreach ($offers as $o):
          $f = Money::fees((int)$o['price_kobo'], $bps, 0); ?>
        <div class="offer">
          <strong><?= e(Catalog::serviceLabel((string)$o['service'])) ?></strong>
          <span class="price"><?= e(naira((int)$o['price_kobo'])) ?></span>
          <small>Done within <?= (int)$o['turnaround_days'] ?> days. You pay <?= e(naira($f['total'])) ?> including the <?= e(naira($f['buyer_fee'])) ?> service fee.</small>
          <?php if ($o['details']): ?><small><?= e((string)$o['details']) ?></small><?php endif; ?>
          <?php if ($u && $u['role'] === 'creative'): ?>
            <a class="btn wide" href="<?= e(url('app', '/order_new?offer=' . (int)$o['id'])) ?>">Book this</a>
          <?php elseif ($u): ?>
            <span class="muted small">Sign in with a creative account to book.</span>
          <?php else: ?>
            <a class="btn wide" href="<?= e(url('auth', '/register/creative')) ?>">Join to book</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <p class="muted small">Your payment is held in escrow. If the curator does not accept or deliver, you are refunded.</p>
    </aside>
  </div>
</main>
<?php
page_footer();
