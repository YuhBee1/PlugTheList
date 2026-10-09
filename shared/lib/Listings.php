<?php
declare(strict_types=1);

namespace PTL;

final class Listings
{
       
                                                                                                               
                                     
                                                                                                                               
       
    public static function parseOffers(string $platform, array $in): array
    {
        $offers = [];
        $err = [];
        $min = Settings::int('min_listing_price_kobo');
        $max = Settings::int('max_listing_price_kobo');
        foreach (Catalog::allowedServices($platform) as $svc) {
            if (empty($in["offer_{$svc}_on"])) {
                continue;
            }
            $label = Catalog::serviceLabel($svc);
            $k = Money::parseNaira((string)($in["offer_{$svc}_price"] ?? ''));
            $d = Validator::intRange((string)($in["offer_{$svc}_days"] ?? ''), 1, 30);
            if ($k === null || $k < $min || $k > $max) {
                $err[] = $label . ': price must be between ' . naira($min) . ' and ' . naira($max) . '.';
                continue;
            }
            if ($d === null) {
                $err[] = $label . ': delivery time must be 1 to 30 days.';
                continue;
            }
            $details = Validator::text((string)($in["offer_{$svc}_details"] ?? ''), 0, 300);
            $offers[] = ['service' => $svc, 'price_kobo' => $k, 'turnaround_days' => $d, 'details' => $details === '' ? null : $details];
        }
        if (!$offers && !$err) {
            $err[] = 'Switch on at least one service and set your price.';
        }
        return [$offers, $err];
    }

                                                                                   
    public static function create(int $curatorId, array $in): array
    {
        [$d, $err] = self::validate($in);
        if ($d === null) {
            return [null, $err];
        }
        [$offers, $oerr] = self::parseOffers($d['platform'], $in);
        if ($oerr) {
            return [null, $oerr];
        }
        if ((int)DB::val('SELECT COUNT(*) FROM listings WHERE curator_id = ?', [$curatorId]) >= 20) {
            return [null, ['You can have up to 20 listings. Contact support if you need more.']];
        }
        if (DB::one('SELECT id FROM listings WHERE url = ? AND status <> ?', [$d['url'], 'rejected']) !== null) {
            return [null, ['That link is already listed on PlugTheList. If it is yours, contact support.']];
        }
        $id = DB::tx(static function () use ($curatorId, $d, $offers): int {
            $id = DB::insert('listings', $d + [
                'curator_id' => $curatorId, 'status' => 'pending',
                'verify_code' => 'PTL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
                'min_price_kobo' => min(array_column($offers, 'price_kobo')),
            ]);
            self::saveOffers($id, $offers);
            Notifier::admins('New listing to review', $d['title'], url('admin', '/listings?status=pending'));
            return $id;
        });
        Audit::log($curatorId, 'listing_created', 'listing', $id);
        return [$id, []];
    }

                                                                          
    public static function update(int $curatorId, int $id, array $in): array
    {
        $l = DB::one('SELECT * FROM listings WHERE id = ? AND curator_id = ?', [$id, $curatorId]);
        if ($l === null) {
            return ['Listing not found.'];
        }
        [$d, $err] = self::validate($in);
        if ($d === null) {
            return $err;
        }
        [$offers, $oerr] = self::parseOffers($d['platform'], $in);
        if ($oerr) {
            return $oerr;
        }
        $identityChanged = $d['url'] !== $l['url'] || $d['platform'] !== $l['platform'];
        DB::tx(static function () use ($id, $d, $offers, $identityChanged): void {
            $extra = ['min_price_kobo' => min(array_column($offers, 'price_kobo'))];
            if ($identityChanged) {
                $extra += ['status' => 'pending', 'verified' => 0, 'verified_at' => null, 'reject_reason' => null];
            }
            DB::update('listings', $d + $extra, 'id = ?', [$id]);
            self::saveOffers($id, $offers);
        });
        Audit::log($curatorId, 'listing_updated', 'listing', $id);
        return [];
    }

                                                                                                             
    private static function saveOffers(int $listingId, array $offers): void
    {
                                                                                                                               
        DB::update('listing_offers', ['active' => 0], 'listing_id = ?', [$listingId]);
        foreach ($offers as $o) {
            $row = DB::one('SELECT id FROM listing_offers WHERE listing_id = ? AND service = ?', [$listingId, $o['service']]);
            $vals = ['price_kobo' => $o['price_kobo'], 'turnaround_days' => $o['turnaround_days'], 'details' => $o['details'], 'active' => 1];
            if ($row) {
                DB::update('listing_offers', $vals, 'id = ?', [$row['id']]);
            } else {
                DB::insert('listing_offers', $vals + ['listing_id' => $listingId, 'service' => $o['service']]);
            }
        }
    }

                                                                                                   
    private static function validate(array $in): array
    {
        $err = [];
        $platform = (string)($in['platform'] ?? '');
        if (!isset(Catalog::platforms()[$platform])) {
            $err[] = 'Choose a platform.';
        }
        $title = Validator::name((string)($in['title'] ?? ''), 3, 120);
        if ($title === null) {
            $err[] = 'Give the listing a name (3 to 120 characters).';
        }
        $url = Validator::url((string)($in['url'] ?? ''));
        if ($url === null) {
            $err[] = 'Paste the full https link to your playlist, channel or community.';
        }
        $followers = Validator::intRange((string)($in['followers'] ?? ''), 0, 500000000);
        if ($followers === null) {
            $err[] = 'Enter your follower, subscriber or member count as a number.';
        }
        $desc = Validator::text((string)($in['description'] ?? ''), 30, 1500);
        if ($desc === null) {
            $err[] = 'Describe your audience and what you accept (30 to 1,500 characters).';
        }
        $genres = [];
        foreach ((array)($in['genres'] ?? []) as $g) {
            if (is_string($g) && Catalog::genreValid($g)) {
                $genres[] = $g;
            }
        }
        $genres = array_slice(array_unique($genres), 0, 4);
        if (!$genres) {
            $err[] = 'Pick 1 to 4 genres.';
        }
        if ($err) {
            return [null, $err];
        }
        return [[
            'platform' => $platform, 'title' => $title, 'url' => $url, 'followers' => $followers,
            'description' => $desc, 'genres' => implode(',', $genres),
        ], []];
    }

       
                                                                                                                
                                                                   
       
