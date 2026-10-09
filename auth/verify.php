<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;

                                                                                                            
$r = Auth::verifyEmail(qs('t'));
page_header(['title' => 'Confirm email', 'area' => 'auth']);
echo '<main class="wrap narrow">';
if ($r) {
    echo '<h1>Email confirmed</h1><p class="lede">Your account is ready. Sign in to continue.</p><p><a class="btn" href="' . e(url('auth', '/login')) . '">Sign in</a></p>';
} else {
    echo '<h1>That link did not work</h1><p class="lede">It may have expired or already been used. Request a new one.</p><p><a class="btn" href="' . e(url('auth', '/resend')) . '">Send a new link</a></p>';
}
echo '</main>';
page_footer();
