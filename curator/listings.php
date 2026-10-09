<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\Catalog;
use PTL\DB;

$u = Auth::require('curator');
$rows = DB::all('SELECT * FROM listings WHERE curator_id = ? ORDER BY id DESC', [$u['id']]);
page_header(['title' => 'My listings', 'area' => 'curator']);
echo '<main class="wrap"><h1>My listings</h1><p><a class="btn" href="' . e(url('curator', '/listing')) . '">New listing</a></p>';
if (!$rows) {
    echo '<p class="empty">No listings yet. Add a playlist, channel or community and set your prices.</p>';
} else {
    echo '<table class="tbl wide"><thead><tr><th>Name</th><th>Platform</th><th>Audience</th><th>From</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $l) {
        echo '<tr><td><a href="' . e(url('curator', '/listing?id=' . (int)$l['id'])) . '">' . e((string)$l['title']) . '</a></td><td>' . e(Catalog::platformLabel((string)$l['platform'])) . '</td><td>' . e(short_num((int)$l['followers'])) . '</td><td>' . e(naira((int)$l['min_price_kobo'])) . '</td><td>' . status_badge((string)$l['status']) . ($l['verified'] ? '' : ' <span class="muted small">unverified</span>') . '</td></tr>';
    }
    echo '</tbody></table>';
}
echo '</main>';
page_footer();
