<?php
declare(strict_types=1);
define('PTL_NO_SESSION', true);
require __DIR__ . '/../shared/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /file\n\nSitemap: " . url('www', '/sitemap.xml') . "\n";
