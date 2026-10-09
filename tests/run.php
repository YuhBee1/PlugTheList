<?php
declare(strict_types=1);

                                                                                                   

use PTL\{Auth, Crypto, DB, Escrow, Ledger, Listings, Migrator, Money, OrderState, Paystack, Settings, Totp, Wallet, Validator, RateLimiter, Privacy};

define('PTL_NO_SESSION', true);
define('PTL_TESTING', true);
$db = sys_get_temp_dir() . '/ptl_test_' . getmypid() . '.sqlite';
@unlink($db);
putenv('X=1');
require __DIR__ . '/../shared/bootstrap.php';
PTL\Env::set('DB_DSN', 'sqlite:' . $db);
PTL\Env::set('APP_KEY', base64_encode(random_bytes(32)));
PTL\Env::set('PAYSTACK_MOCK', '1');
PTL\Env::set('APP_ENV', 'test');
PTL\Env::set('URL_SCHEME', 'http');
PTL\Env::set('BASE_DOMAIN', 'plugthelist.test');

@unlink(PTL_STORAGE . '/logs/mail.log');
$pass = 0;
$fail = 0;
function t(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok   $name\n";
    } else {
        $fail++;
        echo "  FAIL $name $extra\n";
    }
}
function throws(callable $f): bool
{
    try {
        $f();
    } catch (\Throwable $e) {
        return true;
    }
    return false;
}

echo "Primitives\n";
t('TOTP RFC 6238 vector', Totp::code(Totp::b32encode('12345678901234567890'), 59, 8) === '94287082');
$f = Money::fees(1000000, 500, 1000);
t('fees', $f['total'] === 1050000 && $f['curator_net'] === 900000 && $f['platform'] === 150000);
foreach ([0, 1, 333333, 500000, 999999, 1000000] as $share) {
    $o = ['price' => 1000000, 'buyer_fee' => 50000, 'curator_fee' => 100000, 'total' => 1050000];
    $s = Money::split($o, $share);
    t("split conserves @$share", $s['curator'] + $s['platform'] + $s['refund'] === 1050000 && $s['refund'] >= 0);
}
t('parseNaira', Money::parseNaira('₦10,000') === 1000000 && Money::parseNaira('10000.5') === 1000050 && Money::parseNaira('abc') === null);
t('state machine', OrderState::can('paid', 'in_progress') && !OrderState::can('completed', 'refunded') && !OrderState::can('delivered', 'refunded'));
t('phone', Validator::phone('0803 123 4567') === '+2348031234567' && Validator::phone('12345') === null);
t('url https only', Validator::url('http://x.com/a') === null && Validator::url('https://open.spotify.com/playlist/abc') !== null && Validator::url('https://127.0.0.1/x') === null);
PTL\Env::set('APP_ROUTING_MODE', 'path');
PTL\Env::set('APP_BASE_URL', 'https://plugthelist.vercel.app');
t('Vercel public path URL', url('www', '/browse') === 'https://plugthelist.vercel.app/browse');
t('Vercel auth area path URL', url('auth', '/login') === 'https://plugthelist.vercel.app/auth/login');
t('Vercel area URLs share one origin', count(all_origins()) === 1);
PTL\Env::set('APP_ROUTING_MODE', '');
PTL\Env::set('APP_BASE_URL', '');
t('password rules', Validator::passwordProblems('short1') !== [] && Validator::passwordProblems('Correct-Horse-42') === []);
t('seal/open', Crypto::open(Crypto::seal('hello', 'x'), 'x') === 'hello' && Crypto::open(Crypto::seal('hello', 'x'), 'y') === null);
echo "Schema\n";
$log = Migrator::run();
t('migrate', count($log) > 30);
t('migrate twice', is_array(Migrator::run()));
t('rate limit blocks', (function () {
    RateLimiter::reset('rl', 'z');
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit('rl', 'z', 60);
    }
    return RateLimiter::over('rl', 'z', 3, 60) && !RateLimiter::over('rl', 'z', 4, 60);
})());

