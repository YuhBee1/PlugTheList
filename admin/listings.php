<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\Catalog;
use PTL\DB;
use PTL\Listings;
use PTL\Security;

$u = Auth::require('admin');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    $id = (int)post('id');
    $a = post('action');
    if ($a === 'approve') {
        Listings::setStatus((int)$u['id'], $id, 'approved', '', true);
        flash('ok', 'Approved and marked verified.');
    } elseif ($a === 'reject') {
        Listings::setStatus((int)$u['id'], $id, 'rejected', mb_substr(post('reason', 'Could not verify ownership.'), 0, 300));
        flash('ok', 'Rejected.');
    } elseif ($a === 'pause') {
        Listings::setStatus((int)$u['id'], $id, 'paused');
        flash('ok', 'Paused.');
    }
    redirect(url('admin', '/listings?status=' . rawurlencode(qs('status', 'pending'))));
}
$status = in_array(qs('status', 'pending'), ['pending', 'approved', 'rejected', 'paused'], true) ? qs('status', 'pending') : 'pending';
$rows = DB::all('SELECT l.*, u.display_name AS curator_name, u.email FROM listings l JOIN users u ON u.id = l.curator_id WHERE l.status = ? ORDER BY l.id DESC LIMIT 100', [$status]);
page_header(['title' => 'Listings', 'area' => 'admin']);
echo '<main class="wrap"><h1>Listings</h1><p class="tabs">';
foreach (['pending', 'approved', 'rejected', 'paused'] as $s) {
    echo '<a href="' . e(url('admin', '/listings?status=' . $s)) . '"' . ($s === $status ? ' class="on"' : '') . '>' . ucfirst($s) . '</a>';
}
echo '</p>';
if (!$rows) {
    echo '<p class="empty">Nothing here.</p>';
}
foreach ($rows as $l) {
    echo '<section class="panel"><h2>' . e((string)$l['title']) . ' ' . status_badge((string)$l['status']) . '</h2><p>' . e(Catalog::platformLabel((string)$l['platform'])) . ' · ' . e(number_format((int)$l['followers'])) . ' followers · ' . e((string)$l['curator_name']) . ' (' . e((string)$l['email']) . ')</p>'
        . '<p>' . ext_link((string)$l['url']) . '</p><p>Verification code to look for: <code>' . e((string)$l['verify_code']) . '</code></p><p class="prose">' . nl2br(e((string)$l['description'])) . '</p>';
    echo '<ul class="plain">';
    foreach (Listings::offers((int)$l['id'], false) as $o) {
        echo '<li>' . e(Catalog::serviceLabel((string)$o['service'])) . ': ' . e(naira((int)$o['price_kobo'])) . ', ' . (int)$o['turnaround_days'] . ' days' . ($o['active'] ? '' : ' (off)') . '</li>';
    }
    echo '</ul>';
    echo '<div class="actions"><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$l['id'] . '"><input type="hidden" name="action" value="approve"><button class="btn small" type="submit">Approve and verify</button></form>'
        . '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$l['id'] . '"><input type="hidden" name="action" value="reject"><input name="reason" placeholder="Reason shown to curator" maxlength="300"><button class="btn small ghost" type="submit">Reject</button></form>'
        . ($l['status'] === 'approved' ? '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$l['id'] . '"><input type="hidden" name="action" value="pause"><button class="btn small ghost" type="submit">Pause</button></form>' : '') . '</div></section>';
}
echo '</main>';
page_footer();
