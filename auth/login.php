<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\Security;
use PTL\Session;

$next = Security::safeNext(qs('next', post('next')), '');
if ($u = Session::user()) {
    redirect($next !== '' ? $next : role_home((string)$u['role']));
}
$error = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    $email = post('email');
    [$state, $msg] = Auth::login($email, (string)($_POST['password'] ?? ''));
    if ($state === 'mfa') {
        redirect(url('auth', '/2fa' . ($next !== '' ? '?next=' . rawurlencode($next) : '')));
    }
    if ($state === 'ok') {
        $u = Session::user();
        redirect($next !== '' ? $next : role_home((string)$u['role']));
    }
    $error = $msg;
}
page_header(['title' => 'Sign in', 'area' => 'auth']);
?>
<main class="wrap auth-wrap">
  <div class="auth-card">
    <h1>Sign in</h1>
    <?= $error ? error_box([$error]) : '' ?>
    <form method="post" class="form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?= field('email', 'Email', 'email', $email, null, ['autocomplete' => 'username', 'required' => true, 'autofocus' => true]) ?>
      <?= field('password', 'Password', 'password', '', null, ['autocomplete' => 'current-password', 'required' => true]) ?>
      <button class="btn wide" type="submit">Sign in</button>
    </form>
    <p class="alt"><a href="<?= e(url('auth', '/forgot')) ?>">Forgot your password?</a> · <a href="<?= e(url('auth', '/resend')) ?>">Resend confirmation email</a></p>
    <p class="alt">New here? <a href="<?= e(url('auth', '/register')) ?>">Create an account</a></p>
  </div>
</main>
<?php
page_footer();
