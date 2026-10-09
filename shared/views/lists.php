<?php
declare(strict_types=1);

use PTL\Auth;
use PTL\DB;
use PTL\Notifier;
use PTL\Security;

function notifications_page(): void
{
    $u = Auth::require();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Security::requirePost();
        DB::update('notifications', ['read_at' => now()], 'user_id = ? AND read_at IS NULL', [$u['id']], false);
        redirect(url($u['role'] === 'curator' ? 'curator' : ($u['role'] === 'admin' ? 'admin' : 'app'), '/notifications'));
    }
    $rows = DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 60', [$u['id']]);
    $unread = Notifier::unread((int)$u['id']);
    page_header(['title' => 'Notifications', 'area' => $u['role']]);
    echo '<main class="wrap narrow"><h1>Notifications</h1>';
    if ($unread) {
        echo '<form method="post">' . csrf_field() . '<button class="btn ghost" type="submit">Mark all read</button></form>';
    }
    if (!$rows) {
        echo '<p class="empty">Nothing yet. Updates about your orders show up here and in your email.</p>';
    }
    echo '<ul class="notes">';
    foreach ($rows as $r) {
        $inner = '<strong>' . e((string)$r['title']) . '</strong><span>' . e((string)$r['body']) . '</span><small>' . e(ago((string)$r['created_at'])) . '</small>';
        $safe = $r['url'] ? Security::safeNext((string)$r['url'], '') : '';
        echo '<li class="' . ($r['read_at'] ? '' : 'unread') . '">' . ($safe !== '' ? '<a href="' . e($safe) . '">' . $inner . '</a>' : $inner) . '</li>';
    }
    echo '</ul></main>';
    page_footer();
}

                                         
function orders_page(string $role): void
{
    $u = Auth::require($role);
    $col = $role === 'creative' ? 'creative_id' : 'curator_id';
    $filter = qs('s');
    $groups = ['active' => ['awaiting_payment', 'paid', 'in_progress', 'delivered', 'disputed'], 'done' => ['completed', 'refunded', 'cancelled']];
    $in = $groups[$filter] ?? null;
    $w = $in ? ' AND status IN (' . implode(',', array_fill(0, count($in), '?')) . ')' : '';
    $rows = DB::all("SELECT * FROM orders WHERE $col = ?$w ORDER BY id DESC LIMIT 100", array_merge([$u['id']], $in ?? []));
    $area = $role === 'creative' ? 'app' : 'curator';
    page_header(['title' => $role === 'creative' ? 'My bookings' : 'Orders', 'area' => $role]);
    echo '<main class="wrap"><h1>' . ($role === 'creative' ? 'My bookings' : 'Orders') . '</h1><p class="tabs"><a href="' . e(url($area, '/orders')) . '"' . (!$in ? ' class="on"' : '') . '>All</a><a href="' . e(url($area, '/orders?s=active')) . '"' . ($filter === 'active' ? ' class="on"' : '') . '>Active</a><a href="' . e(url($area, '/orders?s=done')) . '"' . ($filter === 'done' ? ' class="on"' : '') . '>Finished</a></p>';
    if (!$rows) {
        echo '<p class="empty">' . ($role === 'creative' ? 'No bookings yet. <a href="' . e(url('www', '/browse')) . '">Browse curators</a> to book your first.' : 'No orders yet. Bookings appear here once an artist pays.') . '</p>';
    } else {
        echo '<table class="tbl wide"><thead><tr><th>Ref</th><th>Song</th><th>Listing</th><th>' . ($role === 'creative' ? 'Total' : 'You earn') . '</th><th>Status</th><th>Updated</th></tr></thead><tbody>';
        foreach ($rows as $o) {
            $amt = $role === 'creative' ? (int)$o['total_kobo'] : (int)$o['price_kobo'] - (int)$o['curator_fee_kobo'];
            echo '<tr><td><a href="' . e(url($area, '/order?id=' . (int)$o['id'])) . '">' . e((string)$o['ref']) . '</a></td><td>' . e((string)$o['track_title']) . '</td><td>' . e((string)$o['listing_title']) . '</td><td>' . e(naira($amt)) . '</td><td>' . status_badge((string)$o['status']) . '</td><td>' . e(ago((string)($o['updated_at'] ?? $o['created_at']))) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</main>';
    page_footer();
}
