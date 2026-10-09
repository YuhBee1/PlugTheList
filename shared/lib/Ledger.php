<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                                       
                                                                                                             
                                                                                                       
   
final class Ledger
{
       
                                                                             
                                                                    
       
    public static function post(string $kind, string $idem, array $lines, ?int $orderId = null, string $memo = ''): bool
    {
        if (array_sum($lines) !== 0) {
            throw new \LogicException('Ledger transaction does not balance: ' . $kind);
        }
        return (bool)DB::tx(static function () use ($kind, $idem, $lines, $orderId, $memo): bool {
            if (!DB::insertIgnore('ledger_txns', ['kind' => $kind, 'order_id' => $orderId, 'idem' => $idem, 'memo' => mb_substr($memo, 0, 200)])) {
                return false;
            }
            $id = (int)DB::val('SELECT id FROM ledger_txns WHERE idem = ?', [$idem]);
            foreach ($lines as $acct => $amt) {
                if ($amt !== 0) {
                    DB::insert('ledger_lines', ['txn_id' => $id, 'account' => $acct, 'amount_kobo' => $amt]);
                }
            }
            return true;
        });
    }

    public static function balance(string $account): int
    {
        return (int)DB::val('SELECT COALESCE(SUM(amount_kobo),0) FROM ledger_lines WHERE account = ?', [$account]);
    }

    public static function userBalance(int $userId): int
    {
        return self::balance('user:' . $userId);
    }

                                                                           
    public static function drift(): int
    {
        return (int)DB::val('SELECT COALESCE(SUM(amount_kobo),0) FROM ledger_lines');
    }

                                                                                              
    public static function escrowCheck(): array
    {
        $exp = (int)DB::val("SELECT COALESCE(SUM(total_kobo),0) FROM orders WHERE status IN ('paid','in_progress','delivered','disputed')");
        return [self::balance('escrow'), $exp];
    }
}
