<?php
$title = 'Consultar pedido';
$metaTitle = 'Consultar pedido | Bolfer Official';
$metaDescription = 'Acompanhe o status do seu pedido na Bolfer Official usando o código de acompanhamento.';
require __DIR__ . '/partials/header.php';
$captcha = captcha_challenge('order-lookup');
?>

<section class="section alt">
  <div class="container">
    <div class="card">
      <h1>Consultar pedido</h1>
      <p>Digite o código do pedido para acompanhar o status.</p>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/pedido" class="form">
        <?= csrf_field(); ?>
        <label>Código do pedido</label>
        <input type="text" name="public_id" required>

        <input type="hidden" name="captcha_id" value="<?= e((string) ($captcha['id'] ?? '')); ?>">
        <label><?= e((string) ($captcha['question'] ?? 'Quanto e 2 + 2?')); ?></label>
        <input type="text" name="captcha_answer" inputmode="numeric" autocomplete="off" required>

        <button class="btn" type="submit">Consultar</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
