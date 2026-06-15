<?php
$title = 'Ativar 2FA';
$bodyClass = 'admin-login';
require __DIR__ . '/partials/admin_header.php';
?>

<section class="section alt">
  <div class="container">
    <div class="card admin-login-card admin-security-card">
      <h1 class="admin-login-title">Ativar 2FA no painel</h1>
      <p class="admin-sub">Confirme o seu aplicativo autenticador para concluir o acesso ao painel administrativo.</p>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="admin-security-steps">
        <div class="admin-security-step">
          <strong>1. Conta</strong>
          <span><?= e((string) ($adminEmail ?? 'admin')); ?></span>
        </div>
        <div class="admin-security-step admin-security-step--qr">
          <strong>2. Escaneie o QR Code</strong>
          <div class="admin-qr-shell">
            <img src="<?= e((string) ($qrCodeUrl ?? '')); ?>" alt="QR Code para ativar o 2FA" loading="lazy" referrerpolicy="no-referrer">
          </div>
          <span>Se preferir, use a chave secreta logo abaixo no aplicativo autenticador.</span>
        </div>
        <div class="admin-security-step">
          <strong>3. Chave secreta</strong>
          <code><?= e((string) ($secret ?? '')); ?></code>
        </div>
        <div class="admin-security-step">
          <strong>4. Otpauth URI</strong>
          <textarea rows="3" readonly><?= e((string) ($otpAuthUri ?? '')); ?></textarea>
        </div>
      </div>

      <form method="post" action="/admin/2fa/setup" class="form admin-auth-form">
        <?= csrf_field(); ?>

        <label>Código de 6 digitos do aplicativo autenticador</label>
        <input type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Ex.: 123456" required>

        <button class="btn" type="submit">Ativar 2FA</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
