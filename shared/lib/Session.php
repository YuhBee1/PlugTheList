<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                             
                                                                                             
                                                                                                      
   
final class Session
{
    private static ?array $row = null;
    private static ?array $user = null;

    public static function start(): void
    {
        self::$row = null;
        self::$user = null;
        $tok = Security::getCookie('ptl_sid');
        if (!preg_match('/^[a-f0-9]{64}$/', $tok)) {
            return;
        }
        $row = DB::one(
            'SELECT s.*, u.role AS u_role, u.status AS u_status, u.deleted_at AS u_deleted
               FROM sessions s JOIN users u ON u.id = s.user_id
              WHERE s.id = ? AND s.revoked_at IS NULL',
            [Crypto::tokenHash($tok)]
        );
        if ($row === null) {
            Security::deleteCookie('ptl_sid');
            return;
        }
        $idle = $row['u_role'] === 'admin' ? 1800 : 43200;
        $last = strtotime((string)$row['last_seen_at']);
        $dead = time() > strtotime((string)$row['expires_at'])
            || (time() - $last) > $idle
            || $row['u_status'] !== 'active'
            || $row['u_deleted'] !== null;
        if ($dead) {
            DB::update('sessions', ['revoked_at' => now()], 'id = ?', [$row['id']], false);
            Security::deleteCookie('ptl_sid');
            return;
        }
        if (time() - $last > 60) {
            DB::update('sessions', ['last_seen_at' => now()], 'id = ?', [$row['id']], false);
        }
        self::$row = $row;
    }

    public static function row(): ?array
    {
        return self::$row;
    }

                                                                           
    public static function user(): ?array
    {
        if (self::$row === null || !(int)self::$row['mfa_ok']) {
            return null;
        }
        if (self::$user === null) {
            self::$user = DB::one(
                'SELECT id, role, email, email_verified_at, full_name, display_name, phone, creative_type, business_name, status, totp_enabled, created_at
                   FROM users WHERE id = ?',
                [self::$row['user_id']]
            );
        }
        return self::$user;
    }

    public static function create(int $userId, bool $mfaOk): void
    {
        $role = (string)DB::val('SELECT role FROM users WHERE id = ?', [$userId]);
        $absolute = $role === 'admin' ? 12 * 3600 : 7 * 86400;
        $tok = Crypto::token(32);
        DB::insert('sessions', [
            'id' => Crypto::tokenHash($tok),
            'user_id' => $userId,
            'csrf' => Crypto::token(32),
            'mfa_ok' => $mfaOk ? 1 : 0,
            'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'ip' => Security::ip(),
            'last_seen_at' => now(),
            'expires_at' => date('Y-m-d H:i:s', time() + $absolute),
        ]);
        Security::setCookie('ptl_sid', $tok, time() + $absolute, true);
        self::start();
    }

    public static function markMfa(): void
    {
        if (self::$row === null) {
            return;
        }
                                                                                            
        $old = (string)self::$row['id'];
        $tok = Crypto::token(32);
        DB::update('sessions', ['id' => Crypto::tokenHash($tok), 'mfa_ok' => 1, 'csrf' => Crypto::token(32)], 'id = ?', [$old], false);
        $absolute = max(60, strtotime((string)self::$row['expires_at']) - time());
        Security::setCookie('ptl_sid', $tok, time() + $absolute, true);
        self::start();
    }

    public static function destroy(): void
    {
        if (self::$row !== null) {
            DB::update('sessions', ['revoked_at' => now()], 'id = ?', [self::$row['id']], false);
        }
        Security::deleteCookie('ptl_sid');
        self::$row = null;
        self::$user = null;
    }

    public static function revokeAll(int $userId, bool $keepCurrent = false): void
    {
        if ($keepCurrent && self::$row !== null) {
            DB::update('sessions', ['revoked_at' => now()], 'user_id = ? AND revoked_at IS NULL AND id <> ?', [$userId, self::$row['id']], false);
        } else {
            DB::update('sessions', ['revoked_at' => now()], 'user_id = ? AND revoked_at IS NULL', [$userId], false);
        }
    }

    public static function currentId(): string
    {
        return self::$row !== null ? (string)self::$row['id'] : '';
    }
}
