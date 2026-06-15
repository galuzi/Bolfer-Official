<?php
$title = $title ?? 'Status';
$metaTitle = ($title ?? 'Status') . ' | Bolfer Official';
$metaDescription = $message ?? 'Status do pagamento na Bolfer Official.';
require __DIR__ . '/partials/header.php';
?>

<section class="section alt">
  <div class="container">
    <div class="card">
      <h1><?= e($title); ?></h1>
      <p><?= e($message ?? ''); ?></p>
      <?php if (!empty($publicId)) : ?>
        <div class="order-code">
          Código do pedido: <strong><?= e($publicId); ?></strong>
        </div>
        <p><a class="btn-ghost" href="/pedido/<?= e($publicId); ?>">Acompanhar pedido</a></p>
      <?php endif; ?>
      <p><a class="btn" href="/">Voltar para loja</a></p>
    </div>
  </div>
</section>

<?php if (!empty($gaPurchasePayload)) : ?>
<script>
(() => {
  if (typeof window.bolferTrack !== 'function') {
    return;
  }

  const payload = <?= json_encode($gaPurchasePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const transactionId = payload && payload.transaction_id ? String(payload.transaction_id) : '';
  if (!transactionId) {
    return;
  }

  const storageKey = 'bolfer_ga4_purchase_' + transactionId;
  if (window.localStorage && localStorage.getItem(storageKey) === '1') {
    return;
  }

  window.bolferTrack('purchase', payload);

  if (window.localStorage) {
    localStorage.setItem(storageKey, '1');
  }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
