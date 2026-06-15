<?php
$title = 'Convites'; require __DIR__ . '/partials/admin_header.php'; ?>
<?php
$searchValue = (string) ($filters['search'] ?? '');
$statusValue = (string) ($filters['status'] ?? 'all');
?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Convites</h1>
        <p class="admin-sub">Gerencie chaves de acesso para novos admins.</p>
      </div>
    </div>

    <div class="card admin-block">
      <div class="admin-header">
        <h1>Chaves de convite</h1>
        <?php if ($canManage ?? false) : ?>
          <form method="post" action="/admin/invites" class="form inline">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="generate">
            <input type="hidden" name="search" value="<?= e($searchValue); ?>">
            <input type="hidden" name="status" value="<?= e($statusValue); ?>">
            <div class="field">
              <label>Quantidade</label>
              <select name="quantity">
                <option value="1">1 chave</option>
                <option value="2">2 chaves</option>
                <option value="3">3 chaves</option>
                <option value="5">5 chaves</option>
              </select>
            </div>
            <button class="btn" type="submit">Gerar chaves</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="get" action="/admin/invites" class="admin-filters">
        <div class="field">
          <label>Buscar</label>
          <input type="text" name="search" value="<?= e($searchValue); ?>" placeholder="Chave ou admin">
        </div>

        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="all" <?= $statusValue === 'all' ? 'selected' : ''; ?>>Todas</option>
            <option value="available" <?= $statusValue === 'available' ? 'selected' : ''; ?>>Disponiveis</option>
            <option value="used" <?= $statusValue === 'used' ? 'selected' : ''; ?>>Usadas</option>
          </select>
        </div>

        <div class="admin-filter-actions">
          <button class="btn" type="submit">Filtrar</button>
          <a class="btn-ghost" href="/admin/invites?clear=1">Limpar</a>
        </div>
      </form>

      <div class="table-wrap">
        <table class="table admin-table admin-invites-table">
          <thead>
            <tr>
              <th>Chave</th>
              <th>Criada por</th>
              <th>Usada por</th>
              <th>Data</th>
              <th>Uso</th>
              <?php if ($canManage ?? false) : ?>
                <th>Acoes</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($keys)) : ?>
              <tr>
                <td colspan="<?= ($canManage ?? false) ? '6' : '5'; ?>">Nenhuma chave encontrada para os filtros atuais.</td>
              </tr>
            <?php else : ?>
              <?php foreach ($keys as $key) : ?>
                <tr>
                  <td><?= e($key['invite_key']); ?></td>
                  <td><?= e($key['created_by_email'] ?? '-'); ?></td>
                  <td><?= e($key['used_by_email'] ?? '-'); ?></td>
                  <td><?= e($key['created_at']); ?></td>
                  <td><?= !empty($key['used_at']) ? 'Usada em ' . e($key['used_at']) : 'Disponivel'; ?></td>
                  <?php if ($canManage ?? false) : ?>
                    <td>
                      <div class="actions">
                        <form method="post" action="/admin/invites" onsubmit="return confirm('Excluir esta chave?');">
                          <?= csrf_field(); ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="invite_id" value="<?= (int) $key['id']; ?>">
                          <input type="hidden" name="search" value="<?= e($searchValue); ?>">
                          <input type="hidden" name="status" value="<?= e($statusValue); ?>">
                          <button class="btn small danger" type="submit">Excluir</button>
                        </form>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
