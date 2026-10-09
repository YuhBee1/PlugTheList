<?php
declare(strict_types=1);

define('PTL_NO_SESSION', true);
define('PTL_API', true);
require __DIR__ . '/../shared/bootstrap.php';

try {
    PTL\DB::val('SELECT 1');
    json_out(['ok' => true]);
} catch (Throwable $e) {
    error_log('[health] ' . $e->getMessage());
    json_out(['ok' => false], 503);
}
