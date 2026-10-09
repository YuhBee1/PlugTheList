<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
page_header(['title' => 'Check your email', 'area' => 'auth']);
?>
<main class="wrap narrow">
  <h1>Check your email</h1>
  <p class="lede">If that address can be registered, we have sent a confirmation link. It works for 48 hours. Look in spam if you do not see it.</p>
  <p><a class="btn" href="<?= e(url('auth', '/login')) ?>">Go to sign in</a> <a class="link" href="<?= e(url('auth', '/resend')) ?>">Send it again</a></p>
</main>
<?php
page_footer();