    public static function search(array $f, int $page = 1, int $per = 12): array
    {
        $w = ["l.status = 'approved'", "u.status = 'active'"];
        $p = [];
        if (isset(Catalog::platforms()[$f['platform'] ?? ''])) {
            $w[] = 'l.platform = ?';
            $p[] = $f['platform'];
        }
        if (Catalog::genreValid($f['genre'] ?? '')) {
            $w[] = "(',' || l.genres || ',') LIKE ?";
            $p[] = '%,' . $f['genre'] . ',%';
        }
        $q = trim($f['q'] ?? '');
        if ($q !== '') {
            $w[] = '(l.title LIKE ? OR l.description LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['', ''], mb_substr($q, 0, 60)) . '%';
            array_push($p, $like, $like);
        }
        $budget = Money::parseNaira($f['max_budget'] ?? '');
        if ($budget !== null && $budget > 0) {
            $w[] = 'l.min_price_kobo <= ?';
            $p[] = $budget;
        }
        $minF = Validator::intRange($f['min_followers'] ?? '', 1, 500000000);
        if ($minF !== null) {
            $w[] = 'l.followers >= ?';
            $p[] = $minF;
        }
        $order = match ($f['sort'] ?? '') {
            'price_asc' => 'l.min_price_kobo ASC',
            'followers' => 'l.followers DESC',
            'rating' => '(CASE WHEN l.rating_count = 0 THEN 0 ELSE l.rating_sum * 1.0 / l.rating_count END) DESC, l.orders_done DESC',
            default => 'l.orders_done DESC, l.followers DESC',
        };
        $from = ' FROM listings l JOIN users u ON u.id = l.curator_id WHERE ' . implode(' AND ', $w);
                                                               
        if (DB::driver() === 'mysql') {
            $from = str_replace("(',' || l.genres || ',')", "CONCAT(',', l.genres, ',')", $from);
        }
        $total = (int)DB::val('SELECT COUNT(*)' . $from, $p);
        $per = max(1, min(48, $per));
        $off = (max(1, $page) - 1) * $per;
        $rows = DB::all('SELECT l.*, u.display_name AS curator_name' . $from . ' ORDER BY ' . $order . ', l.id DESC LIMIT ' . $per . ' OFFSET ' . $off, $p);
        return ['rows' => $rows, 'total' => $total];
    }

    public static function offers(int $listingId, bool $onlyActive = true): array
    {
        return DB::all('SELECT * FROM listing_offers WHERE listing_id = ?' . ($onlyActive ? ' AND active = 1' : '') . ' ORDER BY price_kobo ASC', [$listingId]);
    }

    public static function proofs(int $listingId): array
    {
        return DB::all('SELECT * FROM listing_proofs WHERE listing_id = ? ORDER BY id DESC', [$listingId]);
    }

    public static function rating(array $l): string
    {
        return (int)$l['rating_count'] > 0 ? number_format($l['rating_sum'] / $l['rating_count'], 1) . ' (' . (int)$l['rating_count'] . ')' : 'No ratings yet';
    }

    public static function setStatus(int $adminId, int $id, string $status, string $reason = '', ?bool $verified = null): void
    {
        $l = DB::one('SELECT * FROM listings WHERE id = ?', [$id]);
        if ($l === null || !in_array($status, ['approved', 'rejected', 'paused', 'pending'], true)) {
            return;
        }
        $d = ['status' => $status, 'reject_reason' => $status === 'rejected' ? mb_substr($reason, 0, 300) : null];
        if ($verified === true) {
            $d += ['verified' => 1, 'verified_at' => now()];
        }
        DB::update('listings', $d, 'id = ?', [$id]);
        Audit::log($adminId, 'listing_' . $status, 'listing', $id, ['reason' => $reason]);
        $msg = match ($status) {
            'approved' => 'Your listing "' . $l['title'] . '" is live. Artists can book it now.',
            'rejected' => 'Your listing "' . $l['title'] . '" was not approved. ' . $reason,
            default => 'Your listing "' . $l['title'] . '" is now ' . $status . '.',
        };
        Notifier::to((int)$l['curator_id'], 'Listing ' . $status, $msg, url('curator', '/listing?id=' . $id));
    }
}
