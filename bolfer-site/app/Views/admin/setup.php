<?php
$title = 'Criar Admin Fundador';
$bodyClass = 'admin-login';
require __DIR__ . '/partials/admin_header.php';
$captcha = captcha_challenge('admin-setup');
?>

<section class="section alt">
  <div class="container">
    <div class="card">
      <h1>Admin fundador</h1>
      <p>Crie o primeiro admin para acessar o painel.</p>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/admin/setup" class="form">
        <?= csrf_field(); ?>

        <label>Email</label>
        <input type="email" name="username" required>

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
