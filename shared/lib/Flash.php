<?php
declare(strict_types=1);

namespace PTL;

                                                                                                           
final class Flash
{
                                                         
    private static ?array $pending = null;

    public static function add(string $type, string $msg): void
    {
        $list = self::read();
        $list[] = [in_array($type, ['ok', 'error', 'info'], true) ? $type : 'info', mb_substr($msg, 0, 300)];
        $list = array_slice($list, -3);
        $json = json_encode($list, JSON_UNESCAPED_UNICODE);
        $b = rtrim(strtr(base64_encode((string)$json), '+/', '-_'), '=');
        Security::setCookie('ptl_flash', $b . '.' . Crypto::hmac('flash', $b), time() + 120, true);
        self::$pending = $list;
    }

                                                       
    private static function read(): array
    {
        if (self::$pending !== null) {
            return self::$pending;
        }
        $c = Security::getCookie('ptl_flash');
        if ($c === '' || !str_contains($c, '.')) {
            return [];
        }
        [$b, $sig] = explode('.', $c, 2);
        if (!hash_equals(Crypto::hmac('flash', $b), $sig)) {
            return [];
        }
        $d = json_decode((string)base64_decode(strtr($b, '-_', '+/'), true), true);
        return is_array($d) ? $d : [];
    }

                                                                      
    public static function take(): array
    {
        $list = self::read();
        if ($list !== [] && !headers_sent()) {
            Security::deleteCookie('ptl_flash', true);
        }
        self::$pending = [];
        return $list;
    }
}
