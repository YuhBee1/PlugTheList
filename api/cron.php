<?php
declare(strict_types=1);

   
                       
                                                                                                         
                                                                                            
   
define('PTL_NO_SESSION', true);
define('PTL_API', true);
require __DIR__ . '/../shared/bootstrap.php';

use PTL\DB;
use PTL\Escrow;
use PTL\RateLimiter;

if (PHP_SAPI !== 'cli') {
    $tok = (string)env('CRON_TOKEN', '');
    $provided = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
    if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
        $provided = $m[1];
    }
    if (strlen($tok) < 24 || !hash_equals($tok, $provided)) {
        json_out(['ok' => false], 403);
    }
}
$dbLock = false;
$lock = null;
if (DB::driver() === 'mysql') {
    $dbLock = (int)DB::val('SELECT GET_LOCK(?, 0)', ['plugthelist-cron']) === 1;
    if (!$dbLock) {
        PHP_SAPI === 'cli' ? exit(0) : json_out(['ok' => true, 'skipped' => 'already running']);
    }
} else {
    $lock = fopen(PTL_STORAGE . '/cron.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        PHP_SAPI === 'cli' ? exit(0) : json_out(['ok' => true, 'skipped' => 'already running']);
    }
}
try {
    $out = Escrow::sweep();
    $out['rate_limits_purged'] = RateLimiter::purge();
    $cut = date('Y-m-d H:i:s', time() - 30 * 86400);
    $out['sessions_purged'] = DB::run('DELETE FROM sessions WHERE expires_at < ? OR (revoked_at IS NOT NULL AND revoked_at < ?)', [$cut, $cut]);
    $out['tokens_purged'] = DB::run('DELETE FROM email_tokens WHERE expires_at < ?', [$cut]);
    $out['audit_purged'] = DB::run('DELETE FROM audit_logs WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 730 * 86400)]);
    DB::run("UPDATE payments SET status = 'abandoned' WHERE status = 'pending' AND created_at < ?", [date('Y-m-d H:i:s', time() - 3 * 86400)]);
} finally {
    if ($dbLock) {
        DB::val('SELECT RELEASE_LOCK(?)', ['plugthelist-cron']);
    } elseif (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
if (PHP_SAPI === 'cli') {
    echo json_encode($out), "\n";
    exit(0);
}
json_out(['ok' => true] + $out);
