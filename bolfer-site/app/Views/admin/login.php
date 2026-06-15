<?php
$title = 'Login Admin';
$bodyClass = 'admin-login';
require __DIR__ . '/partials/admin_header.php';
$captcha = captcha_challenge('admin-login');
?>

<section class="section alt">
  <div class="container">
    <div class="card admin-login-card">
      <h1 class="admin-login-title">Login Admin</h1>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/admin/login" class="form admin-auth-form">
        <?= csrf_field(); ?>
        <label>Email</label>
        <input type="email" name="username" required>

        <label>Senha</label>
        <input type="password" name="password" required>

        <input type="hidden" name="captcha_id" value="<?= e((string) ($captcha['id'] ?? '')); ?>">
        <label><?= e((string) ($captcha['question'] ?? 'Quanto e 2 + 2?')); ?></label>
        <input type="text" name="captcha_answer" inputmode="numeric" autocomplete="off" required>

        <button class="btn" type="submit">Entrar</button>
      </form>

    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