echo "Auth\n";
$good = ['email' => 'Cur@Example.com', 'full_name' => 'Ada Okafor', 'display_name' => 'Ada Plays', 'phone' => '08031234567', 'password' => 'Correct-Horse-42', 'password2' => 'Correct-Horse-42', 'terms' => '1', 'age18' => '1'];
[$cid, $e] = Auth::register('curator', $good);
t('register curator', $cid > 0 && !$e, json_encode($e));
[$dupe, $e2] = Auth::register('curator', $good);
t('duplicate email is silent', $dupe === 0 && !$e2);
[$zid, $e3] = Auth::register('creative', ['email' => 'art@example.com', 'full_name' => 'Tunde Bello', 'display_name' => 'Tunde B', 'creative_type' => 'artist'] + $good);
t('register creative', $zid > 0, json_encode($e3));
[, $bad] = Auth::register('creative', ['password' => 'x'] + $good);
t('weak password rejected', isset($bad['password']));
[, $bad2] = Auth::register('creative', ['creative_type' => 'hacker'] + ['email' => 'q@example.com'] + $good);
t('bad creative type rejected', isset($bad2['creative_type']));
t('login blocked until verified', Auth::login('cur@example.com', 'Correct-Horse-42')[0] === 'error');
$mail = (string)file_get_contents(PTL_STORAGE . '/logs/mail.log');
preg_match('#/verify\?t=([A-Za-z0-9_\-]+)#', $mail, $m);
t('verification mail has token', isset($m[1]));
t('verify email', Auth::verifyEmail($m[1]) !== null && Auth::verifyEmail($m[1]) === null);
DB::update('users', ['email_verified_at' => now()], 'id = ?', [$zid]);
t('wrong password rejected', Auth::login('cur@example.com', 'nope-nope-nope')[0] === 'error');
t('login ok', Auth::login('cur@example.com', 'Correct-Horse-42')[0] === 'ok');
$sec = Auth::beginTotp($cid);
$codes = Auth::enableTotp($cid, Totp::code($sec));
t('2FA enable returns 8 backup codes', is_array($codes) && count($codes) === 8);
Auth::requestReset('cur@example.com');
preg_match_all('#/reset\?t=([A-Za-z0-9_\-]+)#', (string)file_get_contents(PTL_STORAGE . '/logs/mail.log'), $rm);
$tok = end($rm[1]);
t('reset weak pw refused', Auth::resetPassword($tok, 'abc', 'abc') !== []);
t('reset works once', Auth::resetPassword($tok, 'Brand-New-Pass-77', 'Brand-New-Pass-77') === [] && Auth::resetPassword($tok, 'Another-Pass-88', 'Another-Pass-88') !== []);

echo "Listings\n";
$lin = ['platform' => 'spotify', 'title' => 'Naija Heat Playlist', 'url' => 'https://open.spotify.com/playlist/abc123', 'followers' => '25000', 'description' => str_repeat('Afrobeats fans. ', 4), 'genres' => ['Afrobeats'], 'offer_review_on' => '1', 'offer_review_price' => '15,000', 'offer_review_days' => '5', 'offer_feature_on' => '1', 'offer_feature_price' => '99999', 'offer_feature_days' => '5'];
[$lid, $le] = Listings::create($cid, $lin);
t('listing created', $lid > 0, json_encode($le));
t('Spotify only gets review service', DB::val('SELECT COUNT(*) FROM listing_offers WHERE listing_id = ?', [$lid]) == 1);
[, $le2] = Listings::create($cid, ['offer_review_price' => '5,000'] + $lin + ['url' => 'https://open.spotify.com/playlist/zzz']);
t('price below minimum refused', $le2 !== []);
[, $le3] = Listings::create($cid, $lin);
t('duplicate url refused', $le3 !== []);
t('pending listing not browsable', Listings::search([])['total'] === 0);
Listings::setStatus(1, $lid, 'approved', '', true);
t('approved listing browsable', Listings::search([])['total'] === 1 && Listings::search(['max_budget' => '10000'])['total'] === 0 && Listings::search(['genre' => 'Afrobeats'])['total'] === 1);
$offer = (int)DB::val('SELECT id FROM listing_offers WHERE listing_id = ?', [$lid]);

