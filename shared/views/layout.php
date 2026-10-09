<?php
declare(strict_types=1);

                                                                                       

use PTL\Flash;
use PTL\OrderState;
use PTL\Session;

function role_home(string $role): string
{
    return match ($role) {
        'curator' => url('curator', '/'),
        'admin' => url('admin', '/'),
        default => url('app', '/'),
    };
}

function status_badge(string $status, ?string $label = null): string
{
    $cls = match ($status) {
        'completed', 'approved', 'paid_out', 'success', 'active', 'verified' => 'ok',
        'awaiting_payment', 'pending', 'requested', 'processing', 'in_progress', 'delivered', 'paid' => 'wait',
        'disputed', 'rejected', 'failed', 'suspended' => 'bad',
        default => 'mute',
    };
    $text = $label ?? (str_starts_with($status, 'x:') ? $status : OrderState::label($status));
    if ($text === $status) {
        $text = ucfirst(str_replace('_', ' ', $status));
    }
    return '<span class="badge ' . $cls . '">' . e($text) . '</span>';
}

                                                   
function nav_items(string $area, ?array $u): array
{
    if ($u === null) {
        return [
            [url('www', '/browse'), 'Browse'],
            [url('www', '/how-it-works'), 'How it works'],
            [url('www', '/pricing'), 'Fees'],
        ];
    }
    return match ($u['role']) {
        'curator' => [
            [url('curator', '/'), 'Dashboard'],
            [url('curator', '/listings'), 'My listings'],
            [url('curator', '/orders'), 'Orders'],
            [url('curator', '/wallet'), 'Wallet'],
        ],
        'admin' => [
            [url('admin', '/'), 'Overview'],
            [url('admin', '/listings'), 'Listings'],
            [url('admin', '/orders'), 'Orders'],
            [url('admin', '/disputes'), 'Disputes'],
            [url('admin', '/withdrawals'), 'Withdrawals'],
            [url('admin', '/users'), 'Users'],
            [url('admin', '/settings'), 'Settings'],
            [url('admin', '/audit'), 'Audit'],
        ],
        default => [
            [url('www', '/browse'), 'Browse'],
            [url('app', '/'), 'Dashboard'],
            [url('app', '/orders'), 'Bookings'],
        ],
    };
}

                                                                                                                   
function page_header(array $o = []): void
{
    $title = (string)($o['title'] ?? 'PlugTheList');
    $full = $title === 'PlugTheList' ? 'PlugTheList: book Nigerian playlist and community curators' : $title . ' | PlugTheList';
    $desc = (string)($o['description'] ?? 'Book playlists, channels and communities for your music. Every booking is held in escrow and released only when the work is delivered.');
    $area = (string)($o['area'] ?? 'public');
    $u = Session::user();
    Security_noStoreIfPrivate($area);
    echo "<!doctype html>\n<html lang=\"en\" data-area=\"" . e($area) . "\">\n<head>\n<meta charset=\"utf-8\">\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<title>' . e($full) . "</title>\n";
    echo '<meta name="description" content="' . e($desc) . '">' . "\n";
    if (!empty($o['noindex']) || $area !== 'public') {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
    if (!empty($o['canonical'])) {
        echo '<link rel="canonical" href="' . e((string)$o['canonical']) . '">' . "\n";
    }
    echo '<meta name="color-scheme" content="light dark">' . "\n";
    echo '<meta property="og:title" content="' . e($full) . '"><meta property="og:description" content="' . e($desc) . '"><meta property="og:type" content="website">' . "\n";
    echo '<link rel="icon" href="' . e(asset('favicon.svg')) . '" type="image/svg+xml">' . "\n";
    echo '<script src="' . e(asset('theme.js')) . '"></script>' . "\n";
    echo '<link rel="stylesheet" href="' . e(asset('ptl.css')) . '">' . "\n";
    echo '<script src="' . e(asset('ptl.js')) . '" defer></script>' . "\n";
    echo '</head>' . "\n" . '<body class="area-' . e($area) . (isset($o['body']) ? ' ' . e((string)$o['body']) : '') . '">' . "\n";
    echo '<a class="skip" href="#main">Skip to content</a>' . "\n";
    echo '<div class="stripe" aria-hidden="true"></div>' . "\n";
    echo '<header class="top"><div class="wrap bar">';
    echo '<a class="brand" href="' . e($u ? role_home((string)$u['role']) : url('www', '/')) . '"><span class="mark" aria-hidden="true">P</span><span class="brand-t">PlugTheList</span></a>';
    echo '<button class="navbtn" type="button" aria-expanded="false" aria-controls="nav" data-nav-toggle>Menu</button>';
    echo '<nav id="nav" class="nav" aria-label="Main">';
    foreach (nav_items($area, $u) as [$href, $label]) {
        echo '<a href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '</nav><div class="acct">';
    echo '<button class="themebtn" type="button" data-theme-toggle aria-label="Switch light or dark theme">Theme</button>';
    if ($u) {
        $name = (string)($u['display_name'] ?: $u['full_name']);
        $acct = match ($u['role']) {
            'curator' => url('curator', '/account'),
            'admin' => url('admin', '/account'),
            default => url('app', '/account'),
        };
        echo '<a class="who" href="' . e($acct) . '">' . e(mb_strimwidth($name, 0, 22, '…')) . '</a>';
        echo '<form method="post" action="' . e(url('auth', '/logout')) . '" class="inline">' . csrf_field() . '<button class="link" type="submit">Sign out</button></form>';
    } else {
        echo '<a class="link" href="' . e(url('auth', '/login')) . '">Sign in</a>';
        echo '<a class="btn small" href="' . e(url('auth', '/register')) . '">Join</a>';
    }
    echo '</div></div></header>' . "\n";
    $flashes = Flash::take();
    if ($flashes) {
        echo '<div class="wrap flashes" role="status">';
        foreach ($flashes as $f) {
            echo '<p class="flash ' . e((string)$f[0]) . '">' . e((string)$f[1]) . '</p>';
        }
        echo '</div>';
    }
    echo '<div id="main" tabindex="-1">' . "\n";
}

function Security_noStoreIfPrivate(string $area): void
{
    if ($area !== 'public') {
        \PTL\Security::noStore();
    }
}

function page_footer(): void
{
    echo "\n</div>\n<footer class=\"foot\"><div class=\"wrap foot-grid\">";
    echo '<div><strong>PlugTheList</strong><p class="muted">Curators set the price. Creatives book with confidence. Escrow holds the money until the work is done.</p><p class="muted small">Operated by Paramount Digital Services, Uyo, Akwa Ibom, Nigeria.</p></div>';
    echo '<div><strong>Platform</strong><ul>'
        . '<li><a href="' . e(url('www', '/browse')) . '">Browse curators</a></li>'
        . '<li><a href="' . e(url('www', '/how-it-works')) . '">How escrow works</a></li>'
        . '<li><a href="' . e(url('www', '/pricing')) . '">Fees</a></li>'
        . '<li><a href="' . e(url('auth', '/register/curator')) . '">List your playlist</a></li></ul></div>';
    echo '<div><strong>Legal</strong><ul>'
        . '<li><a href="' . e(url('www', '/terms')) . '">Terms of service</a></li>'
        . '<li><a href="' . e(url('www', '/privacy')) . '">Privacy notice</a></li>'
        . '<li><a href="' . e(url('www', '/acceptable-use')) . '">Acceptable use</a></li>'
        . '<li><a href="' . e(url('www', '/escrow-refunds')) . '">Escrow and refunds</a></li>'
        . '<li><a href="' . e(url('www', '/cookies')) . '">Cookies</a></li></ul></div>';
    echo '</div><div class="wrap legalline small muted">Payments by Paystack. PlugTheList is a marketplace: curators are independent and are responsible for what they deliver. No platform can guarantee streams, followers or chart positions.</div></footer>' . "\n";
    echo "</body>\n</html>";
}

                                                                                                    
function field(string $name, string $label, string $type = 'text', string $value = '', ?string $err = null, array $attr = [], string $hint = ''): string
{
    $id = 'f_' . $name;
    $a = '';
    foreach ($attr as $k => $v) {
        if ($v === true) {
            $a .= ' ' . e($k);
        } elseif ($v !== false) {
            $a .= ' ' . e($k) . '="' . e((string)$v) . '"';
        }
    }
    $keepValue = $type !== 'password';
    $h = '<div class="field' . ($err ? ' has-err' : '') . '"><label for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        $h .= '<textarea id="' . e($id) . '" name="' . e($name) . '"' . $a . '>' . e($value) . '</textarea>';
    } else {
        $h .= '<input id="' . e($id) . '" name="' . e($name) . '" type="' . e($type) . '"' . ($keepValue ? ' value="' . e($value) . '"' : '') . $a . '>';
    }
    if ($hint !== '') {
        $h .= '<small class="hint">' . e($hint) . '</small>';
    }
    if ($err) {
        $h .= '<small class="err" role="alert">' . e($err) . '</small>';
    }
    return $h . '</div>';
}

                                     
function error_box(array $errs): string
{
    if (!$errs) {
        return '';
    }
    $h = '<div class="errbox" role="alert"><strong>Fix this first:</strong><ul>';
    foreach ($errs as $x) {
        $h .= '<li>' . e($x) . '</li>';
    }
    return $h . '</ul></div>';
}

function select_field(string $name, string $label, array $options, string $value = '', ?string $err = null, string $placeholder = ''): string
{
    $h = '<div class="field' . ($err ? ' has-err' : '') . '"><label for="f_' . e($name) . '">' . e($label) . '</label><select id="f_' . e($name) . '" name="' . e($name) . '">';
    if ($placeholder !== '') {
        $h .= '<option value="">' . e($placeholder) . '</option>';
    }
    foreach ($options as $k => $v) {
        $h .= '<option value="' . e((string)$k) . '"' . ((string)$k === $value ? ' selected' : '') . '>' . e((string)$v) . '</option>';
    }
    $h .= '</select>';
    if ($err) {
        $h .= '<small class="err" role="alert">' . e($err) . '</small>';
    }
    return $h . '</div>';
}

function paginate(int $total, int $page, int $per, string $base): string
{
    $pages = (int)ceil($total / max(1, $per));
    if ($pages <= 1) {
        return '';
    }
    $h = '<nav class="pager" aria-label="Pages">';
    for ($i = 1; $i <= min($pages, 30); $i++) {
        $u = $base . (str_contains($base, '?') ? '&' : '?') . 'page=' . $i;
        $h .= $i === $page ? '<span class="cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($u) . '">' . $i . '</a>';
    }
    return $h . '</nav>';
}

                                                                                       
function listing_row(array $l): string
{
    $href = url('www', '/listing?id=' . (int)$l['id']);
    $g = array_filter(explode(',', (string)$l['genres']));
    $rating = (int)$l['rating_count'] > 0 ? '★ ' . number_format($l['rating_sum'] / $l['rating_count'], 1) . ' (' . (int)$l['rating_count'] . ')' : 'New';
    $h = '<li class="row"><a class="row-link" href="' . e($href) . '">';
    $h .= '<span class="row-main"><strong class="row-title">' . e((string)$l['title']) . '</strong>';
    $h .= '<span class="row-meta">' . e(PTL\Catalog::platformLabel((string)$l['platform'])) . ' · ' . e(short_num((int)$l['followers'])) . ' followers · ' . e(implode(', ', $g)) . '</span>';
    $h .= '<span class="row-meta">by ' . e((string)($l['curator_name'] ?? '')) . ' · ' . e($rating) . ' · ' . pluralise((int)$l['orders_done'], 'job done', 'jobs done') . (!empty($l['verified']) ? ' · <span class="vtag">Verified owner</span>' : '') . '</span></span>';
    $h .= '<span class="leader" aria-hidden="true"></span>';
    $h .= '<span class="row-price"><small>from</small>' . e(naira((int)$l['min_price_kobo'])) . '</span></a></li>';
    return $h;
}
