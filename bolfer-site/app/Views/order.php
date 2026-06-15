<?php
$title = 'Pedido ' . $order['public_id'];
$metaTitle = 'Pedido ' . $order['public_id'] . ' | Bolfer Official';
$metaDescription = 'Acompanhe o andamento do seu pedido na Bolfer Official.';
require __DIR__ . '/partials/header.php';
?>
<?php
$status = (string) $order['status'];
$statusLabels = [
  'created' => 'Pedido criado',
  'pending_payment' => 'Aguardando pagamento',
  'paid_waiting_contact' => 'Preparando entrega',
  'in_delivery' => 'Em entrega',
  'delivered' => 'Entregue',
  'cancelled' => 'Cancelado',
  'rejected' => 'Recusado',
];
$progressSteps = [
  ['label' => 'Pedido criado'],
  ['label' => 'Pagamento'],
  ['label' => 'Preparando/Entrega'],
  ['label' => 'Concluido'],
];
$statusToStep = [
  'created' => 1,
  'pending_payment' => 2,
  'paid_waiting_contact' => 3,
  'in_delivery' => 3,
  'delivered' => 4,
];
$activeStep = $statusToStep[$status] ?? 1;
$finalStep = count($progressSteps);
$isCompleted = $status === 'delivered';
$isCancelled = in_array($status, ['cancelled', 'rejected'], true);
?>

<section class="section alt">
  <div class="container">
    <div class="card">
      <h1>Pedido <?= e($order['public_id']); ?></h1>

      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="order-progress <?= $isCancelled ? 'is-cancelled' : ''; ?>">
        <div class="progress-track"></div>
        <div class="progress-steps">
          <?php foreach ($progressSteps as $index => $step) : ?>
            <?php
              $stepIndex = $index + 1;
              $isDone = $activeStep > $stepIndex || ($isCompleted && $stepIndex === $finalStep);
              $isActive = $activeStep === $stepIndex;
              if ($isCompleted && $stepIndex === $finalStep) {
                $isActive = true;
              }
            ?>
            <div class="progress-step <?= $isDone ? 'done' : ''; ?> <?= $isActive ? 'active' : ''; ?>">
              <span class="progress-dot"><?= $stepIndex; ?></span>
              <span class="progress-label"><?= e($step['label']); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="progress-status">
          Status atual: <strong><?= e($statusLabels[$status] ?? $status); ?></strong>
        </div>
      </div>

      <div class="grid">
        <div>
          <p>Status</p>
          <p><strong><?= e($statusLabels[$status] ?? $status); ?></strong></p>
        </div>
        <div>
          <p>Produto</p>
          <p><strong><?= e($order['product_name']); ?></strong></p>
        </div>
        <div>
          <p>Quantidade</p>
          <p><strong><?= (int) $order['quantity']; ?></strong></p>
        </div>
        <div>
          <p>Total</p>
          <p><strong>R$ <?= number_format((float) $order['total_amount_snapshot'], 2, ',', '.'); ?></strong></p>
        </div>
        <div>
          <p>Nick</p>
          <p><strong><?= e($order['in_game_nick']); ?></strong></p>
        </div>
      </div>

      <?php if ($order['status'] === 'pending_payment') : ?>
        <div class="contact-actions" style="margin-top:24px;">
          <a class="btn" href="/pedido/<?= e($order['public_id']); ?>/continuar">Continuar pagamento</a>
          <a class="btn-ghost" href="/pedido">Consultar outro pedido</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($order['status'] === 'paid_waiting_contact') : ?>
  <section class="section">
    <div class="container">
      <div class="card">
        <h2>Contato para entrega</h2>
        <p>Fale com o entregador antes da entrega:</p>
        <div class="contact-actions">
          <?php if (!empty($whatsapp)) : ?>
            <a class="btn" href="<?= e($whatsapp); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
          <?php endif; ?>
          <?php if (!empty($discord)) : ?>
            <a class="btn-ghost" href="<?= e($discord); ?>" target="_blank" rel="noopener noreferrer">Discord</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>

