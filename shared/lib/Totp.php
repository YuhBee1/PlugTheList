<?php
declare(strict_types=1);

namespace PTL;

                                                                                                           
final class Totp
{
    private const ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::b32encode(random_bytes(20));
    }

    public static function b32encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHA[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function b32decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin((int)strpos(self::ALPHA, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secret, ?int $time = null, int $digits = 6): string
    {
        $counter = intdiv($time ?? time(), 30);
        return self::hotp($secret, $counter, $digits);
    }

    private static function hotp(string $secret, int $counter, int $digits): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), self::b32decode($secret), true);
        $o = ord($hash[19]) & 0x0f;
        $bin = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string)($bin % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

       
                                                                                        
                                                                     
       
    public static function verify(string $secret, string $input, int $lastStep = 0, ?int $time = null): ?int
    {
        $input = preg_replace('/\s+/', '', $input) ?? '';
        if (!preg_match('/^\d{6}$/', $input)) {
            return null;
        }
        $step = intdiv($time ?? time(), 30);
        for ($d = -1; $d <= 1; $d++) {
            $s = $step + $d;
            if ($s > $lastStep && hash_equals(self::hotp($secret, $s, 6), $input)) {
                return $s;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer = 'PlugTheList'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }
}
