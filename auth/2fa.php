<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Auth;
use PTL\Security;
use PTL\Session;

$row = Session::row();
if ($row === null) {
    redirect(url('auth', '/login'));
}
$next = Security::safeNext(qs('next', post('next')), '');
if (Session::user()) {
    redirect($next !== '' ? $next : role_home((string)Session::user()['role']));
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePost();
    if (Auth::verifyMfa(post('code'))) {
        redirect($next !== '' ? $next : role_home((string)Session::user()['role']));
    }
    $error = 'That code is not right, or you have tried too many times. Wait a few minutes if this keeps happening.';
}
page_header(['title' => 'Two-step verification', 'area' => 'auth']);
?>
<main class="wrap auth-wrap">
  <div class="auth-card">
    <h1>Two-step verification</h1>
    <p class="lede">Enter the 6-digit code from your authenticator app, or one of your backup codes.</p>
    <?= $error ? error_box([$error]) : '' ?>
    <form method="post" class="form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?= field('code', 'Code', 'text', '', null, ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'required' => true, 'autofocus' => true, 'maxlength' => 9]) ?>
      <button class="btn wide" type="submit">Verify</button>
    </form>
    <form method="post" action="<?= e(url('auth', '/logout')) ?>" class="alt"><?= csrf_field() ?><button class="link" type="submit">Cancel and sign out</button></form>
  </div>
</main>
<?php
page_footer();
