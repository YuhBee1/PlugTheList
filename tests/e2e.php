<?php
declare(strict_types=1);
                                                        
$W = 'http://127.0.0.1:8081'; $A = 'http://127.0.0.1:8082'; $C = 'http://127.0.0.1:8083'; $P = 'http://127.0.0.1:8084'; $AD = 'http://127.0.0.1:8085'; $API = 'http://127.0.0.1:8086';
$pass = 0; $fail = 0;
function t(string $n, bool $ok, string $x = ''): void { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  ok   ' : '  FAIL ') . $n . ($ok ? '' : " $x") . "\n"; }

final class Client {
    public string $jar; public array $last = [];
    public function __construct(string $name) { $this->jar = "/tmp/ptl_jar_$name"; @unlink($this->jar); }
    public function req(string $method, string $url, array $post = [], array $hdr = [], bool $follow = false): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 6, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $hdr, CURLOPT_USERAGENT => 'e2e']);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
        $raw = (string)curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $h = substr($raw, 0, $hs); $b = substr($raw, $hs);
        preg_match('/^Location:\s*(.+)$/mi', $h, $m);
        return $this->last = ['code' => $code, 'headers' => $h, 'body' => $b, 'loc' => trim($m[1] ?? '')];
    }
    public function get(string $u, bool $follow = true): array { return $this->req('GET', $u, [], [], $follow); }
    public function csrf(string $url): string { $r = $this->get($url); preg_match('/name="_csrf" value="([^"]+)"/', $r['body'], $m); return $m[1] ?? ''; }
    public function post(string $pageUrl, string $actUrl, array $data, bool $follow = true, array $hdr = []): array {
        $data['_csrf'] = $this->csrf($pageUrl);
        return $this->req('POST', $actUrl, $data, $hdr, $follow);
    }
}
function mailTokens(string $kind): array { preg_match_all('#/' . $kind . '\?t=([A-Za-z0-9_\-]+)#', (string)@file_get_contents(__DIR__ . '/../storage/logs/mail.log'), $m); return $m[1]; }
function totp(string $secret): string { return \PTL\Totp::code($secret); }
define('PTL_NO_SESSION', true); require __DIR__ . '/../shared/bootstrap.php';

