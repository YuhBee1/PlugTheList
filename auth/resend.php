<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;
use PTL\RateLimiter;
use PTL\Security;
use PTL\Validator;

$done = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    RateLimiter::enforce('resend_ip', client_ip(), 5, 3600);
    $email = Validator::email(post('email'));
    if ($email !== null && RateLimiter::allow('resend_email', $email, 3, 3600)) {
        $id = DB::val('SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL AND deleted_at IS NULL', [$email]);
        if ($id) {
            Auth::sendVerification((int)$id);
        }
    }
    $done = true;                                                 
}
page_header(['title' => 'Resend confirmation', 'area' => 'auth']);
echo '<main class="wrap auth-wrap"><div class="auth-card"><h1>Resend confirmation email</h1>';
if ($done) {
    echo '<p class="lede">If that email belongs to an account waiting for confirmation, a new link is on its way.</p>';
} else {
    echo '<form method="post" class="form" novalidate>' . csrf_field() . field('email', 'Email', 'email', '', null, ['required' => true, 'autocomplete' => 'email']) . '<button class="btn wide" type="submit">Send link</button></form>';
}
echo '</div></main>';
page_footer();
