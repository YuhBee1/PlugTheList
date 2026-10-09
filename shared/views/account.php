<?php
declare(strict_types=1);

use PTL\Audit;
use PTL\Auth;
use PTL\DB;
use PTL\Privacy;
use PTL\RateLimiter;
use PTL\Security;
use PTL\Session;
use PTL\Totp;
use PTL\Validator;

function account_page(): void
{
    $u = Auth::require();
    $uid = (int)$u['id'];
    $self = url($u['role'] === 'curator' ? 'curator' : ($u['role'] === 'admin' ? 'admin' : 'app'), '/account');
    $backup = null;
    $secretShow = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Security::requirePost();
        RateLimiter::enforce('account_act', (string)$uid, 40, 3600);
        $act = post('action');
        $pwOk = static fn(): bool => password_verify((string)($_POST['current'] ?? ''), (string)DB::val('SELECT password_hash FROM users WHERE id = ?', [$uid]));
        switch ($act) {
            case 'profile':
                $dn = Validator::name(post('display_name'), 2, 60);
                $ph = Validator::phone(post('phone'));
                if ($dn === null || $ph === null) {
                    flash('error', 'Enter a display name and a valid Nigerian mobile number.');
                } else {
                    DB::update('users', ['display_name' => $dn, 'phone' => $ph], 'id = ?', [$uid]);
                    flash('ok', 'Profile saved.');
                }
                redirect($self);
            case 'password':
                $p = Auth::changePassword($uid, (string)($_POST['current'] ?? ''), (string)($_POST['new'] ?? ''), (string)($_POST['new2'] ?? ''));
                flash($p ? 'error' : 'ok', $p ? implode(' ', $p) : 'Password changed. Other devices were signed out.');
                redirect($self);
            case 'totp_begin':
                $secretShow = Auth::beginTotp($uid);
                break;
            case 'totp_enable':
                $codes = Auth::enableTotp($uid, post('code'));
                if ($codes === null) {
                    flash('error', 'That code was not right. Try the newest code from your app.');
                    $secretShow = Crypto_open_secret($uid);
                } else {
                    $backup = $codes;
                }
                break;
            case 'totp_disable':
                if ($u['role'] === 'admin') {
                    flash('error', 'Admin accounts must keep two-step verification on.');
                } elseif (!$pwOk()) {
                    flash('error', 'Your password was not right.');
                } else {
                    Auth::disableTotp($uid);
                    flash('ok', 'Two-step verification is off.');
                }
                redirect($self);
            case 'signout_others':
                Session::revokeAll($uid, true);
                Audit::log($uid, 'signout_others', 'user', $uid);
                flash('ok', 'Signed out of all other devices.');
                redirect($self);
            case 'export':
                RateLimiter::enforce('export', (string)$uid, 5, 3600);
                Audit::log($uid, 'data_export', 'user', $uid);
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="plugthelist-my-data.json"');
                header('Cache-Control: no-store');
                echo json_encode(Privacy::export($uid), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            case 'delete':
                if (!$pwOk() || post('confirm') !== 'DELETE') {
                    flash('error', 'Type DELETE and enter your password to confirm.');
                    redirect($self);
                }
                $why = Privacy::delete($uid);
                if ($why !== null) {
                    flash('error', $why);
                    redirect($self);
                }
                Session::destroy();
                flash('ok', 'Your account was deleted.');
                redirect(url('www', '/'));
        }
    }
    $sessions = DB::all('SELECT id, ua, ip, last_seen_at, created_at FROM sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > ? ORDER BY last_seen_at DESC LIMIT 10', [$uid, now()]);
    $cur = Session::currentId();
    $fresh = DB::one('SELECT display_name, phone, totp_enabled FROM users WHERE id = ?', [$uid]);
    page_header(['title' => 'Account', 'area' => $u['role'] === 'curator' ? 'curator' : ($u['role'] === 'admin' ? 'admin' : 'creative')]);
    echo '<main class="wrap narrow"><h1>Account</h1><p class="muted">' . e((string)$u['email']) . ' · ' . e(ucfirst((string)$u['role'])) . '</p>';

    if ($backup) {
        echo '<section class="panel"><h2>Two-step verification is on</h2><p><strong>Save these backup codes now.</strong> Each works once if you lose your phone. We cannot show them again.</p><ul class="codes">';
        foreach ($backup as $c) {
            echo '<li><code>' . e($c) . '</code></li>';
        }
        echo '</ul></section>';
    }

    echo '<section class="panel"><h2>Profile</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="profile">'
        . field('display_name', 'Public name', 'text', (string)$fresh['display_name'], null, ['maxlength' => 60, 'required' => true])
        . field('phone', 'Mobile number', 'tel', (string)$fresh['phone'], null, ['required' => true])
        . '<button class="btn" type="submit">Save profile</button></form></section>';

    echo '<section class="panel"><h2>Two-step verification</h2>';
    if ((int)$fresh['totp_enabled']) {
        echo '<p>' . status_badge('active', 'On') . ' Your sign-in needs a code from your authenticator app.</p>';
        if ($u['role'] !== 'admin') {
            echo '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="totp_disable">' . field('current', 'Your password', 'password', '', null, ['autocomplete' => 'current-password', 'required' => true]) . '<button class="btn ghost" type="submit">Turn off</button></form>';
        }
    } elseif ($secretShow !== null) {
        echo '<p>In your authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password) choose "enter a setup key" and type:</p><p class="key"><code>' . e(trim(chunk_split($secretShow, 4, ' '))) . '</code></p><p class="muted small">Account: ' . e((string)$u['email']) . ' · Type: time-based · 6 digits</p>'
            . '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="totp_enable">' . field('code', 'Code from the app', 'text', '', null, ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 6, 'required' => true]) . '<button class="btn" type="submit">Turn on</button></form>';
    } else {
        echo '<p>' . ($u['role'] === 'admin' ? 'Required for admin accounts. ' : 'Recommended for everyone, and essential if you receive payouts. ') . 'Adds a 6-digit code to sign-in.</p><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="totp_begin"><button class="btn" type="submit">Set up</button></form>';
    }
    echo '</section>';

    echo '<section class="panel"><h2>Password</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="password">'
        . field('current', 'Current password', 'password', '', null, ['autocomplete' => 'current-password', 'required' => true])
        . field('new', 'New password', 'password', '', null, ['autocomplete' => 'new-password', 'required' => true, 'minlength' => 10])
        . field('new2', 'Repeat new password', 'password', '', null, ['autocomplete' => 'new-password', 'required' => true])
        . '<button class="btn" type="submit">Change password</button></form></section>';

    echo '<section class="panel"><h2>Where you are signed in</h2><ul class="plain">';
    foreach ($sessions as $s) {
        echo '<li>' . e(mb_strimwidth((string)$s['ua'], 0, 70, '…')) . ' · ' . e((string)$s['ip']) . ' · ' . e(ago((string)$s['last_seen_at'])) . ($s['id'] === $cur ? ' ' . status_badge('active', 'This device') : '') . '</li>';
    }
    echo '</ul><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="signout_others"><button class="btn ghost" type="submit">Sign out other devices</button></form></section>';

    if ($u['role'] !== 'admin') {
        echo '<section class="panel"><h2>Your data</h2><p>Download everything we hold about you, or delete your account. Financial records are kept as the law requires, without your personal details where possible.</p>'
            . '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="export"><button class="btn ghost" type="submit">Download my data</button></form>'
            . '<details class="dispute"><summary>Delete my account</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="delete">'
            . '<p class="muted">You need no orders in progress and a zero wallet balance.</p>'
            . field('current', 'Your password', 'password', '', null, ['autocomplete' => 'current-password', 'required' => true])
            . field('confirm', 'Type DELETE to confirm', 'text', '', null, ['required' => true, 'autocomplete' => 'off'])
            . '<button class="btn danger" type="submit">Delete account</button></form></details></section>';
    }
    echo '</main>';
    page_footer();
}

function Crypto_open_secret(int $uid): ?string
{
    $enc = (string)DB::val('SELECT totp_secret FROM users WHERE id = ?', [$uid]);
    return $enc !== '' ? PTL\Crypto::open($enc, 'totp') : null;
}
