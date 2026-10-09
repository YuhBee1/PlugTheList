<?php
declare(strict_types=1);

namespace PTL;

                                                                                                                 
final class Settings
{
    public const DEFAULTS = [
        'curator_fee_bps' => '1000',                                                 
        'buyer_fee_bps' => '500',                                                              
        'min_listing_price_kobo' => '1000000',              
        'max_listing_price_kobo' => '500000000',               
        'min_withdrawal_kobo' => '1000000',                 
        'withdrawal_fee_kobo' => '0',
        'withdrawals_need_approval' => '1',
        'accept_window_hours' => '48',                                                                
        'auto_release_hours' => '72',                                                                      
        'non_delivery_grace_hours' => '72',                                                        
        'unpaid_expiry_hours' => '24',
        'max_active_orders_default' => '5',
        'listing_auto_approve_spotify' => '1',
        'guaranteed_placement_platforms' => 'audiomack,boomplay,youtube,soundcloud,whatsapp,telegram,instagram,tiktok,x,facebook,other',
    ];

    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            foreach (DB::all('SELECT k, v FROM settings') as $r) {
                self::$cache[$r['k']] = (string)$r['v'];
            }
        }
        return self::$cache;
    }

    public static function get(string $k): string
    {
        return self::load()[$k] ?? (self::DEFAULTS[$k] ?? '');
    }

    public static function int(string $k): int
    {
        return (int)self::get($k);
    }

    public static function set(string $k, string $v): void
    {
        if (!array_key_exists($k, self::DEFAULTS)) {
            throw new \InvalidArgumentException('Unknown setting');
        }
        if (DB::run('UPDATE settings SET v = ?, updated_at = ? WHERE k = ?', [$v, now(), $k]) === 0) {
            DB::insertIgnore('settings', ['k' => $k, 'v' => $v, 'updated_at' => now()], false);
        }
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
