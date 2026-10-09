<?php
declare(strict_types=1);

use PTL\Auth;
use PTL\Catalog;
use PTL\DB;
use PTL\Escrow;
use PTL\OrderState;
use PTL\RateLimiter;
use PTL\Security;
use PTL\Upload;
use PTL\Validator;

                                                               
function order_page(string $role): void
{
    $u = Auth::require($role);
    $id = (int)qs('id', post('id'));
    $col = $role === 'creative' ? 'creative_id' : 'curator_id';
    $o = DB::one("SELECT o.*, c.display_name AS curator_name, a.display_name AS creative_name FROM orders o JOIN users c ON c.id = o.curator_id JOIN users a ON a.id = o.creative_id WHERE o.id = ? AND o.$col = ?", [$id, $u['id']]);
    if ($o === null) {
        abort(404, 'We could not find that order.');
    }
    $self = url($role === 'creative' ? 'app' : 'curator', '/order?id=' . $id);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Security::requirePost();
        RateLimiter::enforce('order_act', (string)$u['id'], 80, 3600);
        $act = post('action');
        try {
            switch ($act) {
                case 'accept':
                    $role === 'curator' || throw new DomainException('Not allowed.');
                    Escrow::accept($id, (int)$u['id']);
                    flash('ok', 'Accepted. Deliver by ' . fmt_d(date('Y-m-d', time() + (int)$o['turnaround_days'] * 86400)) . '.');
                    break;
                case 'decline':
                    $role === 'curator' || throw new DomainException('Not allowed.');
                    Escrow::decline($id, (int)$u['id'], mb_substr(post('reason'), 0, 200));
                    flash('ok', 'Declined. The creative is being refunded.');
                    break;
                case 'deliver':
                    $role === 'curator' || throw new DomainException('Not allowed.');
                    $url = post('delivery_url');
                    $url = $url === '' ? '' : (Validator::url($url) ?? throw new DomainException('Enter a valid https link, or leave it empty and upload a screenshot.'));
                    $note = Validator::text(post('delivery_note'), 0, 1000) ?? throw new DomainException('The note is too long.');
                    [$fid, $uerr] = Upload::image('proof', (int)$u['id'], 'delivery', $id);
                    if ($uerr) {
                        throw new DomainException($uerr);
                    }
                    if ($url === '' && $fid === null) {
                        throw new DomainException('Add a link or a screenshot as proof of delivery.');
                    }
                    Escrow::deliver($id, (int)$u['id'], $url, $note, $fid);
                    flash('ok', 'Delivered. The creative now has ' . PTL\Settings::int('auto_release_hours') . ' hours to approve.');
                    break;
                case 'approve':
                    $role === 'creative' || throw new DomainException('Not allowed.');
                    Escrow::approve($id, (int)$u['id']);
                    flash('ok', 'Approved. The curator has been paid. Please rate the job.');
                    break;
                case 'dispute':
                    $why = Validator::text(post('reason'), 20, 1000) ?? throw new DomainException('Explain the problem in 20 to 1,000 characters.');
                    Escrow::dispute($id, (int)$u['id'], $why);
                    flash('ok', 'Dispute opened. The money stays in escrow while we review.');
                    break;
                case 'message':
                    $m = Validator::text(post('body'), 1, 1000) ?? throw new DomainException('Write a message up to 1,000 characters.');
                    Escrow::message($id, (int)$u['id'], $m);
                    flash('ok', 'Message sent.');
                    break;
                case 'review':
                    $role === 'creative' || throw new DomainException('Not allowed.');
                    Escrow::review($id, (int)$u['id'], (int)post('rating'), mb_substr(post('comment'), 0, 500));
                    flash('ok', 'Thanks for rating.');
                    break;
                default:
                    throw new DomainException('Unknown action.');
            }
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect($self);
    }

    $events = DB::all('SELECT e.*, u.display_name FROM order_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.order_id = ? ORDER BY e.id', [$id]);
    $msgs = DB::all('SELECT m.*, u.display_name FROM order_messages m JOIN users u ON u.id = m.user_id WHERE m.order_id = ? ORDER BY m.id', [$id]);
    $reviewed = DB::val('SELECT id FROM reviews WHERE order_id = ?', [$id]);
    $s = (string)$o['status'];
    $net = (int)$o['price_kobo'] - (int)$o['curator_fee_kobo'];
    $labels = ['created' => 'Booking created', 'paid' => 'Payment received into escrow', 'accepted' => 'Accepted by curator', 'delivered' => 'Delivered', 'released' => 'Money released to curator', 'refunded' => 'Refund started', 'disputed' => 'Dispute opened', 'resolved' => 'Dispute decided', 'expired' => 'Expired unpaid'];
    page_header(['title' => 'Order ' . $o['ref'], 'area' => $role === 'creative' ? 'creative' : 'curator']);
    echo '<main class="wrap detail"><p class="crumb"><a href="' . e(url($role === 'creative' ? 'app' : 'curator', '/orders')) . '">Orders</a> / ' . e((string)$o['ref']) . '</p>';
    echo '<h1>' . e((string)$o['track_title']) . ' ' . status_badge($s) . '</h1>';
    echo '<p class="meta">' . e((string)$o['listing_title']) . ' · ' . e(Catalog::serviceLabel((string)$o['service'])) . ' · ' . ($role === 'creative' ? 'Curator: ' . e((string)$o['curator_name']) : 'Creative: ' . e((string)$o['creative_name'])) . '</p>';
    echo '<div class="cols"><div>';

    echo '<h2>The song</h2><p>' . ext_link((string)$o['track_url']) . '</p>';
    if ($o['brief']) {
        echo '<h2>Brief</h2><p class="prose">' . nl2br(e((string)$o['brief'])) . '</p>';
    }
    if ($o['delivery_url'] || $o['delivery_file_id'] || $o['delivery_note']) {
        echo '<h2>Proof of delivery</h2>';
        if ($o['delivery_url']) {
            echo '<p>' . ext_link((string)$o['delivery_url']) . '</p>';
        }
        if ($o['delivery_file_id']) {
            echo '<p><a href="' . e(url('www', '/file?id=' . (int)$o['delivery_file_id'])) . '" target="_blank" rel="noopener">Open screenshot</a></p>';
        }
        if ($o['delivery_note']) {
            echo '<p class="prose">' . nl2br(e((string)$o['delivery_note'])) . '</p>';
        }
    }
    if ($o['dispute_reason']) {
        echo '<h2>Dispute</h2><p class="prose">' . nl2br(e((string)$o['dispute_reason'])) . '</p>';
        if ($o['resolution_note']) {
            echo '<p><strong>Decision:</strong> ' . e((string)$o['resolution_note']) . '</p>';
        }
    }

              
    echo '<h2>What you can do now</h2>';
    $f = fn(string $act, string $label, string $extra = '', string $cls = 'btn') => '<form method="post" class="inline-form" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . $act . '">' . $extra . '<button class="' . $cls . '" type="submit">' . $label . '</button></form>';
    if ($role === 'creative' && $s === OrderState::AWAITING_PAYMENT) {
        echo '<p>Pay ' . e(naira((int)$o['total_kobo'])) . ' to put the money in escrow. This order expires ' . e(until((string)$o['expires_at'])) . '.</p>';
        echo '<form method="post" action="' . e(url('app', '/pay')) . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button class="btn" type="submit">Pay ' . e(naira((int)$o['total_kobo'])) . ' with Paystack</button></form>';
    } elseif ($role === 'curator' && $s === OrderState::PAID) {
        echo '<p>Accept before ' . e(fmt_dt((string)$o['accept_by'])) . ' or the creative is refunded automatically. You will have ' . (int)$o['turnaround_days'] . ' days to deliver.</p>';
        echo $f('accept', 'Accept this booking');
        echo '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="decline">' . field('reason', 'Reason for declining (optional)', 'text', '', null, ['maxlength' => 200]) . '<button class="btn ghost" type="submit">Decline and refund</button></form>';
    } elseif ($role === 'curator' && $s === OrderState::IN_PROGRESS) {
        echo '<p>Deliver by ' . e(fmt_dt((string)$o['due_at'])) . '. Add a link to the work, a screenshot, or both.</p>';
        echo '<form method="post" class="form" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="deliver">'
            . field('delivery_url', 'Link to the work (https)', 'url', '', null, ['maxlength' => 300])
            . '<div class="field"><label for="f_proof">Screenshot (JPG, PNG or WebP, up to 4 MB)</label><input id="f_proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp"></div>'
            . field('delivery_note', 'Note for the creative', 'textarea', '', null, ['maxlength' => 1000, 'rows' => 3])
            . '<button class="btn" type="submit">Mark as delivered</button></form>';
    } elseif ($role === 'creative' && $s === OrderState::DELIVERED) {
        echo '<p>Check the proof above. Approve to pay the curator, or open a dispute before ' . e(fmt_dt((string)$o['review_by'])) . '. After that the money is released automatically.</p>';
        echo $f('approve', 'Approve and release payment');
    } elseif ($role === 'creative' && $s === OrderState::COMPLETED && !$reviewed) {
        echo '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="review">'
            . select_field('rating', 'Rate this curator', ['5' => '5 - Excellent', '4' => '4 - Good', '3' => '3 - Okay', '2' => '2 - Poor', '1' => '1 - Bad'], '5')
            . field('comment', 'Comment (optional)', 'text', '', null, ['maxlength' => 500]) . '<button class="btn" type="submit">Submit rating</button></form>';
    } else {
        echo '<p class="muted">Nothing needed from you right now.</p>';
    }
    if (in_array($s, [OrderState::IN_PROGRESS, OrderState::DELIVERED], true)) {
        echo '<details class="dispute"><summary>Something wrong? Open a dispute</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="dispute">'
            . field('reason', 'What went wrong?', 'textarea', '', null, ['rows' => 4, 'maxlength' => 1000, 'required' => true], 'The money stays in escrow while we review both sides.')
            . '<button class="btn danger" type="submit">Open dispute</button></form></details>';
    }

                   
    echo '<h2>Messages</h2>';
    if ($msgs) {
        echo '<ul class="msgs">';
        foreach ($msgs as $m) {
            echo '<li class="' . ((int)$m['user_id'] === (int)$u['id'] ? 'mine' : 'theirs') . '"><strong>' . e((string)$m['display_name']) . '</strong> <small>' . e(ago((string)$m['created_at'])) . '</small><p>' . nl2br(e((string)$m['body'])) . '</p></li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="muted">No messages yet. Keep conversation here so we can help if there is a dispute.</p>';
    }
    if ($s !== OrderState::CANCELLED) {
        echo '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="message">' . field('body', 'Message', 'textarea', '', null, ['rows' => 3, 'maxlength' => 1000, 'required' => true]) . '<button class="btn ghost" type="submit">Send</button></form>';
    }
    echo '</div><aside class="book"><h2>Money</h2><table class="tbl compact">';
    if ($role === 'creative') {
        echo '<tr><th>Curator price</th><td>' . e(naira((int)$o['price_kobo'])) . '</td></tr><tr><th>Service fee</th><td>' . e(naira((int)$o['buyer_fee_kobo'])) . '</td></tr><tr><th>Total</th><td><strong>' . e(naira((int)$o['total_kobo'])) . '</strong></td></tr>';
    } else {
        echo '<tr><th>Your price</th><td>' . e(naira((int)$o['price_kobo'])) . '</td></tr><tr><th>Commission</th><td>−' . e(naira((int)$o['curator_fee_kobo'])) . '</td></tr><tr><th>You receive</th><td><strong>' . e(naira($net)) . '</strong></td></tr>';
    }
    echo '</table><p class="muted small">' . (OrderState::holdsEscrow($s) ? 'The money is held in escrow.' : ($s === OrderState::COMPLETED ? 'Released.' : '')) . '</p>';
    echo '<h2>Timeline</h2><ul class="timeline">';
    foreach ($events as $ev) {
        echo '<li><strong>' . e($labels[$ev['event']] ?? $ev['event']) . '</strong><small>' . e(fmt_dt((string)$ev['created_at'])) . '</small></li>';
    }
    echo '</ul><p class="muted small">Reference ' . e((string)$o['ref']) . '</p></aside></div></main>';
    page_footer();
}
