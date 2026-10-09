<?php
declare(strict_types=1);

namespace PTL;

final class Auth
{
    public const TERMS_VERSION = '2026-10-draft';
    public const CREATIVE_TYPES = ['artist' => 'Artist or band', 'label' => 'Label or management', 'business' => 'Business or brand', 'other' => 'Something else'];

       
                                      
                                                                    
       
    public static function register(string $role, array $in): array
    {
        $err = [];
        if (!in_array($role, ['curator', 'creative'], true)) {
            throw new \InvalidArgumentException('role');
        }
        $email = Validator::email($in['email'] ?? '');
        $full = Validator::name($in['full_name'] ?? '');
        $display = Validator::name($in['display_name'] ?? '', 2, 60);
        $phone = Validator::phone($in['phone'] ?? '');
        $pass = (string)($in['password'] ?? '');
        if ($email === null) {
            $err['email'] = 'Enter a valid email address.';
        }
        if ($full === null) {
            $err['full_name'] = 'Enter your full name (2 to 80 characters).';
        }
        if ($display === null) {
            $err['display_name'] = $role === 'curator' ? 'Enter the name curators and artists will see (2 to 60 characters).' : 'Enter your artist, label or business name (2 to 60 characters).';
        }
        if ($phone === null) {
            $err['phone'] = 'Enter a Nigerian mobile number, like 0803 123 4567.';
        }
        $problems = Validator::passwordProblems($pass, (string)$email);
        if ($problems) {
            $err['password'] = implode(' ', $problems);
        } elseif (!hash_equals($pass, (string)($in['password2'] ?? ''))) {
            $err['password2'] = 'The two passwords do not match.';
        }
        $ctype = null;
        if ($role === 'creative') {
            $ctype = Validator::oneOf((string)($in['creative_type'] ?? ''), self::CREATIVE_TYPES);
            if ($ctype === null) {
                $err['creative_type'] = 'Choose what best describes you.';
            }
        }
        if (empty($in['terms'])) {
            $err['terms'] = 'You must accept the Terms and Privacy notice.';
        }
        if (empty($in['age18'])) {
            $err['age18'] = 'You must confirm you are 18 or older.';
        }
        if ($err) {
            return [null, $err];
        }
        $id = null;
        try {
            $id = DB::insert('users', [
                'role' => $role,
                'email' => $email,
                'password_hash' => self::hash($pass),
                'full_name' => $full,
                'display_name' => $display,
                'phone' => $phone,
                'creative_type' => $ctype,
                'business_name' => null,
                'status' => 'active',
                'terms_version' => self::TERMS_VERSION,
                'terms_accepted_at' => now(),
                'marketing_opt_in' => empty($in['marketing']) ? 0 : 1,
            ]);
        } catch (\PDOException $e) {
                                                                                                        
            $existing = DB::one('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing !== null) {
                Mailer::send((string)$email, 'Someone tried to sign up with your email', "Someone used this email to create a PlugTheList account, but you already have one.\n\nIf it was you, sign in or reset your password. If not, you can ignore this message.", url('auth', '/login'), 'Sign in');
                return [0, []];
            }
            throw $e;
        }
        Audit::log($id, 'register', 'user', $id, ['role' => $role]);
        self::sendVerification($id);
        return [$id, []];
    }

