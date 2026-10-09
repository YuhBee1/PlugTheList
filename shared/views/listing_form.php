<?php
declare(strict_types=1);

use PTL\Auth;
use PTL\Catalog;
use PTL\DB;
use PTL\Listings;
use PTL\RateLimiter;
use PTL\Security;
use PTL\Upload;
use PTL\Validator;

                                         
function listing_form_page(int $id): void
{
    $u = Auth::require('curator');
    $l = $id ? DB::one('SELECT * FROM listings WHERE id = ? AND curator_id = ?', [$id, $u['id']]) : null;
    if ($id && $l === null) {
        abort(404, 'Listing not found.');
    }
    $errs = [];
    $in = $l ? ['platform' => $l['platform'], 'title' => $l['title'], 'url' => $l['url'], 'followers' => (string)$l['followers'], 'description' => $l['description'], 'genres' => array_filter(explode(',', (string)$l['genres']))] : ['genres' => []];
    if ($l) {
        foreach (Listings::offers($id) as $o) {
            $in["offer_{$o['service']}_on"] = '1';
            $in["offer_{$o['service']}_price"] = (string)intdiv((int)$o['price_kobo'], 100);
            $in["offer_{$o['service']}_days"] = (string)$o['turnaround_days'];
            $in["offer_{$o['service']}_details"] = (string)$o['details'];
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Security::requirePost();
        RateLimiter::enforce('listing_act', (string)$u['id'], 60, 3600);
        $act = post('action', 'save');
        if ($act === 'proof' && $l) {
            $cap = Validator::text(post('caption'), 5, 160);
            $link = post('link') === '' ? '' : Validator::url(post('link'));
            [$fid, $uerr] = Upload::image('shot', (int)$u['id'], 'proof', $id);
            if ($cap === null || $link === null || $uerr || ($link === '' && $fid === null)) {
                flash('error', $uerr ?: 'Add a short caption (5 to 160 characters) and a valid https link or a screenshot.');
            } elseif ((int)DB::val('SELECT COUNT(*) FROM listing_proofs WHERE listing_id = ?', [$id]) >= 12) {
                flash('error', 'You can add up to 12 examples.');
            } else {
                DB::insert('listing_proofs', ['listing_id' => $id, 'caption' => $cap, 'link' => $link ?: null, 'file_id' => $fid]);
                flash('ok', 'Example added.');
            }
            redirect(url('curator', '/listing?id=' . $id));
        }
        if ($act === 'proof_delete' && $l) {
            DB::run('DELETE FROM listing_proofs WHERE id = ? AND listing_id = ?', [(int)post('pid'), $id]);
            redirect(url('curator', '/listing?id=' . $id));
        }
        if ($act === 'pause' && $l) {
            DB::update('listings', ['status' => $l['status'] === 'paused' ? 'pending' : 'paused'], 'id = ? AND status IN (?, ?)', [$id, 'approved', 'paused']);
            flash('ok', $l['status'] === 'paused' ? 'Sent for re-approval.' : 'Listing paused. It no longer shows in search.');
            redirect(url('curator', '/listing?id=' . $id));
        }
        $in = array_map(static fn($x) => is_array($x) ? $x : (is_string($x) ? $x : ''), $_POST);
        if ($l) {
            $errs = Listings::update((int)$u['id'], $id, $in);
            if (!$errs) {
                flash('ok', 'Saved.' . (($in['url'] ?? '') !== $l['url'] || ($in['platform'] ?? '') !== $l['platform'] ? ' Because the link changed, it needs approval again.' : ''));
                redirect(url('curator', '/listing?id=' . $id));
            }
        } else {
            [$nid, $errs] = Listings::create((int)$u['id'], $in);
            if ($nid) {
                flash('ok', 'Listing created. Follow the steps below to verify you own it.');
                redirect(url('curator', '/listing?id=' . $nid));
            }
        }
    }
    $g = static fn(string $k): string => is_string($in[$k] ?? '') ? (string)($in[$k] ?? '') : '';
    $plat = [];
    foreach (Catalog::platforms() as $s => $p) {
        $plat[$s] = $p[0];
    }
    $dsp = array_keys(array_filter(Catalog::platforms(), static fn($p) => $p[2]));
    page_header(['title' => $l ? 'Edit listing' : 'New listing', 'area' => 'curator']);
    echo '<main class="wrap"><p class="crumb"><a href="' . e(url('curator', '/listings')) . '">My listings</a> / ' . ($l ? e((string)$l['title']) : 'New') . '</p><h1>' . ($l ? e((string)$l['title']) . ' ' . status_badge((string)$l['status']) : 'List a playlist, channel or community') . '</h1>';

    if ($l) {
        if ($l['status'] === 'rejected') {
            echo '<p class="errbox"><strong>Not approved:</strong> ' . e((string)$l['reject_reason']) . ' Fix it below and save to ask for another review.</p>';
        }
        if (!$l['verified']) {
            echo '<section class="panel"><h2>Prove you own it</h2><p>Put this code anywhere in your ' . e(Catalog::platformKind((string)$l['platform']) === 'playlist' ? 'playlist description' : 'channel or group description or bio') . ', then wait for review (usually within a day). You can remove it after approval.</p><p class="key"><code>' . e((string)$l['verify_code']) . '</code></p></section>';
        }
    }
    echo error_box($errs && !isset($errs['_']) && array_is_list($errs) ? $errs : []);
    echo '<form method="post" class="form wide-form" enctype="multipart/form-data" novalidate>' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<div class="field"><label for="f_platform">Platform</label><select id="f_platform" name="platform" data-dsp="' . e(implode(',', $dsp)) . '">';
    echo '<option value="">Choose one</option>';
    foreach ($plat as $k => $v) {
        echo '<option value="' . e($k) . '"' . ($g('platform') === $k ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    echo '</select></div>';
    echo field('title', 'Listing name', 'text', $g('title'), null, ['required' => true, 'maxlength' => 120]);
    echo field('url', 'Link (https)', 'url', $g('url'), null, ['required' => true, 'maxlength' => 300], 'The public link to your playlist, channel, page or group.');
    echo field('followers', 'Followers, subscribers or members', 'text', $g('followers'), null, ['required' => true, 'inputmode' => 'numeric'], 'Be accurate. Admins check this, and artists will too.');
    echo '<fieldset class="field"><legend>Genres (pick up to 4)</legend><div class="chips">';
    foreach (Catalog::GENRES as $gname) {
        echo '<label class="chip"><input type="checkbox" name="genres[]" value="' . e($gname) . '"' . (in_array($gname, (array)($in['genres'] ?? []), true) ? ' checked' : '') . '> ' . e($gname) . '</label>';
    }
    echo '</div></fieldset>';
    echo field('description', 'Your audience and what you accept', 'textarea', $g('description'), null, ['rows' => 5, 'required' => true, 'maxlength' => 1500], 'Who listens, what you will and will not play or post.');
    echo '<h2>Services and prices</h2><p class="muted">You set the price in naira. Minimum ' . e(naira(PTL\Settings::int('min_listing_price_kobo'))) . '.</p>';
    echo '<p class="note" id="dspnote" hidden>Spotify and Apple Music playlists can only sell a review and consideration. Guaranteed placements are not allowed.</p>';
    foreach (Catalog::services() as $svc => $label) {
        echo '<div class="offer-edit" data-svc="' . e($svc) . '"><label class="check"><input type="checkbox" name="offer_' . e($svc) . '_on" value="1"' . ($g("offer_{$svc}_on") ? ' checked' : '') . '> <strong>' . e($label) . '</strong></label>';
        echo '<div class="three">' . field("offer_{$svc}_price", 'Price (₦)', 'text', $g("offer_{$svc}_price"), null, ['inputmode' => 'numeric', 'placeholder' => '15,000'])
            . field("offer_{$svc}_days", 'Delivery (days)', 'text', $g("offer_{$svc}_days") ?: '3', null, ['inputmode' => 'numeric'])
            . field("offer_{$svc}_details", 'Details (optional)', 'text', $g("offer_{$svc}_details"), null, ['maxlength' => 300]) . '</div></div>';
    }
    echo '<button class="btn" type="submit">' . ($l ? 'Save changes' : 'Create listing') . '</button></form>';

    if ($l) {
        echo '<section class="panel"><h2>Proof of past work</h2><p class="muted">Add links or screenshots that show results for earlier artists. This is what makes people book you.</p><ul class="plain">';
        foreach (Listings::proofs($id) as $p) {
            echo '<li>' . e((string)$p['caption']) . ($p['link'] ? ' · ' . ext_link((string)$p['link'], 'View') : '') . ($p['file_id'] ? ' · <a href="' . e(url('www', '/file?id=' . (int)$p['file_id'])) . '" target="_blank" rel="noopener">Screenshot</a>' : '')
                . ' <form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="proof_delete"><input type="hidden" name="pid" value="' . (int)$p['id'] . '"><button class="link" type="submit">Remove</button></form></li>';
        }
        echo '</ul><form method="post" class="form" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="proof">'
            . field('caption', 'What does this show?', 'text', '', null, ['maxlength' => 160, 'required' => true])
            . field('link', 'Link (optional)', 'url', '', null, ['maxlength' => 300])
            . '<div class="field"><label for="f_shot">Screenshot (optional)</label><input id="f_shot" name="shot" type="file" accept="image/jpeg,image/png,image/webp"></div><button class="btn ghost" type="submit">Add example</button></form></section>';
        if (in_array($l['status'], ['approved', 'paused'], true)) {
            echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="pause"><button class="btn ghost" type="submit">' . ($l['status'] === 'paused' ? 'Resume listing' : 'Pause listing') . '</button></form>';
        }
    }
    echo '</main>';
    page_footer();
}
