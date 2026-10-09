<?php
declare(strict_types=1);

                                                                                                                                      
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../shared/bootstrap.php';
$email = PTL\Validator::email($argv[1] ?? '');
$name = PTL\Validator::name($argv[2] ?? 'Administrator');
if ($email === null || $name === null) {
    fwrite(STDERR, "Usage: php bin/create_admin.php email \"Full Name\"\n");
    exit(1);
}
if (PTL\DB::one('SELECT id FROM users WHERE email = ?', [$email])) {
    fwrite(STDERR, "That email already has an account.\n");
    exit(1);
}
$pass = substr(rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'AB'), '='), 0, 20) . '7a';
$id = PTL\DB::insert('users', [
    'role' => 'admin', 'email' => $email, 'email_verified_at' => now(), 'password_hash' => PTL\Auth::hash($pass),
    'full_name' => $name, 'display_name' => $name, 'status' => 'active', 'terms_version' => PTL\Auth::TERMS_VERSION, 'terms_accepted_at' => now(),
]);
PTL\Audit::log(null, 'admin_created', 'user', $id);
echo "Admin created.\nEmail:    $email\nPassword: $pass\nSign in at " . url('auth', '/login') . " then open Account and turn on two-step verification (required before the admin area opens).\n";
