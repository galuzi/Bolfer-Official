<?php
$title = 'Pedidos'; require __DIR__ . '/partials/admin_header.php'; ?>
<?php
$statusValue = (string) ($filters['status'] ?? '');
$productValue = (string) ($filters['product_id'] ?? '');
$publicIdValue = (string) ($filters['public_id'] ?? '');
?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Pedidos</h1>
        <p class="admin-sub">Acompanhe status, reembolsos e contatos.</p>
      </div>
    </div>

    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')) : ?>
      <div class="alert error"><?= e($msg); ?></div>
    <?php endif; ?>

    <div class="card admin-block">
      <form method="get" action="/admin/orders" class="admin-filters">
        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="">Todos</option>
            <?php foreach ($statuses as $status) : ?>
              <option value="<?= e($status); ?>" <?= $statusValue === $status ? 'selected' : ''; ?>>
                <?= e($statusLabels[$status] ?? $status); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Produto</label>
          <select name="product_id">
            <option value="">Todos</option>
            <?php foreach ($products as $product) : ?>
              <option value="<?= (int) $product['id']; ?>" <?= $productValue === (string) $product['id'] ? 'selected' : ''; ?>>
                <?= e($product['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Pedido</label>
          <input type="text" name="public_id" value="<?= e($publicIdValue); ?>" placeholder="Código">
        </div>

        <div class="admin-filter-actions">
          <button class="btn" type="submit">Filtrar</button>
          <a class="btn-ghost" href="/admin/orders?clear=1">Limpar</a>
        </div>
      </form>

      <form method="post" action="/admin/orders" class="admin-danger">
        <?= csrf_field(); ?>
        <input type="hidden" name="action" value="delete_all">
        <button class="btn danger" type="submit" <?= admin_is_full() ? '' : 'disabled'; ?>>Apagar todos os pedidos</button>
      </form>
      <?php if (!admin_is_full()) : ?>
        <p class="admin-sub" style="margin-top: 8px;">Somente admins principais podem apagar tudo ou solicitar reembolsos.</p>
      <?php endif; ?>
    </div>

    <div class="card admin-block" style="margin-top: 24px;">
      <div class="table-wrap">
        <table class="table admin-table">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Status</th>
              <th>Produto</th>
              <th>Total</th>
              <th>Contato</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order) : ?>
              <?php $canRefund = in_array($order['status'], ['paid_waiting_contact', 'in_delivery', 'delivered'], true); ?>
              <tr>
                <td><?= e($order['public_id']); ?></td>
                <td><?= e($statusLabels[$order['status']] ?? $order['status']); ?></td>
                <td><?= e($order['product_name']); ?></td>
                <td>R$ <?= number_format((float) $order['total_amount_snapshot'], 2, ',', '.'); ?></td>
                <td><?= e((string) $order['contact_channel']); ?> <?= e((string) $order['contact_value']); ?></td>
                <td>
                  <div class="actions">
                    <form method="post" action="/admin/orders">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                      <select name="status">
                        <?php foreach ($statuses as $status) : ?>
                          <option value="<?= e($status); ?>" <?= $order['status'] === $status ? 'selected' : ''; ?>>
                            <?= e($statusLabels[$status] ?? $status); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn small" type="submit">Atualizar</button>
                    </form>
                    <?php if ($canRefund) : ?>
                      <form method="post" action="/admin/orders" onsubmit="return confirm('Confirmar reembolso deste pedido?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="refund_payment">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                        <button class="btn small danger" type="submit" <?= admin_is_full() ? '' : 'disabled'; ?>>Reembolsar</button>
                      </form>
                    <?php endif; ?>
                    <a class="btn-ghost" href="/admin/orders?view=<?= (int) $order['id']; ?>">Ver</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($viewOrderId)) : ?>
      <div class="card admin-block" style="margin-top: 24px;">
        <h2>Contato do cliente</h2>
        <?php if (empty($viewOrder)) : ?>
          <p>Pedido não encontrado.</p>
        <?php else : ?>
          <div class="admin-contact">
            <div>
              <strong>Nick</strong>
              <p><?= e((string) ($viewOrder['in_game_nick'] ?? 'Não informado')); ?></p>
            </div>
            <div>
              <strong>Contato</strong>
              <p><?= e((string) ($viewOrder['contact_channel'] ?? '')); ?> <?= e((string) ($viewOrder['contact_value'] ?? 'Não informado')); ?></p>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="card admin-block" style="margin-top: 24px;">
        <h2>Logs do pedido</h2>
        <?php if (empty($logs)) : ?>
          <p>Sem logs.</p>
        <?php else : ?>
          <ul>
            <?php foreach ($logs as $log) : ?>
              <li><?= e($log['created_at']); ?> - <?= e($log['message']); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