echo "Public pages and headers\n";
$g = new Client('guest');
foreach (['/', '/browse', '/how-it-works', '/pricing', '/terms', '/privacy', '/acceptable-use', '/escrow-refunds', '/cookies', '/contact', '/robots.txt', '/sitemap.xml', '/assets/ptl.css', '/assets/ptl.js'] as $p) {
    t("GET www$p", $g->get($W . $p)['code'] === 200, (string)$g->last['code']);
}
$r = $g->get($W . '/');
t('CSP header', str_contains($r['headers'], 'Content-Security-Policy') && str_contains($r['headers'], "frame-ancestors 'none'"));
t('no inline script/style/handlers in HTML', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>|<style|\sstyle="|\son[a-z]+="/i', $r['body']));
t('listing 404', $g->get($W . '/listing?id=999')['code'] === 404);
t('unknown page 404', $g->get($W . '/nope')['code'] === 404);
t('shared dir not exposed', $g->get($W . '/../shared/bootstrap.php')['code'] === 404);

echo "Access control and CSRF\n";
$r = $g->get($P . '/', false);
t('creative area redirects guests to sign in', $r['code'] === 302 && str_contains($r['loc'], '/login'));
t('open redirect refused', (function () use ($g, $A) { $r = $g->get($A . '/login?next=' . rawurlencode('https://evil.example/x'), false); return $r['code'] === 200; })());
$r = $g->req('POST', $A . '/login', ['email' => 'a@b.com', 'password' => 'x']);
t('POST without CSRF rejected', $r['code'] === 419, (string)$r['code']);
$tok = $g->csrf($A . '/login');
$r = $g->req('POST', $A . '/login', ['_csrf' => $tok, 'email' => 'a@b.com', 'password' => 'x'], ['Origin: https://evil.example']);
t('cross-origin POST rejected', $r['code'] === 403, (string)$r['code']);
$r = $g->req('POST', $A . '/login', ['_csrf' => $tok, 'email' => 'nobody@example.com', 'password' => 'wrongwrong1']);
t('bad login gives generic message', $r['code'] === 200 && str_contains($r['body'], 'Email or password is not right'));
t('webhook rejects unsigned', $g->req('POST', $API . '/webhook', ['x' => 1])['code'] === 401);
t('cron rejects without token', $g->get($API . '/cron')['code'] === 403);

echo "Sign up\n";
$cur = new Client('cur'); $cre = new Client('cre');
$base = ['full_name' => 'Ada Okafor', 'display_name' => 'Ada Plays', 'phone' => '08031234567', 'password' => 'Correct-Horse-42', 'password2' => 'Correct-Horse-42', 'terms' => '1', 'age18' => '1'];
$r = $cur->post($A . '/register/curator', $A . '/register/curator', ['email' => 'ada@example.com'] + $base, false);
t('curator registers', $r['code'] === 302 && str_contains($r['loc'], 'check-email'), $r['code'] . ' ' . substr(strip_tags($r['body']), 0, 200));
$r = $cre->post($A . '/register/creative', $A . '/register/creative', ['email' => 'tunde@example.com', 'full_name' => 'Tunde Bello', 'display_name' => 'Tunde B', 'creative_type' => 'artist'] + $base, false);
t('creative registers', $r['code'] === 302);
$r = $cur->post($A . '/login', $A . '/login', ['email' => 'ada@example.com', 'password' => 'Correct-Horse-42']);
t('login blocked until email verified', str_contains($r['body'], 'Confirm your email'));
foreach (mailTokens('verify') as $tk) { $cur->get($A . '/verify?t=' . $tk); }
$r = $cur->post($A . '/login', $A . '/login', ['email' => 'ada@example.com', 'password' => 'Correct-Horse-42'], false);
t('curator signs in and lands on curator area', $r['code'] === 302 && str_starts_with($r['loc'], $C), $r['loc']);
$r = $cre->post($A . '/login', $A . '/login', ['email' => 'tunde@example.com', 'password' => 'Correct-Horse-42'], false);
t('creative signs in and lands on app area', $r['code'] === 302 && str_starts_with($r['loc'], $P), $r['loc']);
t('curator dashboard loads', $cur->get($C . '/')['code'] === 200);
t('curator cannot open creative area', $cur->get($P . '/')['code'] === 403);
t('creative cannot open curator area', $cre->get($C . '/')['code'] === 403);
t('creative cannot open admin', $cre->get($AD . '/')['code'] === 403);

echo "Admin and 2FA\n";
$adm = new Client('adm');
$out = shell_exec('php ' . escapeshellarg(__DIR__ . '/../bin/create_admin.php') . ' boss@example.com "Boss Admin" 2>&1');
preg_match('/Password: (\S+)/', (string)$out, $m); $apw = $m[1] ?? '';
t('admin created', $apw !== '');
$r = $adm->post($A . '/login', $A . '/login', ['email' => 'boss@example.com', 'password' => $apw], false);
t('admin signs in', $r['code'] === 302);
$r = $adm->get($AD . '/', false);
t('admin area blocked until 2FA is on', $r['code'] === 302 && str_contains($r['loc'], '/account'), $r['code'] . ' ' . $r['loc']);
$adm->post($AD . '/account', $AD . '/account', ['action' => 'totp_begin']);
$r = $adm->get($AD . '/account');
                                                                                                         
$r = $adm->post($AD . '/account', $AD . '/account', ['action' => 'totp_begin']);
preg_match('#<p class="key"><code>([A-Z2-7 ]+)</code>#', $r['body'], $m);
$secret = str_replace(' ', '', $m[1] ?? '');
t('2FA secret shown', strlen($secret) >= 16);
$r = $adm->post($AD . '/account', $AD . '/account', ['action' => 'totp_enable', 'code' => totp($secret)]);
t('2FA enabled with backup codes', substr_count($r['body'], '<code>') >= 8);
                                    
$adm2 = new Client('adm2');
$r = $adm2->post($A . '/login', $A . '/login', ['email' => 'boss@example.com', 'password' => $apw], false);
t('login now asks for 2FA', str_contains($r['loc'], '/2fa'), $r['loc']);
t('admin area closed before 2FA code', $adm2->get($AD . '/', false)['code'] === 302);
$r = $adm2->post($A . '/2fa', $A . '/2fa', ['code' => '000000']);
t('wrong 2FA code refused', str_contains($r['body'], 'not right'));
sleep(1);
$r = $adm2->post($A . '/2fa', $A . '/2fa', ['code' => totp($secret)], false);
t('2FA code accepted (replay-safe step may need next window)', $r['code'] === 302 || str_contains($r['body'], 'not right'), $r['code'] . '');
if ($r['code'] !== 302) { echo "    (waiting for next TOTP window)\n"; sleep(31); $r = $adm2->post($A . '/2fa', $A . '/2fa', ['code' => totp($secret)], false); t('2FA accepted next window', $r['code'] === 302); }
t('admin overview loads', $adm2->get($AD . '/')['code'] === 200 && str_contains($adm2->last['body'], 'Health checks'));
t('ledger health OK', str_contains($adm2->last['body'], 'Ledger balances to zero (drift 0)'));

echo "Listing, approval, browse\n";
$form = ['platform' => 'audiomack', 'title' => 'Lagos Late Night', 'url' => 'https://audiomack.com/playlist/ada/lagos', 'followers' => '42000', 'description' => 'Afrobeats and street-pop fans in Lagos and Abuja, mostly 18 to 30.', 'genres[]' => 'Afrobeats', 'offer_review_on' => '1', 'offer_review_price' => '10,000', 'offer_review_days' => '3', 'offer_post_on' => '1', 'offer_post_price' => '25,000', 'offer_post_days' => '2'];
$r = $cur->post($C . '/listing', $C . '/listing', $form, false);
t('listing created', $r['code'] === 302 && str_contains($r['loc'], '/listing?id='), $r['code'] . substr(strip_tags($r['body']), 0, 300));
preg_match('/id=(\d+)/', $r['loc'], $m); $lid = (int)($m[1] ?? 0);
t('hidden from public until approved', $g->get($W . '/listing?id=' . $lid)['code'] === 404);
$r = $cur->post($C . '/listing', $C . '/listing', ['title' => 'x'] + $form, false);
t('bad listing rejected, form re-shown', $r['code'] === 200);
$r = $adm2->post($AD . '/listings?status=pending', $AD . '/listings?status=pending', ['id' => (string)$lid, 'action' => 'approve'], false);
t('admin approves', $r['code'] === 302);
t('listing page public', $g->get($W . '/listing?id=' . $lid)['code'] === 200 && str_contains($g->last['body'], 'Lagos Late Night'));
t('browse shows it', str_contains($g->get($W . '/browse?genre=Afrobeats&max_budget=15000')['body'], 'Lagos Late Night'));
t('browse budget filter excludes', !str_contains($g->get($W . '/browse?max_budget=5000')['body'], 'Lagos Late Night'));
t('SQL-ish input is harmless', $g->get($W . "/browse?q=" . rawurlencode("' OR 1=1 --") . '&sort=' . rawurlencode('1;DROP TABLE users'))['code'] === 200);
t('XSS is escaped', !str_contains($g->get($W . '/browse?q=' . rawurlencode('<script>alert(1)</script>'))['body'], '<script>alert(1)'));

echo "Booking and escrow over HTTP\n";
$offerId = (int)\PTL\DB::val("SELECT id FROM listing_offers WHERE listing_id = ? AND service = 'post'", [$lid]);
                                           
$r = $cre->post($P . '/order_new?offer=' . $offerId, $P . '/order_new?offer=' . $offerId, ['offer' => (string)$offerId, 'track_title' => 'Moto', 'track_url' => 'https://audiomack.com/tunde/song/moto', 'brief' => 'Drop on Friday', 'terms' => '1'], false);
t('order created', $r['code'] === 302 && str_contains($r['loc'], '/order?id='), $r['code'] . '');
preg_match('/id=(\d+)/', $r['loc'], $m); $oid = (int)($m[1] ?? 0);
t('curator cannot see order before payment? (visible, unpaid)', $cur->get($C . '/order?id=' . $oid)['code'] === 200);
t('guests cannot see it', !str_contains((new Client('x'))->get($P . '/order?id=' . $oid)['body'], 'Moto'));
$r = $cre->post($P . '/order?id=' . $oid, $P . '/pay', ['id' => (string)$oid], true);
t('pay → mock Paystack → callback → order paid', str_contains($r['body'], 'Paid, waiting for curator') || str_contains($r['body'], 'held in escrow'), substr(strip_tags($r['body']), 0, 300));
t('escrow holds money', \PTL\Ledger::balance('escrow') === 2625000, (string)\PTL\Ledger::balance('escrow'));
$r = $cur->post($C . '/order?id=' . $oid, $C . '/order?id=' . $oid, ['id' => (string)$oid, 'action' => 'accept']);
t('curator accepts', str_contains($r['body'], 'In progress'));
$r = $cre->post($P . '/order?id=' . $oid, $P . '/order?id=' . $oid, ['id' => (string)$oid, 'action' => 'approve']);
t('creative cannot approve before delivery', str_contains($r['body'], 'not possible') || str_contains($r['body'], 'In progress'));
$r = $cur->post($C . '/order?id=' . $oid, $C . '/order?id=' . $oid, ['id' => (string)$oid, 'action' => 'deliver', 'delivery_url' => 'https://audiomack.com/playlist/ada/lagos', 'delivery_note' => 'Position 4']);
t('curator delivers', str_contains($r['body'], 'Delivered'));
$r = $cre->post($P . '/order?id=' . $oid, $P . '/order?id=' . $oid, ['id' => (string)$oid, 'action' => 'approve']);
t('creative approves → completed', str_contains($r['body'], 'Completed'));
t('curator wallet credited (₦25,000 − 10% = ₦22,500)', \PTL\Ledger::userBalance((int)\PTL\DB::val("SELECT id FROM users WHERE email = 'ada@example.com'")) === 2250000);
$r = $cre->post($P . '/order?id=' . $oid, $P . '/order?id=' . $oid, ['id' => (string)$oid, 'action' => 'review', 'rating' => '5', 'comment' => 'Solid']);
t('rating saved', str_contains($r['body'], 'Thanks for rating'), substr(trim(preg_replace('/\\s+/', ' ', strip_tags($r['body']))), 0, 400));

echo "Wallet and withdrawal over HTTP\n";
$r = $cur->post($C . '/wallet', $C . '/wallet', ['action' => 'bank', 'bank_code' => '058', 'account_number' => '0123456789', 'current' => 'wrong-pass-123']);
t('bank save needs password', str_contains($r['body'], 'password was not right'), substr(trim(preg_replace('/\\s+/', ' ', strip_tags($r['body']))), 0, 500) . ' ' . $r['code']);
$r = $cur->post($C . '/wallet', $C . '/wallet', ['action' => 'bank', 'bank_code' => '058', 'account_number' => '0123456789', 'current' => 'Correct-Horse-42']);
t('bank saved', str_contains($r['body'], 'Bank account saved'));
$r = $cur->post($C . '/wallet', $C . '/wallet', ['action' => 'withdraw', 'amount' => '5,000']);
t('below minimum refused', str_contains($r['body'], 'minimum withdrawal'));
$r = $cur->post($C . '/wallet', $C . '/wallet', ['action' => 'withdraw', 'amount' => '20,000']);
t('withdrawal requested', str_contains($r['body'], 'Withdrawal requested'));
$wid = (int)\PTL\DB::val('SELECT id FROM withdrawals ORDER BY id DESC');
$r = $adm2->post($AD . '/withdrawals', $AD . '/withdrawals', ['id' => (string)$wid, 'action' => 'approve']);
t('admin approves payout', str_contains($r['body'], 'Done') && \PTL\DB::val('SELECT status FROM withdrawals WHERE id = ?', [$wid]) === 'paid');

echo "Webhook (signed)\n";
$o2 = \PTL\Escrow::create((int)\PTL\DB::val("SELECT id FROM users WHERE email = 'tunde@example.com'"), $offerId, 'Song 2', 'https://audiomack.com/x', '');
$pref = 'ptl_' . bin2hex(random_bytes(10));
\PTL\DB::insert('payments', ['order_id' => $o2, 'reference' => $pref, 'amount_kobo' => 2625000, 'status' => 'pending']);
$body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $pref, 'amount' => 2625000, 'currency' => 'NGN', 'channel' => 'card']]);
$sig = hash_hmac('sha512', $body, 'sk_test_e2e_secret');
$call = fn() => (new Client('wh'))->req('POST', $API . '/webhook', [], ['X-Paystack-Signature: ' . $sig, 'Content-Type: application/json']);
$ch = curl_init($API . '/webhook'); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['X-Paystack-Signature: ' . $sig, 'Content-Type: application/json']]);
$res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
t('signed webhook accepted', $code === 200, (string)$res);
t('order marked paid by webhook', \PTL\DB::val('SELECT status FROM orders WHERE id = ?', [$o2]) === 'paid');
$res2 = curl_exec($ch);
t('replayed webhook is idempotent', curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && \PTL\Ledger::balance('escrow') === 2625000);
$bad = str_replace('2625000', '100', $body);
curl_setopt($ch, CURLOPT_POSTFIELDS, $bad); curl_exec($ch);
t('tampered body rejected', curl_getinfo($ch, CURLINFO_HTTP_CODE) === 401);
t('cron with token runs', (function () use ($API) { $ch = curl_init($API . '/cron'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['X-Cron-Token: cron_token_for_tests_1234567890']]); $r = curl_exec($ch); return curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && str_contains((string)$r, '"ok":true'); })());

echo "Privacy, sign out\n";
$r = $cre->post($P . '/account', $P . '/account', ['action' => 'export']);
t('data export is JSON', str_contains($r['headers'], 'application/json') && str_contains($r['body'], '"account"'));
$r = $cur->post($C . '/', $A . '/logout', [], false);
t('sign out works', $r['code'] === 302);
t('after sign out, area is closed', $cur->get($C . '/', false)['code'] === 302);
t('ledger balanced at end', \PTL\Ledger::drift() === 0);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
