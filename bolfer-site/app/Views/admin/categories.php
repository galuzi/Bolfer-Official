<?php
$title = 'Categorias'; require __DIR__ . '/partials/admin_header.php'; ?>
<?php
$editingCategory = null;
if (!empty($editId)) {
  foreach ($categories as $categoryItem) {
    if ((int) $categoryItem['id'] === (int) $editId) {
      $editingCategory = $categoryItem;
      break;
    }
  }
}
?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Categorias</h1>
        <p class="admin-sub">Organize itens e contas no catalogo.</p>
      </div>
    </div>

    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')) : ?>
      <div class="alert error"><?= e($msg); ?></div>
    <?php endif; ?>

    <div class="card admin-block">
      <form method="post" action="/admin/categories" class="form">
        <?= csrf_field(); ?>
        <input type="hidden" name="action" value="<?= $editingCategory ? 'update' : 'create'; ?>">
        <?php if ($editingCategory) : ?>
          <input type="hidden" name="id" value="<?= (int) $editingCategory['id']; ?>">
        <?php endif; ?>

        <label>Nome</label>
        <input type="text" name="name" value="<?= e($editingCategory['name'] ?? ''); ?>" required>

        <label>Slug</label>
        <input type="text" name="slug" value="<?= e($editingCategory['slug'] ?? ''); ?>" required>

        <label>Ordem</label>
        <input type="number" name="sort_order" value="<?= e((string) ($editingCategory['sort_order'] ?? 0)); ?>">

        <label>Ativo</label>
        <select name="is_active">
          <option value="1" <?= $editingCategory && (int) $editingCategory['is_active'] === 1 ? 'selected' : ''; ?>>Sim</option>
          <option value="0" <?= $editingCategory && (int) $editingCategory['is_active'] === 0 ? 'selected' : ''; ?>>Não</option>
        </select>

        <div class="actions">
          <button class="btn" type="submit"><?= $editingCategory ? 'Atualizar' : 'Salvar'; ?></button>
          <?php if ($editingCategory) : ?>
            <a class="btn-ghost" href="/admin/categories">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card admin-block" style="margin-top: 24px;">
      <div class="table-wrap">
        <table class="table admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Slug</th>
              <th>Ordem</th>
              <th>Ativo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $category) : ?>
              <tr>
                <td><?= (int) $category['id']; ?></td>
                <td><?= e($category['name']); ?></td>
                <td><?= e($category['slug']); ?></td>
                <td><?= (int) $category['sort_order']; ?></td>
                <td><?= (int) $category['is_active'] === 1 ? 'Sim' : 'Não'; ?></td>
                <td>
                  <div class="actions">
                    <a class="btn small" href="/admin/categories?edit=<?= (int) $category['id']; ?>">Editar</a>
                    <form method="post" action="/admin/categories">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $category['id']; ?>">
                      <button class="btn danger small" type="submit">Excluir</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
