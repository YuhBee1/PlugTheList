<?php
declare(strict_types=1);

                                                                                                                      
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../shared/bootstrap.php';
foreach (PTL\Migrator::run() as $line) {
    echo $line, "\n";
}
echo "Migration finished.\n";
