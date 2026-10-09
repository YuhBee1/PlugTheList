<?php
declare(strict_types=1);
define('PTL_NO_SESSION', true);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\DB;
header('Content-Type: application/xml; charset=utf-8');
$u = [url('www', '/'), url('www', '/browse'), url('www', '/how-it-works'), url('www', '/pricing'), url('www', '/terms'), url('www', '/privacy')];
foreach (DB::all("SELECT id FROM listings WHERE status = 'approved' ORDER BY id DESC LIMIT 5000") as $r) {
    $u[] = url('www', '/listing?id=' . (int)$r['id']);
}
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($u as $x) {
    echo '<url><loc>' . htmlspecialchars($x, ENT_XML1) . '</loc></url>';
}
echo '</urlset>';
