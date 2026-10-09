<?php
declare(strict_types=1);

namespace PTL;

                                                                                                                                     
final class Privacy
{
                                      
    public static function export(int $userId): array
    {
        $u = DB::one('SELECT id, role, email, full_name, display_name, phone, creative_type, created_at, terms_version, terms_accepted_at, marketing_opt_in, last_login_at FROM users WHERE id = ?', [$userId]) ?? [];
        return [
            'exported_at' => now(),
            'account' => $u,
            'listings' => DB::all('SELECT id, platform, title, url, followers, genres, description, status, created_at FROM listings WHERE curator_id = ?', [$userId]),
            'orders' => DB::all('SELECT ref, listing_title, track_title, track_url, price_kobo, total_kobo, status, created_at FROM orders WHERE creative_id = ? OR curator_id = ?', [$userId, $userId]),
            'withdrawals' => DB::all('SELECT amount_kobo, fee_kobo, status, bank_name, last4, created_at FROM withdrawals WHERE user_id = ?', [$userId]),
            'notifications' => DB::all('SELECT title, body, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 500', [$userId]),
            'sign_in_history' => DB::all('SELECT ip, ua, created_at FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [$userId]),
        ];
    }

                                                           
    public static function blockers(int $userId): ?string
    {
        if ((int)DB::val("SELECT COUNT(*) FROM orders WHERE (creative_id = ? OR curator_id = ?) AND status IN ('awaiting_payment','paid','in_progress','delivered','disputed')", [$userId, $userId]) > 0) {
            return 'You have orders in progress. Finish or cancel them first.';
        }
        if (Ledger::userBalance($userId) > 0) {
            return 'Withdraw your wallet balance first.';
        }
        if ((int)DB::val("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status IN ('requested','processing')", [$userId]) > 0) {
            return 'You have a withdrawal in progress.';
        }
        return null;
    }

    public static function delete(int $userId): ?string
    {
        $b = self::blockers($userId);
        if ($b !== null) {
            return $b;
        }
        DB::tx(static function () use ($userId): void {
            Session::revokeAll($userId);
            DB::update('users', [
                'email' => 'deleted-' . $userId . '@deleted.invalid',
                'password_hash' => Auth::hash(bin2hex(random_bytes(16))),
                'full_name' => 'Deleted user', 'display_name' => 'Deleted user', 'phone' => null, 'business_name' => null,
                'status' => 'deleted', 'totp_secret' => null, 'totp_enabled' => 0, 'backup_codes' => null,
                'marketing_opt_in' => 0, 'last_login_ip' => null, 'deleted_at' => now(),
            ], 'id = ?', [$userId]);
            DB::update('listings', ['status' => 'paused'], 'curator_id = ?', [$userId]);
            DB::run('DELETE FROM bank_accounts WHERE user_id = ?', [$userId]);
            DB::run('DELETE FROM notifications WHERE user_id = ?', [$userId]);
            DB::run('DELETE FROM email_tokens WHERE user_id = ?', [$userId]);
            DB::run('UPDATE sessions SET ip = NULL, ua = NULL WHERE user_id = ?', [$userId]);
            Audit::log($userId, 'account_deleted', 'user', $userId);
        });
        return null;
    }
}
