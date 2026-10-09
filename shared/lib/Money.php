<?php
declare(strict_types=1);

namespace PTL;

                                                                                                           
final class Money
{
                                                                 
    public static function bps(int $kobo, int $bps): int
    {
        return intdiv($kobo * $bps + 5000, 10000);
    }

       
                                                                                                    
       
    public static function fees(int $price, int $buyerBps, int $curatorBps): array
    {
        $b = self::bps($price, $buyerBps);
        $c = self::bps($price, $curatorBps);
        return [
            'price' => $price,
            'buyer_fee' => $b,
            'curator_fee' => $c,
            'total' => $price + $b,                                             
            'curator_net' => $price - $c,                              
            'platform' => $b + $c,                                  
        ];
    }

       
                                                                                    
                                                                                                   
                                                       
                                                                         
                                                         
       
    public static function split(array $o, int $share): array
    {
        $share = max(0, min($share, (int)$o['price']));
        if ($o['price'] <= 0) {
            return ['curator' => 0, 'platform' => 0, 'refund' => (int)$o['total']];
        }
        $curatorFee = intdiv($o['curator_fee'] * $share + intdiv($o['price'], 2), $o['price']);
        $buyerFee = intdiv($o['buyer_fee'] * $share + intdiv($o['price'], 2), $o['price']);
        $curator = $share - $curatorFee;
        $platform = $curatorFee + $buyerFee;
        return ['curator' => $curator, 'platform' => $platform, 'refund' => (int)$o['total'] - $curator - $platform];
    }

                                                                                      
    public static function parseNaira(string $s): ?int
    {
        $s = trim(str_replace(['₦', ',', ' ', "\u{00a0}"], '', $s));
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        [$n, $k] = array_pad(explode('.', $s, 2), 2, '0');
        return ((int)$n) * 100 + (int)str_pad($k, 2, '0');
    }
}
