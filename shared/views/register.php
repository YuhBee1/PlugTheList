<?php
declare(strict_types=1);

use PTL\Auth;
use PTL\RateLimiter;
use PTL\Session;

                                                  
function register_page(string $role): void
{
    if (Session::user()) {
        redirect(role_home((string)Session::user()['role']));
    }
    $isCur = $role === 'curator';
    $errs = [];
    $v = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        PTL\Security::requirePost();
        RateLimiter::enforce('register_ip', client_ip(), 8, 3600);
        $v = array_map(static fn($x) => is_string($x) ? $x : '', $_POST);
        [$id, $errs] = Auth::register($role, $v);
        if ($id !== null && !$errs) {
            redirect(url('auth', '/check-email'));
        }
    }
    $g = static fn(string $k): string => (string)($v[$k] ?? '');
    page_header([
        'title' => $isCur ? 'List your playlist or community' : 'Create an artist or business account',
        'area' => 'auth',
        'description' => $isCur ? 'Sign up as a curator, set your own prices and get paid through escrow.' : 'Sign up to book playlists, channels and communities with escrow protection.',
    ]);
    echo '<main class="wrap auth-wrap"><div class="auth-card"><p class="crumb"><a href="' . e(url('auth', '/register')) . '">Join</a> / ' . ($isCur ? 'Curator' : 'Creative') . '</p>';
    echo '<h1>' . ($isCur ? 'List your playlist or community' : 'Book curators for your music or brand') . '</h1>';
    echo '<p class="lede">' . ($isCur
        ? 'You own a playlist, channel or community. You set the price, we hold the buyer\'s money in escrow, and you withdraw once the work is approved.'
        : 'You are an artist, label or business. Pay once into escrow; the curator is paid only after delivery.') . '</p>';
    echo '<form method="post" class="form" novalidate>' . csrf_field();
    echo field('full_name', 'Full name', 'text', $g('full_name'), $errs['full_name'] ?? null, ['autocomplete' => 'name', 'required' => true, 'maxlength' => 80], 'As on your bank account. ' . ($isCur ? 'Payouts only go to an account in this name.' : ''));
    echo field('display_name', $isCur ? 'Public curator name' : 'Artist, label or business name', 'text', $g('display_name'), $errs['display_name'] ?? null, ['required' => true, 'maxlength' => 60]);
    if (!$isCur) {
        echo select_field('creative_type', 'I am a', Auth::CREATIVE_TYPES, $g('creative_type'), $errs['creative_type'] ?? null, 'Choose one');
    }
    echo field('email', 'Email', 'email', $g('email'), $errs['email'] ?? null, ['autocomplete' => 'email', 'required' => true, 'maxlength' => 190]);
    echo field('phone', 'Nigerian mobile number', 'tel', $g('phone'), $errs['phone'] ?? null, ['autocomplete' => 'tel', 'required' => true, 'placeholder' => '0803 123 4567']);
    echo field('password', 'Password', 'password', '', $errs['password'] ?? null, ['autocomplete' => 'new-password', 'required' => true, 'minlength' => 10, 'maxlength' => 200], 'At least 10 characters with letters and numbers.');
    echo field('password2', 'Repeat password', 'password', '', $errs['password2'] ?? null, ['autocomplete' => 'new-password', 'required' => true]);
    echo '<div class="checks">';
    echo '<label class="check"><input type="checkbox" name="age18" value="1"' . ($g('age18') ? ' checked' : '') . '> I am 18 or older.</label>' . (isset($errs['age18']) ? '<small class="err">' . e($errs['age18']) . '</small>' : '');
    echo '<label class="check"><input type="checkbox" name="terms" value="1"' . ($g('terms') ? ' checked' : '') . '> I accept the <a href="' . e(url('www', '/terms')) . '" target="_blank" rel="noopener">Terms</a>, <a href="' . e(url('www', '/acceptable-use')) . '" target="_blank" rel="noopener">Acceptable use</a> and <a href="' . e(url('www', '/privacy')) . '" target="_blank" rel="noopener">Privacy notice</a>.</label>' . (isset($errs['terms']) ? '<small class="err">' . e($errs['terms']) . '</small>' : '');
    echo '<label class="check"><input type="checkbox" name="marketing" value="1"' . ($g('marketing') ? ' checked' : '') . '> Send me product news (optional).</label>';
    echo '</div><button class="btn wide" type="submit">' . ($isCur ? 'Create curator account' : 'Create creative account') . '</button></form>';
    echo '<p class="alt">Already have an account? <a href="' . e(url('auth', '/login')) . '">Sign in</a>. ' . ($isCur
        ? 'Looking to book? <a href="' . e(url('auth', '/register/creative')) . '">Join as an artist or business</a>.'
        : 'Own a playlist? <a href="' . e(url('auth', '/register/curator')) . '">Join as a curator</a>.') . '</p>';
    echo '</div></main>';
    page_footer();
}
