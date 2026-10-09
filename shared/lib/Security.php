<?php
declare(strict_types=1);

namespace PTL;

final class Security
{
                                                                            
    public static function boot(): void
    {
        self::headers();
        Session::start();
        if (Session::row() === null) {
            Csrf::token();                                                             
        }
    }

    public static function secure(): bool
    {
        return url_scheme() === 'https';
    }

    public static function cookieName(string $n): string
    {
        return self::secure() ? '__Secure-' . $n : $n;
    }

    public static function setCookie(string $name, string $value, int $expires = 0, bool $shared = true): void
    {
        $domain = $shared && Env::get('APP_ROUTING_MODE') !== 'path' ? (Env::get('COOKIE_DOMAIN') ?? '') : '';
        $full = self::cookieName($name);
        if (!headers_sent()) {
            setcookie($full, $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => $domain,
            'secure' => self::secure(),
            'httponly' => true,
            'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$full] = $value;
    }

    public static function getCookie(string $name): string
    {
        $v = $_COOKIE[self::cookieName($name)] ?? '';
        return is_string($v) ? $v : '';
    }

    public static function deleteCookie(string $name, bool $shared = true): void
    {
        $full = self::cookieName($name);
        $domain = $shared && Env::get('APP_ROUTING_MODE') !== 'path' ? (Env::get('COOKIE_DOMAIN') ?? '') : '';
        setcookie($full, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $domain,
            'secure' => self::secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$full]);
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                                                                                                                          
        if (Env::bool('TRUST_CLOUDFLARE') && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $c = (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
            if (filter_var($c, FILTER_VALIDATE_IP)) {
                $ip = $c;
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        $asset = origin_of(asset(''));
        $formTargets = array_merge(["'self'"], all_origins(), ['https://checkout.paystack.com']);
        $csp = [
            "default-src 'self'",
            "script-src 'self' $asset",
            "style-src 'self' $asset",
            "img-src 'self' data: $asset",
            "font-src 'self' $asset",
            "connect-src 'self'",
            'form-action ' . implode(' ', array_unique($formTargets)),
            "base-uri 'none'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ];
        if (self::secure()) {
            $csp[] = 'upgrade-insecure-requests';
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-site');
        header('Content-Type: text/html; charset=utf-8');
    }

                                              
    public static function apiHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    }

    public static function noStore(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
        }
    }

                                                                      
    public static function safeNext(string $next, string $fallback): string
    {
        if ($next === '' || strlen($next) > 600) {
            return $fallback;
        }
        $p = parse_url($next);
        if ($p === false || !isset($p['scheme'], $p['host']) || isset($p['user']) || isset($p['pass'])) {
            return $fallback;
        }
        if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            return $fallback;
        }
        $origin = strtolower($p['scheme']) . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '');
        return in_array($origin, array_map('strtolower', all_origins()), true) ? $next : $fallback;
    }

    public static function checkOrigin(): void
    {
        $allowed = array_map('strtolower', all_origins());
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && $origin !== 'null') {
            if (!in_array(strtolower($origin), $allowed, true)) {
                abort(403, 'Blocked: this request did not come from PlugTheList.');
            }
            return;
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '' && !in_array(strtolower(origin_of($ref)), $allowed, true)) {
            abort(403, 'Blocked: this request did not come from PlugTheList.');
        }
    }

                                                  
    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            abort(405, 'Method not allowed.');
        }
        self::checkOrigin();
        $t = $_POST['_csrf'] ?? '';
        if (!is_string($t) || !Csrf::valid($t)) {
            abort(419, 'Your session expired. Go back, refresh the page and try again.');
        }
    }
}
