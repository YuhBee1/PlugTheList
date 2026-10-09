<?php
declare(strict_types=1);

                                                               
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../shared/bootstrap.php';
$ok = true;
$say = function (bool $good, string $msg) use (&$ok): void {
    echo ($good ? '  ok    ' : '  FIX   '), $msg, "\n";
    $ok = $ok && $good;
};
$say(PHP_VERSION_ID >= 80100, 'PHP 8.1 or newer (you have ' . PHP_VERSION . ')');
foreach (['pdo_mysql', 'curl', 'mbstring', 'sodium', 'gd', 'openssl', 'json'] as $x) {
    $say(extension_loaded($x), "extension $x");
}
$say(defined('PASSWORD_ARGON2ID'), 'Argon2id available (otherwise bcrypt is used, which is still fine)');
foreach (['APP_KEY', 'DB_NAME', 'DB_USER', 'BASE_DOMAIN', 'COOKIE_DOMAIN', 'PAYSTACK_SECRET', 'CRON_TOKEN', 'MAIL_HOST'] as $k) {
    $say((string)env($k, '') !== '', "env $k set");
}
$say(env('APP_ENV') === 'production', 'APP_ENV=production');
$say(!PTL\Paystack::mock(), 'Paystack mock mode is off');
$say(env('MAIL_DRIVER', 'log') !== 'log', 'MAIL_DRIVER is smtp or mail (log means emails are NOT sent)');
$say(env('URL_SCHEME', 'https') === 'https', 'URL_SCHEME=https');
$say(is_writable(PTL_STORAGE) && is_writable(PTL_STORAGE . '/logs') && is_writable(PTL_STORAGE . '/uploads'), 'storage/ is writable');
try {
    PTL\DB::val('SELECT 1');
    $say(true, 'database connects (' . PTL\DB::driver() . ')');
    $say(PTL\DB::one("SELECT 1 FROM users LIMIT 1") !== false, 'tables exist (run bin/migrate.php if not)');
} catch (Throwable $e) {
    $say(false, 'database: ' . $e->getMessage());
}
$say(env('LEGAL_DRAFT', '1') === '0', 'LEGAL_DRAFT=0 (only after a lawyer reviewed the legal pages)');
echo $ok ? "\nAll good.\n" : "\nFix the items above before going live.\n";
exit($ok ? 0 : 1);
