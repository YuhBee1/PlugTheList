<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
define('PTL_ALLOW_ADMIN_NO_2FA', true);                                                            
require PTL_SHARED . '/views/account.php';
account_page();