    public static function hash(string $p): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($p, $algo);
    }

    public static function sendVerification(int $userId): void
    {
        $u = DB::one('SELECT id, email, email_verified_at, display_name FROM users WHERE id = ?', [$userId]);
        if ($u === null || $u['email_verified_at'] !== null) {
            return;
        }
        $tok = self::makeToken($userId, 'verify', 86400 * 2);
        $link = url('auth', '/verify?t=' . $tok);
        Mailer::send((string)$u['email'], 'Confirm your email for PlugTheList', "Hi " . $u['display_name'] . ",\n\nConfirm your email address to finish creating your account. The link works for 48 hours.", $link, 'Confirm my email');
    }

    public static function makeToken(int $userId, string $purpose, int $ttl): string
    {
        DB::update('email_tokens', ['used_at' => now()], 'user_id = ? AND purpose = ? AND used_at IS NULL', [$userId, $purpose], false);
        $tok = Crypto::token(32);
        DB::insert('email_tokens', ['user_id' => $userId, 'purpose' => $purpose, 'token_hash' => Crypto::tokenHash($tok), 'expires_at' => date('Y-m-d H:i:s', time() + $ttl)]);
        return $tok;
    }

                                                                       
    public static function peekToken(string $tok, string $purpose): ?int
    {
        if (!preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $tok)) {
            return null;
        }
        $r = DB::one('SELECT id, user_id, expires_at, used_at FROM email_tokens WHERE token_hash = ? AND purpose = ?', [Crypto::tokenHash($tok), $purpose]);
        if ($r === null || $r['used_at'] !== null || strtotime((string)$r['expires_at']) < time()) {
            return null;
        }
        return (int)$r['user_id'];
    }

    public static function consumeToken(string $tok, string $purpose): ?int
    {
        $uid = self::peekToken($tok, $purpose);
        if ($uid === null) {
            return null;
        }
        $n = DB::update('email_tokens', ['used_at' => now()], 'token_hash = ? AND purpose = ? AND used_at IS NULL', [Crypto::tokenHash($tok), $purpose], false);
        return $n === 1 ? $uid : null;
    }

    public static function verifyEmail(string $tok): ?array
    {
        $uid = self::consumeToken($tok, 'verify');
        if ($uid === null) {
            return null;
        }
        DB::update('users', ['email_verified_at' => now()], 'id = ? AND email_verified_at IS NULL', [$uid]);
        Audit::log($uid, 'email_verified', 'user', $uid);
        return DB::one('SELECT id, role FROM users WHERE id = ?', [$uid]);
    }

       
                                                                                  
       
    public static function login(string $email, string $password): array
    {
        $ip = Security::ip();
        $email = strtolower(trim($email));
        if (RateLimiter::over('login_ip', $ip, 15, 900) || RateLimiter::over('login_email', $email, 6, 900)) {
            return ['error', 'Too many attempts. Wait 15 minutes and try again.'];
        }
        $u = DB::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
                                                                                         
        static $dummy = null;
        $dummy ??= self::hash(bin2hex(random_bytes(8)));
        $hash = $u['password_hash'] ?? $dummy;
        $good = password_verify($password, $hash);
        if ($u === null || !$good) {
            RateLimiter::hit('login_ip', $ip, 900);
            RateLimiter::hit('login_email', $email, 900);
            Audit::log($u ? (int)$u['id'] : null, 'login_failed', 'user', $u ? (int)$u['id'] : null);
            return ['error', 'Email or password is not right.'];
        }
        if ($u['status'] !== 'active') {
            return ['error', 'This account is suspended. Contact support.'];
        }
        if ($u['email_verified_at'] === null) {
            return ['error', 'Confirm your email first. Check your inbox, or request a new link below.'];
        }
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            DB::update('users', ['password_hash' => self::hash($password)], 'id = ?', [$u['id']]);
        }
        RateLimiter::reset('login_email', $email);
        $needMfa = (int)$u['totp_enabled'] === 1;
        Session::create((int)$u['id'], !$needMfa);
        DB::update('users', ['last_login_at' => now(), 'last_login_ip' => $ip], 'id = ?', [$u['id']], false);
        Audit::log((int)$u['id'], $needMfa ? 'login_password_ok' : 'login', 'user', (int)$u['id']);
        return [$needMfa ? 'mfa' : 'ok', ''];
    }

    public static function verifyMfa(string $code): bool
    {
        $row = Session::row();
        if ($row === null) {
            return false;
        }
        $uid = (int)$row['user_id'];
        if (RateLimiter::over('mfa', (string)$uid, 8, 900)) {
            return false;
        }
        $u = DB::one('SELECT id, totp_secret, totp_last_step, backup_codes FROM users WHERE id = ?', [$uid]);
        $code = trim($code);
        $ok = false;
        if ($u !== null && $u['totp_secret']) {
            $secret = Crypto::open((string)$u['totp_secret'], 'totp');
            if ($secret !== null && preg_match('/^\d{6}$/', $code)) {
                $step = Totp::verify($secret, $code, (int)$u['totp_last_step']);
                if ($step !== null) {
                    DB::update('users', ['totp_last_step' => $step], 'id = ?', [$uid]);
                    $ok = true;
                }
            }
            if (!$ok && $u['backup_codes'] && preg_match('/^[a-z0-9]{4}-?[a-z0-9]{4}$/i', $code)) {
                $ok = self::useBackup($uid, (string)$u['backup_codes'], $code);
            }
        }
        if (!$ok) {
            RateLimiter::hit('mfa', (string)$uid, 900);
            Audit::log($uid, 'mfa_failed', 'user', $uid);
            return false;
        }
        RateLimiter::reset('mfa', (string)$uid);
        Session::markMfa();
        Audit::log($uid, 'login', 'user', $uid, ['mfa' => true]);
        return true;
    }

    private static function useBackup(int $uid, string $json, string $code): bool
    {
        $hashes = json_decode($json, true) ?: [];
        $needle = Crypto::hmac('backup', strtolower(str_replace('-', '', $code)));
        foreach ($hashes as $i => $h) {
            if (hash_equals($h, $needle)) {
                unset($hashes[$i]);
                DB::update('users', ['backup_codes' => json_encode(array_values($hashes))], 'id = ?', [$uid]);
                return true;
            }
        }
        return false;
    }

                                                                            
    public static function beginTotp(int $userId): string
    {
        $secret = Totp::newSecret();
        DB::update('users', ['totp_secret' => Crypto::seal($secret, 'totp'), 'totp_enabled' => 0], 'id = ?', [$userId]);
        return $secret;
    }

                                                                                        
    public static function enableTotp(int $userId, string $code): ?array
    {
        $enc = (string)DB::val('SELECT totp_secret FROM users WHERE id = ?', [$userId]);
        $secret = $enc !== '' ? Crypto::open($enc, 'totp') : null;
        if ($secret === null || RateLimiter::over('mfa_setup', (string)$userId, 8, 900)) {
            return null;
        }
        $step = Totp::verify($secret, trim($code), 0);
        if ($step === null) {
            RateLimiter::hit('mfa_setup', (string)$userId, 900);
            return null;
        }
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = strtolower(bin2hex(random_bytes(4)));
            $codes[] = substr($c, 0, 4) . '-' . substr($c, 4);
            $hashes[] = Crypto::hmac('backup', $c);
        }
        DB::update('users', ['totp_enabled' => 1, 'totp_last_step' => $step, 'backup_codes' => json_encode($hashes)], 'id = ?', [$userId]);
        Session::revokeAll($userId, true);
        Audit::log($userId, 'mfa_enabled', 'user', $userId);
        return $codes;
    }

    public static function disableTotp(int $userId): void
    {
        DB::update('users', ['totp_enabled' => 0, 'totp_secret' => null, 'backup_codes' => null, 'totp_last_step' => 0], 'id = ?', [$userId]);
        Session::revokeAll($userId, true);
        Audit::log($userId, 'mfa_disabled', 'user', $userId);
    }

    public static function requestReset(string $email): void
    {
        $email = Validator::email($email);
        if ($email === null) {
            return;
        }
        $u = DB::one('SELECT id, email, display_name FROM users WHERE email = ? AND status = ? AND deleted_at IS NULL', [$email, 'active']);
        if ($u === null) {
            return;
        }
        $tok = self::makeToken((int)$u['id'], 'reset', 3600);
        Mailer::send((string)$u['email'], 'Reset your PlugTheList password', "We got a request to reset your password. The link works for 1 hour. If this was not you, ignore this email: your password stays the same.", url('auth', '/reset?t=' . $tok), 'Choose a new password');
        Audit::log((int)$u['id'], 'reset_requested', 'user', (int)$u['id']);
    }

                                             
    public static function resetPassword(string $tok, string $pass, string $pass2): array
    {
        $uid = self::peekToken($tok, 'reset');
        if ($uid === null) {
            return ['This reset link is invalid or has expired. Request a new one.'];
        }
        $email = (string)DB::val('SELECT email FROM users WHERE id = ?', [$uid]);
        $p = Validator::passwordProblems($pass, $email);
        if (!$p && !hash_equals($pass, $pass2)) {
            $p[] = 'The two passwords do not match.';
        }
        if ($p) {
            return $p;
        }
        if (self::consumeToken($tok, 'reset') === null) {
            return ['This reset link is invalid or has expired. Request a new one.'];
        }
        DB::update('users', ['password_hash' => self::hash($pass)], 'id = ?', [$uid]);
        Session::revokeAll($uid);
        Audit::log($uid, 'password_reset', 'user', $uid);
        Mailer::send($email, 'Your PlugTheList password was changed', "Your password was just changed and you were signed out everywhere. If this was not you, reset it again now and contact support.", url('auth', '/forgot'), 'Reset password');
        return [];
    }

                                             
    public static function changePassword(int $userId, string $current, string $new, string $new2): array
    {
        if (RateLimiter::over('chpw', (string)$userId, 6, 900)) {
            return ['Too many attempts. Try again later.'];
        }
        $u = DB::one('SELECT email, password_hash FROM users WHERE id = ?', [$userId]);
        if ($u === null || !password_verify($current, (string)$u['password_hash'])) {
            RateLimiter::hit('chpw', (string)$userId, 900);
            return ['Your current password is not right.'];
        }
        $p = Validator::passwordProblems($new, (string)$u['email']);
        if (!$p && !hash_equals($new, $new2)) {
            $p[] = 'The two new passwords do not match.';
        }
        if ($p) {
            return $p;
        }
        DB::update('users', ['password_hash' => self::hash($new)], 'id = ?', [$userId]);
        Session::revokeAll($userId, true);
        Audit::log($userId, 'password_changed', 'user', $userId);
        return [];
    }

                                                                                   
    public static function require(string ...$roles): array
    {
        $u = Session::user();
        if ($u === null) {
            $row = Session::row();
            $self = url_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
            $next = '?next=' . rawurlencode($self);
            redirect(url('auth', ($row !== null ? '/2fa' : '/login') . $next));
        }
        if ($roles && !in_array($u['role'], $roles, true)) {
            abort(403, 'This area is for ' . implode(' or ', $roles) . ' accounts.');
        }
        if ($u['role'] === 'admin' && !(int)$u['totp_enabled'] && !defined('PTL_ALLOW_ADMIN_NO_2FA')) {
            flash('error', 'Admins must turn on two-factor authentication before using the admin area.');
            redirect(url('admin', '/account'));
        }
        return $u;
    }
}