echo "Escrow happy path\n";
t('cannot book own listing', throws(fn() => Escrow::create($cid, $offer, 'Song', 'https://x.com/s', '')));
$oid = Escrow::create($zid, $offer, 'My Song', 'https://open.spotify.com/track/1', 'please');
$o = DB::one('SELECT * FROM orders WHERE id = ?', [$oid]);
t('order totals', (int)$o['total_kobo'] === 1575000 && (int)$o['curator_fee_kobo'] === 150000, json_encode($o));
$url = Escrow::startPayment($oid, $zid);
parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
$ref = $q['reference'];
t('wrong amount refused', Escrow::settlePayment($ref, 1, 'NGN') === 'mismatch');
$ref2 = parse_url(Escrow::startPayment($oid, $zid), PHP_URL_QUERY);
parse_str((string)$ref2, $q2);
t('wrong currency refused', Escrow::settlePayment($q2['reference'], 1575000, 'USD') === 'mismatch');
$url3 = Escrow::startPayment($oid, $zid);
parse_str((string)parse_url($url3, PHP_URL_QUERY), $q3);
t('settle pays', Escrow::settlePayment($q3['reference'], 1575000, 'NGN') === 'paid');
t('settle idempotent', Escrow::settlePayment($q3['reference'], 1575000, 'NGN') === 'already');
t('escrow holds total', Ledger::balance('escrow') === 1575000);
t('wrong curator cannot accept', throws(fn() => Escrow::accept($oid, $zid)));
Escrow::accept($oid, $cid);
t('cannot deliver as creative', throws(fn() => Escrow::deliver($oid, $zid, 'https://x.com', '', null)));
Escrow::deliver($oid, $cid, 'https://open.spotify.com/playlist/abc123', 'added at position 3', null);
t('cannot approve as curator', throws(fn() => Escrow::approve($oid, $cid)));
Escrow::approve($oid, $zid);
t('curator credited net', Ledger::userBalance($cid) === 1350000, (string)Ledger::userBalance($cid));
t('platform earned fees', Ledger::balance('platform') === 225000);
t('escrow empty', Ledger::balance('escrow') === 0);
t('double approve blocked', throws(fn() => Escrow::approve($oid, $zid)));
t('ledger balanced', Ledger::drift() === 0);
Escrow::review($oid, $zid, 5, 'great');
t('rating stored', (int)DB::val('SELECT rating_count FROM listings WHERE id = ?', [$lid]) === 1 && throws(fn() => Escrow::review($oid, $zid, 4, '')));

