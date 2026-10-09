<?php
declare(strict_types=1);

define('PTL_NO_SESSION', true);
define('PTL_API', true);
require __DIR__ . '/../shared/bootstrap.php';
json_out(['ok' => true, 'service' => 'PlugTheList API']);
