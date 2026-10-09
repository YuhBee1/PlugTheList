<?php
declare(strict_types=1);

namespace PTL;

                                                                                     
final class OrderState
{
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PAID = 'paid';                                                                  
    public const IN_PROGRESS = 'in_progress';                    
    public const DELIVERED = 'delivered';                                                     
    public const COMPLETED = 'completed';                                  
    public const DISPUTED = 'disputed';
    public const REFUNDED = 'refunded';
    public const CANCELLED = 'cancelled';                  

    private const MAP = [
        self::AWAITING_PAYMENT => [self::PAID, self::CANCELLED],
        self::PAID => [self::IN_PROGRESS, self::REFUNDED],
        self::IN_PROGRESS => [self::DELIVERED, self::REFUNDED, self::DISPUTED],
        self::DELIVERED => [self::COMPLETED, self::DISPUTED],
        self::DISPUTED => [self::COMPLETED, self::REFUNDED],
        self::COMPLETED => [],
        self::REFUNDED => [],
        self::CANCELLED => [],
    ];

    public static function can(string $from, string $to): bool
    {
        return in_array($to, self::MAP[$from] ?? [], true);
    }

    public static function label(string $s): string
    {
        return match ($s) {
            self::AWAITING_PAYMENT => 'Awaiting payment',
            self::PAID => 'Paid, waiting for curator',
            self::IN_PROGRESS => 'In progress',
            self::DELIVERED => 'Delivered, awaiting approval',
            self::COMPLETED => 'Completed',
            self::DISPUTED => 'In dispute',
            self::REFUNDED => 'Refunded',
            self::CANCELLED => 'Cancelled',
            default => $s,
        };
    }

                                              
    public static function holdsEscrow(string $s): bool
    {
        return in_array($s, [self::PAID, self::IN_PROGRESS, self::DELIVERED, self::DISPUTED], true);
    }
}
