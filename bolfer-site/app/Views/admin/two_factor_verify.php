<?php
$title = 'Validar 2FA';
$bodyClass = 'admin-login';
require __DIR__ . '/partials/admin_header.php';
?>

<section class="section alt">
  <div class="container">
    <div class="card admin-login-card admin-security-card">
      <h1 class="admin-login-title">Confirmar 2FA</h1>
      <p class="admin-sub">Digite o código do aplicativo autenticador ou um código de recuperacao para entrar no painel.</p>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="admin-security-step">
        <strong>Conta em validacao</strong>
        <span><?= e((string) ($adminEmail ?? 'admin')); ?></span>
      </div>

      <form method="post" action="/admin/2fa/verify" class="form admin-auth-form">
        <?= csrf_field(); ?>

        <label>Código 2FA ou código de recuperacao</label>
        <input type="text" name="two_factor_code" autocomplete="one-time-code" placeholder="Ex.: 123456 ou ABCD-EFGH-IJKL" required>

        <button class="btn" type="submit">Validar acesso</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
