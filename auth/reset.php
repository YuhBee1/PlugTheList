<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\RateLimiter;
use PTL\Security;

$tok = qs('t', post('t'));
$errs = [];
$valid = Auth::peekToken($tok, 'reset') !== null;
$done = false;
if ($valid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    RateLimiter::enforce('reset_ip', client_ip(), 10, 3600);
    $errs = Auth::resetPassword($tok, (string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
    $done = !$errs;
}
page_header(['title' => 'Choose a new password', 'area' => 'auth']);
echo '<main class="wrap auth-wrap"><div class="auth-card">';
if ($done) {
    echo '<h1>Password changed</h1><p class="lede">You were signed out everywhere. Sign in with your new password.</p><a class="btn" href="' . e(url('auth', '/login')) . '">Sign in</a>';
} elseif (!$valid) {
    echo '<h1>Link expired</h1><p class="lede">This reset link is invalid or has expired.</p><a class="btn" href="' . e(url('auth', '/forgot')) . '">Request a new link</a>';
} else {
    echo '<h1>Choose a new password</h1>' . error_box($errs) . '<form method="post" class="form" novalidate>' . csrf_field() . '<input type="hidden" name="t" value="' . e($tok) . '">'
        . field('password', 'New password', 'password', '', null, ['autocomplete' => 'new-password', 'required' => true, 'minlength' => 10], 'At least 10 characters with letters and numbers.')
        . field('password2', 'Repeat new password', 'password', '', null, ['autocomplete' => 'new-password', 'required' => true])
        . '<button class="btn wide" type="submit">Change password</button></form>';
}
echo '</div></main>';
page_footer();
