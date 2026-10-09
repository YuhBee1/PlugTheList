<?php
declare(strict_types=1);

namespace PTL;

                                                                                                                    
final class Wallet
{
    public static function bank(int $userId): ?array
    {
        return DB::one('SELECT id, bank_code, bank_name, last4, account_name, recipient_code FROM bank_accounts WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

                                    
    public static function saveBank(int $userId, string $bankCode, string $number): ?string
    {
        if (RateLimiter::over('bank_save', (string)$userId, 6, 3600)) {
            return 'Too many tries. Please wait an hour.';
        }
        RateLimiter::hit('bank_save', (string)$userId, 3600);
        $name = null;
        $bankName = '';
        foreach (Paystack::banks() as $b) {
            if ($b['code'] === $bankCode) {
                $bankName = $b['name'];
            }
        }
        if ($bankName === '' || !preg_match('/^\d{10}$/', $number)) {
            return 'Choose your bank and enter your 10-digit account number.';
        }
        try {
            $name = Paystack::resolveAccount($number, $bankCode);
            if ($name === null || $name === '') {
                return 'We could not verify that account. Check the number and bank.';
            }
            $rc = Paystack::createRecipient($name, $number, $bankCode);
        } catch (\Throwable $e) {
            error_log('[bank] ' . $e->getMessage());
            return 'The bank check is unavailable right now. Try again shortly.';
        }
        if ($rc === null) {
            return 'We could not set up payouts for that account.';
        }
                                                                                                                
        $reg = (string)DB::val('SELECT full_name FROM users WHERE id = ?', [$userId]);
        if (!self::namesOverlap($reg, $name) && !Paystack::mock()) {
            Audit::log($userId, 'bank_name_mismatch', 'user', $userId);
            return 'The account name "' . $name . '" does not match the name on your PlugTheList account. Payouts go only to accounts in your own name.';
        }
        DB::tx(static function () use ($userId, $bankCode, $bankName, $number, $name, $rc): void {
            DB::run('DELETE FROM bank_accounts WHERE user_id = ?', [$userId]);
            DB::insert('bank_accounts', [
                'user_id' => $userId, 'bank_code' => $bankCode, 'bank_name' => $bankName,
                'account_enc' => Crypto::seal($number, 'bank'), 'last4' => substr($number, -4), 'account_name' => $name, 'recipient_code' => $rc,
            ]);
        });
        Audit::log($userId, 'bank_saved', 'user', $userId, ['bank' => $bankName, 'last4' => substr($number, -4)]);
        return null;
    }

    private static function namesOverlap(string $a, string $b): bool
    {
        $t = static fn(string $s): array => array_filter(preg_split('/[^a-z]+/', strtolower($s)) ?: [], static fn($w) => strlen($w) > 2);
        return count(array_intersect($t($a), $t($b))) > 0;
    }

                                    
    public static function request(int $userId, int $kobo): ?string
    {
        $min = Settings::int('min_withdrawal_kobo');
        if ($kobo < $min) {
            return 'The minimum withdrawal is ' . naira($min) . '.';
        }
        $bank = self::bank($userId);
        if ($bank === null) {
            return 'Add your bank account first.';
        }
        if (RateLimiter::over('withdraw', (string)$userId, 5, 3600)) {
            return 'Too many withdrawal requests. Try again in an hour.';
        }
        RateLimiter::hit('withdraw', (string)$userId, 3600);
        $fee = Settings::int('withdrawal_fee_kobo');
        $auto = !Settings::int('withdrawals_need_approval');
        $err = DB::tx(static function () use ($userId, $kobo, $bank, $fee, $auto): ?string {
            DB::one('SELECT id FROM users WHERE id = ?' . DB::forUpdate(), [$userId]);                      
            if (Ledger::userBalance($userId) < $kobo) {
                return 'You do not have enough in your wallet.';
            }
            if ($kobo <= $fee) {
                return 'Amount must be more than the withdrawal fee.';
            }
            $ref = 'wd-' . bin2hex(random_bytes(10));
            $id = DB::insert('withdrawals', [
                'user_id' => $userId, 'amount_kobo' => $kobo, 'fee_kobo' => $fee, 'status' => 'requested',
                'bank_name' => $bank['bank_name'], 'last4' => $bank['last4'], 'account_name' => $bank['account_name'],
                'recipient_code' => $bank['recipient_code'], 'transfer_ref' => $ref,
            ]);
            Ledger::post('wd_hold', 'wdhold:' . $id, ['user:' . $userId => -$kobo, 'hold:withdrawal' => $kobo], null, 'withdrawal ' . $id);
            Audit::log($userId, 'withdraw_requested', 'withdrawal', $id, ['amount' => $kobo]);
            if (!$auto) {
                Notifier::admins('Withdrawal to approve', naira($kobo) . ' requested.', url('admin', '/withdrawals'));
            } else {
                DB::afterCommit(static fn() => self::process($id));
            }
            return null;
        });
        return $err;
    }

                                              
    public static function approve(int $adminId, int $id): ?string
    {
        $w = DB::one('SELECT * FROM withdrawals WHERE id = ?', [$id]);
        if ($w === null || $w['status'] !== 'requested') {
            return 'This withdrawal is not waiting for approval.';
        }
        DB::update('withdrawals', ['decided_by' => $adminId, 'decided_at' => now()], 'id = ? AND status = ?', [$id, 'requested']);
        Audit::log($adminId, 'withdraw_approved', 'withdrawal', $id);
        return self::process($id);
    }

    public static function reject(int $adminId, int $id, string $note): ?string
    {
        $r = DB::tx(static function () use ($adminId, $id, $note): ?string {
            $w = DB::one('SELECT * FROM withdrawals WHERE id = ?' . DB::forUpdate(), [$id]);
            if ($w === null || $w['status'] !== 'requested') {
                return 'This withdrawal is not waiting for approval.';
            }
            self::returnFunds($w);
            DB::update('withdrawals', ['status' => 'rejected', 'note' => mb_substr($note, 0, 300), 'decided_by' => $adminId, 'decided_at' => now()], 'id = ?', [$id]);
            Notifier::to((int)$w['user_id'], 'Withdrawal not approved', ($note !== '' ? $note . ' ' : '') . 'The ' . naira((int)$w['amount_kobo']) . ' is back in your wallet.', url('curator', '/wallet'));
            Audit::log($adminId, 'withdraw_rejected', 'withdrawal', $id);
            return null;
        });
        return $r;
    }

    private static function returnFunds(array $w): void
    {
        Ledger::post('wd_return', 'wdret:' . $w['id'], ['hold:withdrawal' => -(int)$w['amount_kobo'], 'user:' . $w['user_id'] => (int)$w['amount_kobo']], null, 'withdrawal ' . $w['id']);
    }

                                                                  
    public static function process(int $id): ?string
    {
        $claimed = DB::update('withdrawals', ['status' => 'processing'], 'id = ? AND status = ?', [$id, 'requested']);
        if ($claimed !== 1) {
            return 'Already handled.';
        }
        $w = DB::one('SELECT * FROM withdrawals WHERE id = ?', [$id]);
        $pay = (int)$w['amount_kobo'] - (int)$w['fee_kobo'];
        try {
            $r = Paystack::transfer($pay, (string)$w['recipient_code'], (string)$w['transfer_ref'], 'PlugTheList payout');
        } catch (\Throwable $e) {
            error_log('[transfer] ' . $e->getMessage());
                                                                                                                        
            Notifier::admins('Payout needs a check', 'Withdrawal #' . $id . ' transfer result unknown. Check Paystack before retrying.', url('admin', '/withdrawals'));
            return 'We could not confirm the transfer. An admin will check it.';
        }
        if (!$r['ok']) {
            self::fail($id, 'Transfer was rejected by the bank network.');
            return 'The transfer could not be started. Your money is back in your wallet.';
        }
        DB::update('withdrawals', ['transfer_code' => $r['code']], 'id = ?', [$id]);
        if ($r['status'] === 'success' || Paystack::mock()) {
            self::complete($id);
        }
        return null;
    }

    public static function complete(int $id): void
    {
        DB::tx(static function () use ($id): void {
            $w = DB::one('SELECT * FROM withdrawals WHERE id = ?' . DB::forUpdate(), [$id]);
            if ($w === null || !in_array($w['status'], ['processing', 'requested'], true)) {
                return;
            }
            $amt = (int)$w['amount_kobo'];
            $fee = (int)$w['fee_kobo'];
            $lines = ['hold:withdrawal' => -$amt, 'ext:bank_out' => $amt - $fee];
            if ($fee > 0) {
                $lines['platform'] = $fee;
            }
            Ledger::post('wd_paid', 'wdpaid:' . $id, $lines, null, 'withdrawal ' . $id);
            DB::update('withdrawals', ['status' => 'paid'], 'id = ?', [$id]);
            Notifier::to((int)$w['user_id'], 'Payout sent: ' . naira($amt - $fee), 'Sent to ' . $w['bank_name'] . ' ending ' . $w['last4'] . '.', url('curator', '/wallet'));
        });
    }

    public static function fail(int $id, string $why): void
    {
        DB::tx(static function () use ($id, $why): void {
            $w = DB::one('SELECT * FROM withdrawals WHERE id = ?' . DB::forUpdate(), [$id]);
            if ($w === null || !in_array($w['status'], ['processing', 'requested'], true)) {
                return;
            }
            self::returnFunds($w);
            DB::update('withdrawals', ['status' => 'failed', 'note' => mb_substr($why, 0, 300)], 'id = ?', [$id]);
            Notifier::to((int)$w['user_id'], 'Payout failed', 'The transfer did not go through. ' . naira((int)$w['amount_kobo']) . ' is back in your wallet.', url('curator', '/wallet'));
        });
    }
}
