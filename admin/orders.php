<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;

Auth::require('admin');
$s = qs('status');
$rows = DB::all('SELECT o.*, a.display_name AS creative_name, c.display_name AS curator_name FROM orders o JOIN users a ON a.id = o.creative_id JOIN users c ON c.id = o.curator_id' . ($s !== '' ? ' WHERE o.status = ?' : '') . ' ORDER BY o.id DESC LIMIT 150', $s !== '' ? [$s] : []);
page_header(['title' => 'Orders', 'area' => 'admin']);
echo '<main class="wrap"><h1>Orders</h1><form method="get" class="filters"><div class="field"><label for="f_status">Status</label><select id="f_status" name="status"><option value="">All</option>';
foreach (['awaiting_payment', 'paid', 'in_progress', 'delivered', 'disputed', 'completed', 'refunded', 'cancelled'] as $x) {
    echo '<option value="' . $x . '"' . ($x === $s ? ' selected' : '') . '>' . e(PTL\OrderState::label($x)) . '</option>';
}
echo '</select></div><div class="field"><button class="btn" type="submit">Filter</button></div></form><table class="tbl wide"><thead><tr><th>Ref</th><th>Creative</th><th>Curator</th><th>Total</th><th>Status</th><th>Created</th></tr></thead><tbody>';
foreach ($rows as $o) {
    echo '<tr><td><a href="' . e(url('admin', '/order?id=' . (int)$o['id'])) . '">' . e((string)$o['ref']) . '</a></td><td>' . e((string)$o['creative_name']) . '</td><td>' . e((string)$o['curator_name']) . '</td><td>' . e(naira((int)$o['total_kobo'])) . '</td><td>' . status_badge((string)$o['status']) . '</td><td>' . e(fmt_dt((string)$o['created_at'])) . '</td></tr>';
}
echo '</tbody></table></main>';
page_footer();
