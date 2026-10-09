<?php
                                                                             
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = $_SERVER['DOCUMENT_ROOT'];
if ($path !== '/' && is_file($root . $path)) {
    return false;
}
$map = ['/register/curator' => 'register-curator.php', '/register/creative' => 'register-creative.php', '/robots.txt' => 'robots.php', '/sitemap.xml' => 'sitemap.php', '/webhook' => 'paystack-webhook.php'];
$p = rtrim($path, '/') ?: '/';
if (isset($map[$p]) && is_file($root . '/' . $map[$p])) {
    require $root . '/' . $map[$p];
    return true;
}
if (preg_match('#^/([A-Za-z0-9_-]+)$#', $p, $m) && is_file($root . '/' . $m[1] . '.php')) {
    require $root . '/' . $m[1] . '.php';
    return true;
}
if ($p === '/' && is_file($root . '/index.php')) {
    require $root . '/index.php';
    return true;
}
http_response_code(404);
echo 'not found';
