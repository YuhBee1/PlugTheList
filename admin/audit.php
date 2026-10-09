<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\DB;

Auth::require('admin');
$a = preg_replace('/[^a-z_]/', '', qs('action'));
$rows = DB::all('SELECT l.*, u.email FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id' . ($a !== '' ? ' WHERE l.action = ?' : '') . ' ORDER BY l.id DESC LIMIT 200', $a !== '' ? [$a] : []);
page_header(['title' => 'Audit log', 'area' => 'admin']);
echo '<main class="wrap"><h1>Audit log</h1><form method="get" class="filters">' . field('action', 'Filter by action', 'text', $a) . '<div class="field"><button class="btn" type="submit">Filter</button></div></form><table class="tbl wide"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Details</th></tr></thead><tbody>';
foreach ($rows as $r) {
    echo '<tr><td>' . e(fmt_dt((string)$r['created_at'])) . '</td><td>' . e((string)($r['email'] ?? 'system')) . '</td><td>' . e((string)$r['action']) . '</td><td>' . e((string)$r['entity']) . ' ' . e((string)$r['entity_id']) . '</td><td>' . e((string)$r['ip']) . '</td><td><small>' . e(mb_strimwidth((string)$r['meta'], 0, 120, '…')) . '</small></td></tr>';
}
echo '</tbody></table></main>';
page_footer();
