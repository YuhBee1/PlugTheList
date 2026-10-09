<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                           
                                                                                                        
   
final class Escrow
{
    public static function ref(): string
    {
        return 'PTL-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    }

    private static function lock(int $orderId): array
    {
        $o = DB::one('SELECT * FROM orders WHERE id = ?' . DB::forUpdate(), [$orderId]);
        if ($o === null) {
            throw new \DomainException('Order not found.');
        }
        return $o;
    }

    private static function move(array $o, string $to, array $extra = []): void
    {
        if (!OrderState::can((string)$o['status'], $to)) {
            throw new \DomainException('That is not possible while the order is "' . OrderState::label((string)$o['status']) . '".');
        }
        $n = DB::update('orders', ['status' => $to] + $extra, 'id = ? AND status = ?', [$o['id'], $o['status']]);
        if ($n !== 1) {
            throw new \DomainException('The order changed. Refresh and try again.');
        }
    }

    public static function event(int $orderId, ?int $userId, string $event, string $note = ''): void
    {
        DB::insert('order_events', ['order_id' => $orderId, 'user_id' => $userId, 'event' => $event, 'note' => mb_substr($note, 0, 500)]);
    }

    private static function orderUrl(array $o, string $area): string
    {
        return url($area, '/order?id=' . (int)$o['id']);
    }

                                   
    public static function create(int $creativeId, int $offerId, string $trackTitle, string $trackUrl, string $brief): int
    {
        $r = DB::one(
            'SELECT o.*, l.curator_id, l.title AS l_title, l.platform, l.status AS l_status, l.max_active_orders, l.id AS lid
               FROM listing_offers o JOIN listings l ON l.id = o.listing_id WHERE o.id = ?',
            [$offerId]
        );
        if ($r === null || !(int)$r['active'] || $r['l_status'] !== 'approved') {
            throw new \DomainException('This offer is not available.');
        }
        if ((int)$r['curator_id'] === $creativeId) {
            throw new \DomainException('You cannot book your own listing.');
        }
        $curator = DB::one('SELECT status FROM users WHERE id = ?', [$r['curator_id']]);
        if ($curator === null || $curator['status'] !== 'active') {
            throw new \DomainException('This curator is not taking bookings right now.');
        }
        $price = (int)$r['price_kobo'];
        if ($price < Settings::int('min_listing_price_kobo') || $price > Settings::int('max_listing_price_kobo')) {
            throw new \DomainException('This offer price is outside the allowed range.');
        }
        $active = (int)DB::val("SELECT COUNT(*) FROM orders WHERE listing_id = ? AND status IN ('paid','in_progress','delivered','disputed')", [$r['lid']]);
        if ($active >= (int)$r['max_active_orders']) {
            throw new \DomainException('This curator has a full queue. Try again in a few days.');
        }
        $f = Money::fees($price, Settings::int('buyer_fee_bps'), Settings::int('curator_fee_bps'));
        return (int)DB::tx(static function () use ($creativeId, $r, $f, $trackTitle, $trackUrl, $brief, $price): int {
            $id = DB::insert('orders', [
                'ref' => self::ref(),
                'creative_id' => $creativeId,
                'curator_id' => (int)$r['curator_id'],
                'listing_id' => (int)$r['lid'],
                'offer_id' => (int)$r['id'],
                'platform' => $r['platform'],
                'service' => $r['service'],
                'listing_title' => $r['l_title'],
                'track_title' => $trackTitle,
                'track_url' => $trackUrl,
                'brief' => $brief,
                'price_kobo' => $price,
                'buyer_fee_kobo' => $f['buyer_fee'],
                'curator_fee_kobo' => $f['curator_fee'],
                'total_kobo' => $f['total'],
                'turnaround_days' => (int)$r['turnaround_days'],
                'status' => OrderState::AWAITING_PAYMENT,
                'expires_at' => date('Y-m-d H:i:s', time() + Settings::int('unpaid_expiry_hours') * 3600),
            ]);
            self::event($id, $creativeId, 'created');
            return $id;
        });
    }

                                                                               
    public static function startPayment(int $orderId, int $creativeId): string
    {
        $o = DB::one('SELECT * FROM orders WHERE id = ? AND creative_id = ?', [$orderId, $creativeId]);
        if ($o === null) {
            throw new \DomainException('Order not found.');
        }
        if ($o['status'] !== OrderState::AWAITING_PAYMENT || strtotime((string)$o['expires_at']) < time()) {
            throw new \DomainException('This order can no longer be paid. Start a new booking.');
        }
        $email = (string)DB::val('SELECT email FROM users WHERE id = ?', [$creativeId]);
        $ref = 'ptl_' . bin2hex(random_bytes(10));
        DB::insert('payments', ['order_id' => $orderId, 'reference' => $ref, 'amount_kobo' => (int)$o['total_kobo'], 'status' => 'pending']);
        $r = Paystack::initialize($email, (int)$o['total_kobo'], $ref, url('app', '/callback'), ['order' => $o['ref']]);
        return $r['url'];
    }

       
                                                                                                          
                                                                          
       
    public static function settlePayment(string $reference, int $amount, string $currency, string $channel = ''): string
    {
        $result = DB::tx(static function () use ($reference, $amount, $currency, $channel): array {
            $p = DB::one('SELECT * FROM payments WHERE reference = ?' . DB::forUpdate(), [$reference]);
            if ($p === null) {
                return ['unknown', null];
            }
            if ($p['status'] === 'success') {
                return ['already', null];
            }
            if ($currency !== 'NGN' || $amount !== (int)$p['amount_kobo']) {
                DB::update('payments', ['status' => 'mismatch'], 'id = ?', [$p['id']]);
                Audit::log(null, 'payment_mismatch', 'payment', (int)$p['id'], ['paid' => $amount, 'expected' => (int)$p['amount_kobo'], 'cur' => $currency]);
                Notifier::admins('Payment mismatch', 'Reference ' . $reference . ' paid ' . $amount . ' ' . $currency . ' but expected ' . $p['amount_kobo'] . '. Investigate in Paystack.', '');
                return ['mismatch', null];
            }
            $o = self::lock((int)$p['order_id']);
            DB::update('payments', ['status' => 'success', 'channel' => $channel, 'paid_at' => now()], 'id = ?', [$p['id']]);
            $total = (int)$o['total_kobo'];
            Ledger::post('payment_in', 'pay:' . $reference, ['ext:paystack_in' => -$total, 'escrow' => $total], (int)$o['id'], 'Paystack ' . $reference);
            if ($o['status'] === OrderState::AWAITING_PAYMENT) {
                self::move($o, OrderState::PAID, ['paid_at' => now(), 'accept_by' => date('Y-m-d H:i:s', time() + Settings::int('accept_window_hours') * 3600)]);
                self::event((int)$o['id'], (int)$o['creative_id'], 'paid');
                Notifier::to((int)$o['curator_id'], 'New booking: ' . $o['track_title'], 'You have a paid booking for "' . $o['listing_title'] . '". Accept it within ' . Settings::int('accept_window_hours') . ' hours or the creative is refunded automatically.', self::orderUrl($o, 'curator'));
                Notifier::to((int)$o['creative_id'], 'Payment received', 'Your ' . naira($total) . ' is held in escrow for ' . $o['ref'] . '. The curator has been told.', self::orderUrl($o, 'app'));
                return ['paid', null];
            }
                                                                                       
            self::refundInside($o, $total, 'late_payment', true);
            return ['paid_late', $o];
        });
        return $result[0] === 'paid_late' ? 'failed' : $result[0];
    }

                                                                                         
    private static function refundInside(array $o, int $amount, string $why, bool $noStateChange = false): void
    {
        Ledger::post('refund', 'refund:' . $o['id'], ['escrow' => -$amount, 'ext:paystack_refund' => $amount], (int)$o['id'], $why);
        if (!$noStateChange) {
            self::move($o, OrderState::REFUNDED, ['completed_at' => now()]);
        }
        self::event((int)$o['id'], null, 'refunded', $why);
        $ref = (string)DB::val('SELECT reference FROM payments WHERE order_id = ? AND status = ? ORDER BY id DESC', [$o['id'], 'success']);
        $oid = (int)$o['id'];
        DB::afterCommit(static function () use ($ref, $amount, $oid): void {
            $ok = false;
            try {
                $ok = $ref !== '' && Paystack::refund($ref, $amount);
            } catch (\Throwable $e) {
                error_log('[refund] ' . $e->getMessage());
            }
            if (!$ok) {
                Audit::log(null, 'refund_needs_manual', 'order', $oid, ['amount' => $amount]);
                Notifier::admins('Refund needs manual action', 'Order #' . $oid . ': the Paystack refund of ' . naira($amount) . ' did not go through automatically. Refund it from the Paystack dashboard.', '');
            }
        });
    }

    public static function accept(int $orderId, int $curatorId): void
    {
        DB::tx(static function () use ($orderId, $curatorId): void {
            $o = self::lock($orderId);
            if ((int)$o['curator_id'] !== $curatorId) {
                throw new \DomainException('Not your order.');
            }
            self::move($o, OrderState::IN_PROGRESS, ['accepted_at' => now(), 'due_at' => date('Y-m-d H:i:s', time() + (int)$o['turnaround_days'] * 86400)]);
            self::event($orderId, $curatorId, 'accepted');
            Notifier::to((int)$o['creative_id'], 'Booking accepted', $o['listing_title'] . ' accepted ' . $o['ref'] . '. Delivery is due ' . date('j M', time() + (int)$o['turnaround_days'] * 86400) . '.', self::orderUrl($o, 'app'));
        });
    }

    public static function decline(int $orderId, int $curatorId, string $reason): void
    {
        DB::tx(static function () use ($orderId, $curatorId, $reason): void {
            $o = self::lock($orderId);
            if ((int)$o['curator_id'] !== $curatorId) {
                throw new \DomainException('Not your order.');
            }
            self::refundInside($o, (int)$o['total_kobo'], 'Declined by curator: ' . $reason);
            Notifier::to((int)$o['creative_id'], 'Booking declined, refund started', 'The curator declined ' . $o['ref'] . '. Your ' . naira((int)$o['total_kobo']) . ' is being refunded to your original payment method.', self::orderUrl($o, 'app'));
        });
    }

    public static function deliver(int $orderId, int $curatorId, string $url, string $note, ?int $fileId): void
    {
        DB::tx(static function () use ($orderId, $curatorId, $url, $note, $fileId): void {
            $o = self::lock($orderId);
            if ((int)$o['curator_id'] !== $curatorId) {
                throw new \DomainException('Not your order.');
            }
            $hrs = Settings::int('auto_release_hours');
            self::move($o, OrderState::DELIVERED, [
                'delivered_at' => now(), 'delivery_url' => $url !== '' ? $url : null, 'delivery_note' => $note,
                'delivery_file_id' => $fileId, 'review_by' => date('Y-m-d H:i:s', time() + $hrs * 3600),
            ]);
            self::event($orderId, $curatorId, 'delivered');
            Notifier::to((int)$o['creative_id'], 'Delivered: check and approve', 'Proof of delivery for ' . $o['ref'] . ' is ready. Approve it, or open a dispute within ' . $hrs . ' hours. After that the money is released automatically.', self::orderUrl($o, 'app'));
        });
    }

    public static function approve(int $orderId, int $creativeId): void
    {
        DB::tx(static function () use ($orderId, $creativeId): void {
            $o = self::lock($orderId);
            if ((int)$o['creative_id'] !== $creativeId) {
                throw new \DomainException('Not your order.');
            }
            self::releaseInside($o, $creativeId, 'approved');
        });
    }

    private static function releaseInside(array $o, ?int $actor, string $how): void
    {
        $net = (int)$o['price_kobo'] - (int)$o['curator_fee_kobo'];
        $plat = (int)$o['buyer_fee_kobo'] + (int)$o['curator_fee_kobo'];
        Ledger::post('release', 'release:' . $o['id'], ['escrow' => -(int)$o['total_kobo'], 'user:' . $o['curator_id'] => $net, 'platform' => $plat], (int)$o['id'], $how);
        self::move($o, OrderState::COMPLETED, ['completed_at' => now()]);
        DB::run('UPDATE listings SET orders_done = orders_done + 1 WHERE id = ?', [$o['listing_id']]);
        self::event((int)$o['id'], $actor, 'released', $how);
        Notifier::to((int)$o['curator_id'], 'You got paid: ' . naira($net), $o['ref'] . ' is complete. ' . naira($net) . ' is now in your wallet.', self::orderUrl($o, 'curator'));
        Notifier::to((int)$o['creative_id'], 'Order complete', $o['ref'] . ' is complete. Leave a rating to help other artists.', self::orderUrl($o, 'app'));
    }

    public static function dispute(int $orderId, int $userId, string $reason): void
    {
        DB::tx(static function () use ($orderId, $userId, $reason): void {
            $o = self::lock($orderId);
            if (!in_array($userId, [(int)$o['creative_id'], (int)$o['curator_id']], true)) {
                throw new \DomainException('Not your order.');
            }
            self::move($o, OrderState::DISPUTED, ['dispute_by' => $userId, 'dispute_reason' => $reason, 'disputed_at' => now()]);
            self::event($orderId, $userId, 'disputed', $reason);
            $other = $userId === (int)$o['creative_id'] ? (int)$o['curator_id'] : (int)$o['creative_id'];
            Notifier::to($other, 'A dispute was opened on ' . $o['ref'], 'The money stays in escrow while PlugTheList reviews. Add your side in the order messages.', self::orderUrl($o, $other === (int)$o['curator_id'] ? 'curator' : 'app'));
            Notifier::admins('Dispute on ' . $o['ref'], mb_substr($reason, 0, 200), url('admin', '/order?id=' . $orderId));
        });
    }

                                                                                                       
    public static function resolve(int $adminId, int $orderId, int $curatorShare, string $note): void
    {
        DB::tx(static function () use ($adminId, $orderId, $curatorShare, $note): void {
            $o = self::lock($orderId);
            if ($o['status'] !== OrderState::DISPUTED) {
                throw new \DomainException('Only disputed orders can be resolved.');
            }
            $s = Money::split(['price' => (int)$o['price_kobo'], 'buyer_fee' => (int)$o['buyer_fee_kobo'], 'curator_fee' => (int)$o['curator_fee_kobo'], 'total' => (int)$o['total_kobo']], $curatorShare);
            $lines = ['escrow' => -(int)$o['total_kobo']];
            if ($s['curator'] > 0) {
                $lines['user:' . $o['curator_id']] = $s['curator'];
            }
            if ($s['platform'] > 0) {
                $lines['platform'] = $s['platform'];
            }
            if ($s['refund'] > 0) {
                $lines['ext:paystack_refund'] = $s['refund'];
            }
            Ledger::post('resolve', 'resolve:' . $orderId, $lines, $orderId, $note);
            $final = ($s['curator'] === 0 && $s['platform'] === 0) ? OrderState::REFUNDED : OrderState::COMPLETED;
            self::move($o, $final, ['completed_at' => now(), 'resolution_note' => $note]);
            if ($final === OrderState::COMPLETED) {
                DB::run('UPDATE listings SET orders_done = orders_done + 1 WHERE id = ?', [$o['listing_id']]);
            }
            self::event($orderId, $adminId, 'resolved', 'curator ' . $s['curator'] . ' / refund ' . $s['refund'] . ' / fees ' . $s['platform'] . ': ' . $note);
            Audit::log($adminId, 'dispute_resolved', 'order', $orderId, $s);
            if ($s['refund'] > 0) {
                $ref = (string)DB::val('SELECT reference FROM payments WHERE order_id = ? AND status = ? ORDER BY id DESC', [$orderId, 'success']);
                $amount = $s['refund'];
                DB::afterCommit(static function () use ($ref, $amount, $orderId): void {
                    $ok = false;
                    try {
                        $ok = $ref !== '' && Paystack::refund($ref, $amount);
                    } catch (\Throwable $e) {
                        error_log('[refund] ' . $e->getMessage());
                    }
                    if (!$ok) {
                        Notifier::admins('Refund needs manual action', 'Order #' . $orderId . ': refund ' . naira($amount) . ' manually in Paystack.', '');
                    }
                });
            }
            $msg = 'Decision on ' . $o['ref'] . ': curator receives ' . naira($s['curator']) . ', refund to the creative ' . naira($s['refund']) . '. ' . $note;
            Notifier::to((int)$o['creative_id'], 'Dispute decided', $msg, self::orderUrl($o, 'app'));
            Notifier::to((int)$o['curator_id'], 'Dispute decided', $msg, self::orderUrl($o, 'curator'));
        });
    }

    public static function message(int $orderId, int $userId, string $body): void
    {
        $o = DB::one('SELECT creative_id, curator_id, status, ref FROM orders WHERE id = ?', [$orderId]);
        if ($o === null || !in_array($userId, [(int)$o['creative_id'], (int)$o['curator_id']], true)) {
            throw new \DomainException('Not your order.');
        }
        if (in_array($o['status'], [OrderState::CANCELLED], true)) {
            throw new \DomainException('This order is closed.');
        }
        DB::insert('order_messages', ['order_id' => $orderId, 'user_id' => $userId, 'body' => $body]);
        $other = $userId === (int)$o['creative_id'] ? (int)$o['curator_id'] : (int)$o['creative_id'];
        Notifier::to($other, 'New message on ' . $o['ref'], mb_substr($body, 0, 140), url($other === (int)$o['curator_id'] ? 'curator' : 'app', '/order?id=' . $orderId));
    }

    public static function review(int $orderId, int $creativeId, int $rating, string $comment): void
    {
        $o = DB::one('SELECT * FROM orders WHERE id = ? AND creative_id = ?', [$orderId, $creativeId]);
        if ($o === null || $o['status'] !== OrderState::COMPLETED || $rating < 1 || $rating > 5) {
            throw new \DomainException('You can rate completed orders only.');
        }
        DB::tx(static function () use ($o, $creativeId, $rating, $comment): void {
            if (!DB::insertIgnore('reviews', ['order_id' => $o['id'], 'listing_id' => $o['listing_id'], 'creative_id' => $creativeId, 'rating' => $rating, 'comment' => $comment])) {
                throw new \DomainException('You already rated this order.');
            }
            DB::run('UPDATE listings SET rating_sum = rating_sum + ?, rating_count = rating_count + 1 WHERE id = ?', [$rating, $o['listing_id']]);
        });
    }

                                                                      
    public static function sweep(): array
    {
        $c = ['expired' => 0, 'accept_timeout' => 0, 'late' => 0, 'auto_release' => 0];
        $now = now();
        foreach (DB::all("SELECT id FROM orders WHERE status = 'awaiting_payment' AND expires_at < ?", [$now]) as $r) {
            try {
                DB::tx(static function () use ($r): void {
                    $o = self::lock((int)$r['id']);
                    self::move($o, OrderState::CANCELLED);
                    self::event((int)$o['id'], null, 'expired');
                });
                $c['expired']++;
            } catch (\DomainException $e) {
            }
        }
        foreach (DB::all("SELECT id FROM orders WHERE status = 'paid' AND accept_by < ?", [$now]) as $r) {
            try {
                DB::tx(static function () use ($r): void {
                    $o = self::lock((int)$r['id']);
                    self::refundInside($o, (int)$o['total_kobo'], 'Curator did not accept in time');
                    Notifier::to((int)$o['creative_id'], 'Refund started', 'The curator did not accept ' . $o['ref'] . ' in time, so your money is being refunded.', self::orderUrl($o, 'app'));
                    Notifier::to((int)$o['curator_id'], 'Booking expired', $o['ref'] . ' expired because it was not accepted in time.', self::orderUrl($o, 'curator'));
                });
                $c['accept_timeout']++;
            } catch (\DomainException $e) {
            }
        }
        $grace = Settings::int('non_delivery_grace_hours') * 3600;
        foreach (DB::all("SELECT id FROM orders WHERE status = 'in_progress' AND due_at < ?", [date('Y-m-d H:i:s', time() - $grace)]) as $r) {
            try {
                DB::tx(static function () use ($r): void {
                    $o = self::lock((int)$r['id']);
                    self::refundInside($o, (int)$o['total_kobo'], 'Not delivered by the due date');
                    Notifier::to((int)$o['creative_id'], 'Refund started', $o['ref'] . ' was not delivered on time. Your money is being refunded.', self::orderUrl($o, 'app'));
                    Notifier::to((int)$o['curator_id'], 'Order refunded', $o['ref'] . ' was not delivered on time and was refunded to the creative.', self::orderUrl($o, 'curator'));
                });
                $c['late']++;
            } catch (\DomainException $e) {
            }
        }
        foreach (DB::all("SELECT id FROM orders WHERE status = 'delivered' AND review_by < ?", [$now]) as $r) {
            try {
                DB::tx(static function () use ($r): void {
                    self::releaseInside(self::lock((int)$r['id']), null, 'auto-released after review window');
                });
                $c['auto_release']++;
            } catch (\DomainException $e) {
            }
        }
        return $c;
    }
}
