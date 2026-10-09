<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Settings;
page_header(['title' => 'How it works', 'area' => 'public', 'description' => 'How escrow, delivery, disputes and refunds work on PlugTheList.']);
?>
<main class="wrap narrow">
  <h1>How PlugTheList works</h1>
  <h2>For creatives</h2>
  <ol class="flow tall">
    <li><strong>Find a curator.</strong><span>Filter by price, audience and genre. Check proof of past work and ratings.</span></li>
    <li><strong>Send your track and brief.</strong><span>You see the full price, including our service fee, before paying.</span></li>
    <li><strong>Pay into escrow.</strong><span>Card, bank transfer or USSD through Paystack. The curator cannot touch the money yet.</span></li>
    <li><strong>The curator accepts and delivers.</strong><span>They have <?= e((string)Settings::int('accept_window_hours')) ?> hours to accept. If not, you are refunded.</span></li>
    <li><strong>You approve.</strong><span>You have <?= e((string)Settings::int('auto_release_hours')) ?> hours after delivery. Approve, or open a dispute if something is wrong.</span></li>
  </ol>
  <h2>For curators</h2>
  <ol class="flow tall">
    <li><strong>Create a listing.</strong><span>Add your link, audience size and the services you offer, with your own prices.</span></li>
    <li><strong>Prove you own it.</strong><span>We give you a short code to place in your playlist description, channel bio or group description. We check it before your listing goes live.</span></li>
    <li><strong>Accept the work you want.</strong><span>Money is already in escrow, so you never chase a payment.</span></li>
    <li><strong>Deliver and upload proof.</strong><span>A link or a screenshot showing the work is done.</span></li>
    <li><strong>Get paid.</strong><span>When approved, your earnings go to your wallet. Withdraw to your own bank account from <?= e(naira(Settings::int('min_withdrawal_kobo'))) ?>.</span></li>
  </ol>
  <h2>Honest limits</h2>
  <p>We cannot guarantee streams or placements, and curators are not allowed to promise them. On Spotify and Apple Music a booking buys a review and fair consideration. Read the <a href="<?= e(url('www', '/escrow-refunds')) ?>">escrow and refund policy</a> for the full rules.</p>
</main>
<?php
page_footer();
