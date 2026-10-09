<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Audit;
use PTL\Security;
use PTL\Session;

Security::requirePost();
$row = Session::row();
if ($row) {
    Audit::log((int)$row['user_id'], 'logout', 'user', (int)$row['user_id']);
}
Session::destroy();
flash('ok', 'You are signed out.');
redirect(url('www', '/'));
