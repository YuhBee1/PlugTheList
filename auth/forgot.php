<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\RateLimiter;
use PTL\Security;

$done = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    RateLimiter::enforce('forgot_ip', client_ip(), 6, 3600);
    $e = strtolower(post('email'));
    if ($e !== '' && RateLimiter::allow('forgot_email', $e, 3, 3600)) {
        Auth::requestReset($e);
    }
    $done = true;
}
page_header(['title' => 'Forgot password', 'area' => 'auth']);
echo '<main class="wrap auth-wrap"><div class="auth-card"><h1>Reset your password</h1>';
if ($done) {
    echo '<p class="lede">If that email has an account, we sent a reset link. It works for one hour.</p>';
} else {
    echo '<form method="post" class="form" novalidate>' . csrf_field() . field('email', 'Email', 'email', '', null, ['required' => true, 'autocomplete' => 'email']) . '<button class="btn wide" type="submit">Email me a reset link</button></form>';
}
echo '</div></main>';
page_footer();
