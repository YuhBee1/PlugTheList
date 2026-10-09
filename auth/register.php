<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Session;

if (Session::user()) {
    redirect(role_home((string)Session::user()['role']));
}
page_header(['title' => 'Join PlugTheList', 'area' => 'auth', 'description' => 'Choose how you will use PlugTheList: list your playlist or book curators.']);
?>
<main class="wrap">
  <h1 class="center">Who are you joining as?</h1>
  <div class="choose">
    <a class="choice" href="<?= e(url('auth', '/register/curator')) ?>">
      <strong>I own a playlist, channel or community</strong>
      <span>Curator. Set your own prices, accept the bookings you want and withdraw your earnings in naira.</span>
      <em class="btn">Join as curator</em>
    </a>
    <a class="choice" href="<?= e(url('auth', '/register/creative')) ?>">
      <strong>I am an artist, label or business</strong>
      <span>Creative. Compare curators by budget, audience and proof of past work. Your payment sits in escrow until delivery.</span>
      <em class="btn">Join as creative</em>
    </a>
  </div>
  <p class="center muted">Already registered? <a href="<?= e(url('auth', '/login')) ?>">Sign in</a></p>
</main>
<?php
page_footer();
