<?php
$title = 'Cadastro Admin';
$bodyClass = 'admin-login';
require __DIR__ . '/partials/admin_header.php';
$captcha = captcha_challenge('admin-register');
?>

<section class="section alt">
  <div class="container">
    <div class="card admin-login-card admin-register-card">
      <h1 class="admin-login-title">Criar admin</h1>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/admin/register" class="form admin-auth-form">
        <?= csrf_field(); ?>

        <label>Email</label>
        <input type="email" name="username" required>

        <label>Chave de convite</label>
        <input type="text" name="register_key" value="<?= e((string) ($inviteKey ?? '')); ?>" required>

        <label>Senha</label>
        <input type="password" name="password" required>

        <label>Confirmar senha</label>
        <input type="password" name="password_confirm" required>

        <input type="hidden" name="captcha_id" value="<?= e((string) ($captcha['id'] ?? '')); ?>">
        <label><?= e((string) ($captcha['question'] ?? 'Quanto e 2 + 2?')); ?></label>
        <input type="text" name="captcha_answer" inputmode="numeric" autocomplete="off" required>

        <button class="btn" type="submit">Criar admin</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
