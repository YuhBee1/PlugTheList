<?php
declare(strict_types=1);

namespace PTL;

                                                                                                                                            
final class Crypto
{
    private static function master(): string
    {
        $k = base64_decode(Env::must('APP_KEY'), true);
        if ($k === false || strlen($k) < 32) {
            throw new \RuntimeException('APP_KEY must be base64 of at least 32 random bytes.');
        }
        return $k;
    }

    private static function subkey(string $purpose): string
    {
        return hash_hmac('sha256', 'ptl:' . $purpose, self::master(), true);
    }

    public static function hmac(string $purpose, string $data): string
    {
        return hash_hmac('sha256', $data, self::subkey($purpose));
    }

                                                                                
    public static function seal(string $plain, string $purpose): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ct = sodium_crypto_secretbox($plain, $nonce, self::subkey('seal:' . $purpose));
        return base64_encode($nonce . $ct);
    }

    public static function open(string $sealed, string $purpose): ?string
    {
        $raw = base64_decode($sealed, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ct = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $pt = sodium_crypto_secretbox_open($ct, $nonce, self::subkey('seal:' . $purpose));
        return $pt === false ? null : $pt;
    }

                                                                         
    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
