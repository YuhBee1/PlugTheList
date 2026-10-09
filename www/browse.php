<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Catalog;
use PTL\Listings;
use PTL\RateLimiter;

RateLimiter::enforce('browse', client_ip(), 120, 300);
$f = [];
foreach (['platform', 'genre', 'q', 'max_budget', 'min_followers', 'sort'] as $k) {
    $f[$k] = mb_substr(qs($k), 0, 60);
}
$page = max(1, min(500, (int)qs('page', '1')));
$res = Listings::search($f, $page, 12);
$plat = [];
foreach (Catalog::platforms() as $s => $p) {
    $plat[$s] = $p[0];
}
$genres = array_combine(Catalog::GENRES, Catalog::GENRES);
$base = url('www', '/browse') . '?' . http_build_query(array_filter($f, static fn($v) => $v !== ''));
page_header(['title' => 'Browse curators', 'area' => 'public', 'description' => 'Compare playlists, channels and communities by price, audience and proof of past work.']);
?>
<main class="wrap">
  <h1>Browse curators</h1>
  <form method="get" class="filters" action="<?= e(url('www', '/browse')) ?>">
    <?= field('q', 'Search', 'search', $f['q'], null, ['maxlength' => 60]) ?>
    <?= select_field('platform', 'Platform', $plat, $f['platform'], null, 'Any platform') ?>
    <?= select_field('genre', 'Genre', $genres, $f['genre'], null, 'Any genre') ?>
    <?= field('max_budget', 'Budget (₦, up to)', 'text', $f['max_budget'], null, ['inputmode' => 'numeric', 'placeholder' => '50,000']) ?>
    <?= field('min_followers', 'Audience at least', 'text', $f['min_followers'], null, ['inputmode' => 'numeric', 'placeholder' => '10,000']) ?>
    <?= select_field('sort', 'Sort by', ['popular' => 'Most booked', 'price_asc' => 'Lowest price', 'followers' => 'Biggest audience', 'rating' => 'Best rated'], $f['sort']) ?>
    <div class="field"><button class="btn" type="submit">Apply</button></div>
  </form>
  <p class="muted"><?= pluralise($res['total'], 'curator', 'curators') ?> found</p>
  <?php if ($res['rows']): ?>
    <ul class="rows"><?php foreach ($res['rows'] as $l) { echo listing_row($l); } ?></ul>
    <?= paginate($res['total'], $page, 12, $base) ?>
  <?php else: ?>
    <p class="empty">Nothing matches those filters. Try a higher budget or fewer filters.</p>
  <?php endif; ?>
</main>
<?php
page_footer();
