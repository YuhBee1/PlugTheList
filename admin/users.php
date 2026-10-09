<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Audit;
use PTL\Auth;
use PTL\DB;
use PTL\Ledger;
use PTL\Security;
use PTL\Session;

$u = Auth::require('admin');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    $id = (int)post('id');
    $a = post('action');
    $t = DB::one('SELECT id, role, status FROM users WHERE id = ?', [$id]);
    if ($t && $t['role'] !== 'admin' && in_array($a, ['suspend', 'restore'], true)) {
        DB::update('users', ['status' => $a === 'suspend' ? 'suspended' : 'active'], 'id = ?', [$id]);
        if ($a === 'suspend') {
            Session::revokeAll($id);
            DB::update('listings', ['status' => 'paused'], 'curator_id = ? AND status = ?', [$id, 'approved']);
        }
        Audit::log((int)$u['id'], 'user_' . $a, 'user', $id);
        flash('ok', 'Done.');
    }
    redirect(url('admin', '/users'));
}
$q = trim(qs('q'));
$rows = DB::all('SELECT * FROM users WHERE deleted_at IS NULL' . ($q !== '' ? ' AND (email LIKE ? OR display_name LIKE ? OR full_name LIKE ?)' : '') . ' ORDER BY id DESC LIMIT 100', $q !== '' ? array_fill(0, 3, '%' . str_replace(['%', '_'], '', $q) . '%') : []);
page_header(['title' => 'Users', 'area' => 'admin']);
echo '<main class="wrap"><h1>Users</h1><form method="get" class="filters">' . field('q', 'Search name or email', 'search', $q) . '<div class="field"><button class="btn" type="submit">Search</button></div></form><table class="tbl wide"><thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Verified</th><th>2FA</th><th>Wallet</th><th>Status</th><th></th></tr></thead><tbody>';
foreach ($rows as $r) {
    echo '<tr><td>' . e((string)$r['display_name']) . '<br><small>' . e((string)$r['full_name']) . '</small></td><td>' . e((string)$r['role']) . '</td><td>' . e((string)$r['email']) . '</td><td>' . ($r['email_verified_at'] ? 'Yes' : 'No') . '</td><td>' . ((int)$r['totp_enabled'] ? 'On' : 'Off') . '</td><td>' . e(naira(Ledger::userBalance((int)$r['id']))) . '</td><td>' . status_badge((string)$r['status']) . '</td><td>';
    if ($r['role'] !== 'admin') {
        $act = $r['status'] === 'active' ? 'suspend' : 'restore';
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="action" value="' . $act . '"><button class="btn small ghost" type="submit">' . ucfirst($act) . '</button></form>';
    }
    echo '</td></tr>';
}
echo '</tbody></table></main>';
page_footer();
