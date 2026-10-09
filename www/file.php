<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\RateLimiter;
use PTL\Session;
use PTL\Upload;

RateLimiter::enforce('file', client_ip(), 300, 300);
Upload::serve((int)qs('id'), Session::user());
