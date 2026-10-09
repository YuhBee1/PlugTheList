<?php
declare(strict_types=1);

namespace PTL;

   
                                                                
                                                                                                  
   
final class Csrf
{
    public static function token(): string
    {
        $row = Session::row();
        if ($row !== null) {
            return (string)$row['csrf'];
        }
        return Crypto::hmac('csrf-guest', self::guestId());
    }

    private static function guestId(): string
    {
        $v = Security::getCookie('ptl_g');
        if (!preg_match('/^[a-f0-9]{64}$/', $v)) {
            $v = bin2hex(random_bytes(32));
            Security::setCookie('ptl_g', $v, time() + 8 * 3600, false);
        }
        return $v;
    }

    public static function valid(string $t): bool
    {
        return $t !== '' && hash_equals(self::token(), $t);
    }
}
