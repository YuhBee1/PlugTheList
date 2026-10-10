<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Listings;
use PTL\Settings;

$feat = Listings::search(['sort' => 'popular'], 1, 6)['rows'];
$buyer = Settings::int('buyer_fee_bps') / 100;
$cur = Settings::int('curator_fee_bps') / 100;
page_header(['title' => 'PlugTheList', 'area' => 'public', 'canonical' => url('www', '/')]);
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <h1>Book Nigeria's playlists and music communities. Pay when the work is done.</h1>
      <p class="lede">Curators set their own prices. Your money sits in escrow until your song is delivered, and goes back to you if it is not.</p>
      <p class="cta">
        <a class="btn" href="<?= e(url('www', '/browse')) ?>">Browse curators</a>
        <a class="btn ghost" href="<?= e(url('auth', '/register/curator')) ?>">I own a playlist</a>
      </p>
      <p class="muted small">Prices in naira. Pay by card, bank transfer or USSD through Paystack.</p>
    </div>
    <aside class="board" aria-label="Curators you can book now">
      <div class="board-head"><span>Open for bookings</span><a href="<?= e(url('www', '/browse')) ?>">See all</a></div>
      <?php if ($feat): ?>
        <ul class="rows"><?php foreach ($feat as $l) { echo listing_row($l); } ?></ul>
      <?php else: ?>
        <p class="empty">No curators are live yet. Own a playlist, channel or community? <a href="<?= e(url('auth', '/register/curator')) ?>">Be one of the first to list</a>.</p>
      <?php endif; ?>
    </aside>
  </div>
</section>

<section class="wrap steps">
  <h2>How a booking works</h2>
  <ol class="flow">
    <li><strong>You choose a curator</strong><span>Filter by budget, audience size and genre. Read proof of past work and ratings from earlier artists.</span></li>
    <li><strong>You pay into escrow</strong><span>Your payment is held by PlugTheList, not handed to the curator.</span></li>
    <li><strong>The curator delivers</strong><span>They accept, do the work and upload proof within the time they promised.</span></li>
    <li><strong>You approve, they get paid</strong><span>Approve it, or let the review window pass. Problem? Open a dispute and we review it. No delivery means an automatic refund.</span></li>
  </ol>
</section>

<section class="wrap split audience-grid">
  <div class="audience-card">
    <p class="audience-eyebrow">For campaign owners</p>
    <h2>For artists, labels and businesses</h2>
    <p>See the price before you talk to anyone. Every listing shows the audience, genres, turnaround and what the curator will and will not do.</p>
    <p><a class="btn" href="<?= e(url('auth', '/register/creative')) ?>">Create a creative account</a></p>
  </div>
  <div class="audience-card">
    <p class="audience-eyebrow">For curators</p>
    <h2>For playlist and community owners</h2>
    <p>Turn your audience into income without chasing payments. You choose your prices and which bookings to accept. We keep <?= e(rtrim(rtrim(number_format($cur, 2), '0'), '.')) ?>% of each job; buyers pay a <?= e(rtrim(rtrim(number_format($buyer, 2), '0'), '.')) ?>% service fee on top.</p>
    <p><a class="btn" href="<?= e(url('auth', '/register/curator')) ?>">Create a curator account</a></p>
  </div>
</section>

<section class="wrap honest">
  <h2>What we do not promise</h2>
  <p>Nobody can guarantee streams, followers or chart positions, and we will not let a curator sell that. For Spotify and Apple Music playlists you are buying a review and fair consideration, not a guaranteed add. Anyone who promises a fixed number of streams is not following the platforms' rules. <a href="<?= e(url('www', '/how-it-works')) ?>">Read how it works</a>.</p>
</section>
<?php
page_footer();
