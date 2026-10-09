<?php
declare(strict_types=1);

                                                                                   

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function env(string $k, ?string $d = null): ?string
{
    return \PTL\Env::get($k, $d);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function base_domain(): string
{
    return strtolower((string)env('BASE_DOMAIN', 'plugthelist.com'));
}

function url_scheme(): string
{
    return env('URL_SCHEME', 'https') === 'http' ? 'http' : 'https';
}

                                                                              
function url(string $sub, string $path = '/'): string
{
    if (env('APP_ROUTING_MODE') === 'path') {
        $base = trim((string)env('APP_BASE_URL', ''));
        if ($base === '') {
            $deploymentHost = trim((string)env('VERCEL_URL', ''));
            if ($deploymentHost === '') {
                $deploymentHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
            }
            if ($deploymentHost !== '' && preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $deploymentHost)) {
                $base = 'https://' . $deploymentHost;
            } else {
                $base = url_scheme() . '://' . base_domain();
            }
        }
        $parsed = parse_url($base);
        if ($parsed === false || !in_array(strtolower((string)($parsed['scheme'] ?? '')), ['http', 'https'], true) || empty($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])) {
            throw new \RuntimeException('APP_BASE_URL must be a valid HTTP(S) origin.');
        }
        $origin = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        $prefix = $sub === 'www' ? '' : '/' . rawurlencode($sub);
        return rtrim($origin, '/') . $prefix . $path;
    }
    $override = env('URL_' . strtoupper($sub));                                                 
    if ($override) {
        return rtrim($override, '/') . $path;
    }
    $host = $sub === 'www' ? base_domain() : $sub . '.' . base_domain();
    return url_scheme() . '://' . $host . $path;
}

function origin_of(string $u): string
{
    $p = parse_url($u);
    if ($p === false || !isset($p['host'])) {
        return '';
    }
    return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
}

                                 
function all_origins(): array
{
    $o = [];
    foreach (['www', 'auth', 'curator', 'app', 'admin', 'api'] as $s) {
        $o[] = origin_of(url($s, '/'));
    }
    return array_values(array_unique($o));
}

function asset(string $path): string
{
    $base = env('ASSET_URL') ?: url('www', '/assets');
    $p = ltrim($path, '/');
    return rtrim($base, '/') . '/' . $p . ($p !== '' ? '?v=' . PTL_VERSION : '');
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out(array $data, int $code = 200): never
{
    if (!headers_sent()) {
        http_response_code($code);
        \PTL\Security::apiHeaders();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code, string $msg = ''): never
{
    if (defined('PTL_API')) {
        json_out(['ok' => false, 'error' => $msg !== '' ? $msg : 'Error'], $code);
    }
    $titles = [
        403 => 'Not allowed', 404 => 'Page not found', 405 => 'Method not allowed',
        419 => 'Session expired', 429 => 'Slow down', 500 => 'Something went wrong', 503 => 'Back soon',
    ];
    $title = $titles[$code] ?? 'Error';
    $default = [
        404 => 'That page does not exist or has moved.',
        403 => 'You do not have access to this page.',
    ][$code] ?? 'Please go back and try again.';
    if (!headers_sent()) {
        http_response_code($code);
    }
    page_header(['title' => $title, 'area' => 'public', 'noindex' => true]);
    echo '<main class="wrap narrow"><h1>' . e($title) . '</h1><p class="lede">' . e($msg !== '' ? $msg : $default) . '</p>'
        . '<p><a class="btn" href="' . e(url('www', '/')) . '">Back to PlugTheList</a></p></main>';
    page_footer();
    exit;
}

function naira(int $kobo): string
{
    $n = intdiv($kobo, 100);
    $k = $kobo % 100;
    return '₦' . number_format($n) . ($k !== 0 ? '.' . str_pad((string)$k, 2, '0', STR_PAD_LEFT) : '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(\PTL\Csrf::token()) . '">';
}

function flash(string $type, string $msg): void
{
    \PTL\Flash::add($type, $msg);
}

                                                       
function post(string $k, string $d = ''): string
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

function qs(string $k, string $d = ''): string
{
    $v = $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

function client_ip(): string
{
    return \PTL\Security::ip();
}

function fmt_dt(?string $dt): string
{
    return $dt ? date('j M Y, g:ia', strtotime($dt)) : '-';
}

function fmt_d(?string $dt): string
{
    return $dt ? date('j M Y', strtotime($dt)) : '-';
}

function ago(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $s = time() - strtotime($dt);
    if ($s < 60) {
        return 'just now';
    }
    if ($s < 3600) {
        return intdiv($s, 60) . ' min ago';
    }
    if ($s < 86400) {
        return intdiv($s, 3600) . ' hr ago';
    }
    if ($s < 86400 * 14) {
        return intdiv($s, 86400) . ' days ago';
    }
    return fmt_d($dt);
}

function until(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $s = strtotime($dt) - time();
    if ($s <= 0) {
        return 'now';
    }
    if ($s < 3600) {
        return 'in ' . max(1, intdiv($s, 60)) . ' min';
    }
    if ($s < 86400) {
        return 'in ' . intdiv($s, 3600) . ' hr';
    }
    return 'in ' . intdiv($s, 86400) . ' days';
}

function pluralise(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

function short_num(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
    }
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    }
    return (string)$n;
}

                                            
function ext_link(string $url, ?string $label = null): string
{
    $label = $label ?? $url;
    return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer nofollow ugc">' . e($label) . '</a>';
}