echo "Refunds, disputes, sweeps\n";
$mk = function () use ($zid, $offer): int {
    $id = Escrow::create($zid, $offer, 'Song B', 'https://x.com/b', '');
    parse_str((string)parse_url(Escrow::startPayment($id, $zid), PHP_URL_QUERY), $qq);
    Escrow::settlePayment($qq['reference'], 1575000, 'NGN');
    return $id;
};
$o2 = $mk();
Escrow::decline($o2, $cid, 'busy');
t('decline refunds', DB::val('SELECT status FROM orders WHERE id = ?', [$o2]) === 'refunded' && Ledger::balance('ext:paystack_refund') === 1575000);
$o3 = $mk();
Escrow::accept($o3, $cid);
Escrow::dispute($o3, $zid, 'not delivered');
t('dispute freezes funds', Ledger::balance('escrow') === 1575000);
Escrow::resolve(1, $o3, 500000, 'half');
$esc = Money::split(['price' => 1500000, 'buyer_fee' => 75000, 'curator_fee' => 150000, 'total' => 1575000], 500000);
t('resolve split posted', Ledger::balance('escrow') === 0 && Ledger::userBalance($cid) === 1350000 + $esc['curator']);
t('ledger still balanced', Ledger::drift() === 0);
t('cannot resolve twice', throws(fn() => Escrow::resolve(1, $o3, 0, 'x')));
$o4 = $mk();
DB::update('orders', ['accept_by' => date('Y-m-d H:i:s', time() - 60)], 'id = ?', [$o4]);
$o5 = Escrow::create($zid, $offer, 'Song C', 'https://x.com/c', '');
DB::update('orders', ['expires_at' => date('Y-m-d H:i:s', time() - 60)], 'id = ?', [$o5]);
$o6 = $mk();
Escrow::accept($o6, $cid);
Escrow::deliver($o6, $cid, 'https://x.com/p', '', null);
DB::update('orders', ['review_by' => date('Y-m-d H:i:s', time() - 60)], 'id = ?', [$o6]);
$c = Escrow::sweep();
t('sweep results', $c['expired'] === 1 && $c['accept_timeout'] === 1 && $c['auto_release'] === 1, json_encode($c));
t('sweep statuses', DB::val('SELECT status FROM orders WHERE id = ?', [$o4]) === 'refunded' && DB::val('SELECT status FROM orders WHERE id = ?', [$o5]) === 'cancelled' && DB::val('SELECT status FROM orders WHERE id = ?', [$o6]) === 'completed');
t('late payment on cancelled order is refunded', (function () use ($zid, $o5) {
    $ref = 'ptl_late';
    DB::insert('payments', ['order_id' => $o5, 'reference' => $ref, 'amount_kobo' => 1575000, 'status' => 'pending']);
    $before = Ledger::balance('ext:paystack_refund');
    Escrow::settlePayment($ref, 1575000, 'NGN');
    return Ledger::balance('ext:paystack_refund') === $before + 1575000 && Ledger::drift() === 0 && DB::val('SELECT status FROM orders WHERE id = ?', [$o5]) === 'cancelled';
})());
$o7 = $mk();
Escrow::accept($o7, $cid);
DB::update('orders', ['due_at' => date('Y-m-d H:i:s', time() - 4 * 86400)], 'id = ?', [$o7]);
t('non-delivery sweep refunds', Escrow::sweep()['late'] === 1 && DB::val('SELECT status FROM orders WHERE id = ?', [$o7]) === 'refunded');
[$esc1, $esc2] = Ledger::escrowCheck();
t('escrow equals live orders', $esc1 === $esc2, "$esc1 vs $esc2");

echo "Wallet\n";
$bal = Ledger::userBalance($cid);
t('no bank, no withdrawal', Wallet::request($cid, 1000000) !== null);
t('bank saved', Wallet::saveBank($cid, '058', '0123456789') === null);
t('below minimum refused', Wallet::request($cid, 500000) !== null);
t('over balance refused', Wallet::request($cid, $bal + 100) !== null);
t('request ok', Wallet::request($cid, 1000000) === null);
t('funds held', Ledger::userBalance($cid) === $bal - 1000000 && Ledger::balance('hold:withdrawal') === 1000000);
$wid = (int)DB::val('SELECT id FROM withdrawals WHERE user_id = ?', [$cid]);
t('approve pays out', Wallet::approve(1, $wid) === null && DB::val('SELECT status FROM withdrawals WHERE id = ?', [$wid]) === 'paid' && Ledger::balance('hold:withdrawal') === 0);
t('second approve blocked', Wallet::approve(1, $wid) !== null);
Wallet::request($cid, 1000000);
$w2 = (int)DB::val('SELECT MAX(id) FROM withdrawals');
Wallet::reject(1, $w2, 'test');
t('reject returns funds', Ledger::userBalance($cid) === $bal - 1000000 && Ledger::balance('hold:withdrawal') === 0);
t('ledger balanced at end', Ledger::drift() === 0);

echo "Privacy\n";
t('export has data', count(Privacy::export($cid)['orders']) > 0);
t('delete blocked with balance', Privacy::delete($cid) !== null);
t('delete ok for clean user', (function () {
    [$id] = Auth::register('creative', ['email' => 'gone@example.com', 'full_name' => 'Gone Soon', 'display_name' => 'Gone', 'creative_type' => 'artist', 'phone' => '08031234567', 'password' => 'Correct-Horse-42', 'password2' => 'Correct-Horse-42', 'terms' => '1', 'age18' => '1']);
    return Privacy::delete($id) === null && DB::val('SELECT status FROM users WHERE id = ?', [$id]) === 'deleted';
})());

@unlink($db);
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
