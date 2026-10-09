<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;

Auth::require('admin');
$rows = DB::all("SELECT o.*, a.display_name AS creative_name, c.display_name AS curator_name FROM orders o JOIN users a ON a.id = o.creative_id JOIN users c ON c.id = o.curator_id WHERE o.status = 'disputed' ORDER BY o.disputed_at");
page_header(['title' => 'Disputes', 'area' => 'admin']);
echo '<main class="wrap"><h1>Open disputes</h1>';
if (!$rows) {
    echo '<p class="empty">No open disputes.</p>';
}
foreach ($rows as $o) {
    echo '<section class="panel"><h2><a href="' . e(url('admin', '/order?id=' . (int)$o['id'])) . '">' . e((string)$o['ref']) . '</a> ' . e(naira((int)$o['total_kobo'])) . '</h2><p>' . e((string)$o['creative_name']) . ' vs ' . e((string)$o['curator_name']) . ' · opened ' . e(ago((string)$o['disputed_at'])) . '</p><p class="prose">' . nl2br(e(mb_substr((string)$o['dispute_reason'], 0, 400))) . '</p></section>';
}
echo '</main>';
page_footer();
