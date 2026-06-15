<?php
$title = 'Histórico do mercado';
require __DIR__ . '/../partials/header.php';

$marketCoins = (int) ($marketCoins ?? 0);
$marketTransactions = $marketTransactions ?? [];
$marketTopups = $marketTopups ?? [];
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card market-page market-page--history">
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="market-hero market-hero--history">
        <div class="market-hero-copy">
          <p class="user-kicker">Movimentacao</p>
          <h1>Histórico do mercado em uma aba separada.</h1>
          <p class="user-sub">Aqui ficam todas as entradas e saidas da sua carteira. A tela principal do mercado agora ficou focada em comprar e vender.</p>

          <div class="market-hero-actions">
            <a class="btn-ghost" href="/usuario/mercado">Voltar ao mercado</a>
            <a class="btn-ghost" href="/usuario/inventario">Abrir inventário</a>
          </div>
        </div>

        <aside class="market-wallet-card market-wallet-card--hero">
          <span class="user-status-label">Carteira atual</span>
          <strong><?= $marketCoins; ?> coins</strong>
          <p>Total de registros visiveis: <?= count($marketTransactions); ?>.</p>
          <p>As últimas recargas também aparecem abaixo para consulta rápida.</p>
        </aside>
      </div>

      <div class="market-grid market-grid--history">
        <section class="market-panel market-panel--wide">
          <div class="market-panel-head">
            <div>
              <p class="user-kicker">Movimentacao</p>
              <h2>Histórico completo</h2>
            </div>
            <span class="market-count-chip"><?= count($marketTransactions); ?> movimentacao(oes)</span>
          </div>
          <p class="user-sub">Tudo que entra ou sai da sua carteira aparece aqui: recarga, compra, venda e desbloqueio.</p>

          <?php if ($marketTransactions === []) : ?>
            <div class="market-empty-note">
              <strong>Sem movimentacoes por enquanto.</strong>
              <span>Quando houver uma recarga, compra, venda ou desbloqueio, o registro aparece aqui.</span>
            </div>
          <?php else : ?>
            <div class="market-history-list">
              <?php foreach ($marketTransactions as $transaction) : ?>
                <?php
                  $amount = (int) ($transaction['amount'] ?? 0);
                  $createdAt = !empty($transaction['created_at']) ? date('d/m/Y H:i', strtotime((string) ($transaction['created_at'] ?? ''))) : '-';
                  $transactionType = (string) ($transaction['transaction_type'] ?? 'ajuste');
                  $transactionLabel = match ($transactionType) {
                    'topup_credit' => 'Recarga aprovada',
                    'market_purchase' => 'Compra no mercado',
                    'market_sale' => 'Venda no mercado',
                    'inventory_unlock' => 'Desbloqueio',
                    'admin_credit' => 'Crédito manual',
                    'admin_debit' => 'Débito manual',
                    default => 'Movimentacao interna',
                  };
                ?>
                <article class="market-history-row">
                  <div class="market-history-copy">
                    <span class="market-history-tag <?= $amount >= 0 ? 'is-positive' : 'is-negative'; ?>"><?= e($transactionLabel); ?></span>
                    <strong><?= e((string) ($transaction['note'] ?? 'Movimentacao')); ?></strong>
                    <span><?= e($createdAt); ?></span>
                  </div>
                  <div class="market-history-amount <?= $amount >= 0 ? 'is-positive' : 'is-negative'; ?>">
                    <?= $amount >= 0 ? '+' : ''; ?><?= $amount; ?> coins
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="market-panel market-panel--feature">
          <div class="market-panel-head">
            <div>
              <p class="user-kicker">Recargas</p>
              <h2>Ultimos pedidos</h2>
            </div>
            <span class="market-count-chip"><?= count($marketTopups); ?> recarga(s)</span>
          </div>
          <p class="user-sub">Esse bloco ajuda a conferir as últimas recargas feitas dentro do mercado.</p>

          <div class="market-mini-list">
            <?php foreach ($marketTopups as $topup) : ?>
              <?php
                $topupStatus = match ((string) ($topup['status'] ?? 'pending')) {
                  'paid' => 'Pago',
                  'cancelled' => 'Cancelado',
                  default => 'Pendente',
                };
              ?>
              <article class="market-mini-item">
                <strong><?= (int) ($topup['coins_amount'] ?? 0); ?> coins</strong>
                <span>R$ <?= e(number_format((float) ($topup['amount_brl'] ?? 0), 2, ',', '.')); ?> - <?= e($topupStatus); ?></span>
              </article>
            <?php endforeach; ?>
            <?php if ($marketTopups === []) : ?>
              <div class="market-empty-note">
                <strong>Nenhuma recarga registrada ainda.</strong>
                <span>Quando você comprar coins, os pedidos aparecem aqui.</span>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
