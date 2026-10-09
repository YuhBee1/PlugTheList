<?php
declare(strict_types=1);

namespace PTL;

                                                                                                 
final class Validator
{
    private const COMMON = ['password', 'password1', '1234567890', '12345678910', 'qwertyuiop', 'iloveyou123', 'plugthelist', 'welcome123', 'admin12345', 'passw0rd123', 'abcdefghij', '0123456789'];

    public static function email(string $s): ?string
    {
        $s = strtolower(trim($s));
        if ($s === '' || strlen($s) > 190 || !filter_var($s, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $s;
    }

    public static function name(string $s, int $min = 2, int $max = 80): ?string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        $len = mb_strlen($s);
        if ($len < $min || $len > $max || preg_match('/[<>\x00-\x1f]/u', $s)) {
            return null;
        }
        return $s;
    }

                                                                                              
    public static function phone(string $s): ?string
    {
        $d = preg_replace('/[^\d+]/', '', trim($s)) ?? '';
        $d = ltrim($d, '+');
        if (preg_match('/^0([789][01]\d{8})$/', $d, $m)) {
            return '+234' . $m[1];
        }
        if (preg_match('/^234([789][01]\d{8})$/', $d, $m)) {
            return '+234' . $m[1];
        }
        return null;
    }

                                                             
    public static function passwordProblems(string $p, string $email = ''): array
    {
        $out = [];
        if (mb_strlen($p) < 10) {
            $out[] = 'Use at least 10 characters.';
        }
        if (strlen($p) > 200) {
            $out[] = 'Use at most 200 characters.';
        }
        if (in_array(strtolower($p), self::COMMON, true) || preg_match('/^(.)\1+$/u', $p)) {
            $out[] = 'That password is too easy to guess.';
        }
        $local = strtolower(strtok($email, '@') ?: '');
        if ($local !== '' && strlen($local) >= 4 && str_contains(strtolower($p), $local)) {
            $out[] = 'Do not include your email name in your password.';
        }
        if (!preg_match('/\pL/u', $p) || !preg_match('/\d/', $p)) {
            $out[] = 'Mix letters and numbers.';
        }
        return $out;
    }

                                                        
    public static function url(string $s, int $max = 300): ?string
    {
        $s = trim($s);
        if ($s === '' || strlen($s) > $max || preg_match('/[\s<>"\'\x00-\x1f]/', $s)) {
            return null;
        }
        $p = parse_url($s);
        if ($p === false || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        $h = strtolower($p['host']);
        if (!str_contains($h, '.') || filter_var($h, FILTER_VALIDATE_IP) || preg_match('/(^|\.)(localhost|local|internal|test)$/', $h)) {
            return null;
        }
        return $s;
    }

    public static function text(string $s, int $min, int $max): ?string
    {
        $s = trim(str_replace("\r\n", "\n", $s));
        $len = mb_strlen($s);
        if ($len < $min || $len > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $s)) {
            return null;
        }
        return $s;
    }

    public static function intRange(string $s, int $min, int $max): ?int
    {
        $s = str_replace([',', ' '], '', trim($s));
        if (!preg_match('/^\d{1,12}$/', $s)) {
            return null;
        }
        $n = (int)$s;
        return ($n < $min || $n > $max) ? null : $n;
    }

                                                   
    public static function oneOf(string $s, array $options): ?string
    {
        return in_array($s, array_map('strval', array_keys($options) === range(0, count($options) - 1) ? $options : array_keys($options)), true) ? $s : null;
    }
}
