<?php
$title = 'Produtos'; require __DIR__ . '/partials/admin_header.php'; ?>
<?php
$editingProduct = null;
if (!empty($editId)) {
  foreach ($products as $productItem) {
    if ((int) $productItem['id'] === (int) $editId) {
      $editingProduct = $productItem;
      break;
    }
  }
}
$productTypeValue = $editingProduct ? (string) ($editingProduct['product_type'] ?? 'item') : 'item';
$minimumQuantityValue = $editingProduct ? max(1, (int) ($editingProduct['minimum_quantity'] ?? 1)) : 1;
$productDescriptionValue = $editingProduct ? (string) ($editingProduct['product_description'] ?? '') : '';
$accountInfoValue = $editingProduct ? (string) ($editingProduct['account_info'] ?? '') : '';
$accountImagesValue = [];
if ($editingProduct && !empty($editingProduct['account_images'])) {
  $decodedImages = json_decode((string) $editingProduct['account_images'], true);
  if (is_array($decodedImages)) {
    $accountImagesValue = array_values(array_filter($decodedImages, static fn($value) => is_string($value) && trim($value) !== ''));
  }
}
$descriptionValue = $editingProduct ? (string) $editingProduct['description'] : $defaultDescription;
$notesValue = $editingProduct ? (string) $editingProduct['notes'] : $defaultNotes;
?>

<section class="section alt">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Produtos</h1>
        <p class="admin-sub">Gerencie itens e contas do seu catalogo.</p>
      </div>
    </div>

    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')) : ?>
      <div class="alert error"><?= e($msg); ?></div>
    <?php endif; ?>

    <div class="card admin-block">
      <form method="post" action="/admin/products" class="form" enctype="multipart/form-data">
        <?= csrf_field(); ?>
        <input type="hidden" name="action" value="<?= $editingProduct ? 'update' : 'create'; ?>">
        <?php if ($editingProduct) : ?>
          <input type="hidden" name="id" value="<?= (int) $editingProduct['id']; ?>">
        <?php endif; ?>

        <div class="admin-form-grid">
          <div class="field">
            <label>Nome</label>
            <input type="text" name="name" value="<?= e($editingProduct['name'] ?? ''); ?>" required>
          </div>

          <div class="field">
            <label>Slug</label>
            <input type="text" name="slug" value="<?= e($editingProduct['slug'] ?? ''); ?>" required>
          </div>

          <div class="field">
            <label>Tipo</label>
            <select name="product_type">
              <option value="item" <?= $productTypeValue === 'item' ? 'selected' : ''; ?>>Item</option>
              <option value="conta" <?= $productTypeValue === 'conta' ? 'selected' : ''; ?>>Conta</option>
            </select>
          </div>

          <div class="field">
            <label>Categoria</label>
            <select name="category_id" required>
              <?php foreach ($categories as $category) : ?>
                <option value="<?= (int) $category['id']; ?>" <?= $editingProduct && (int) $editingProduct['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
                  <?= e($category['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Preco unitario</label>
            <input type="number" step="0.01" name="unit_price" value="<?= e((string) ($editingProduct['unit_price'] ?? '')); ?>" required>
          </div>

          <div class="field">
            <label>Estoque</label>
            <input type="number" name="stock" value="<?= e((string) ($editingProduct['stock'] ?? '')); ?>" placeholder="Deixe vazio para ilimitado">
          </div>

          <div class="field">
            <label>Compra mínima</label>
            <input type="number" name="minimum_quantity" min="1" value="<?= (int) $minimumQuantityValue; ?>" required>
          </div>

          <div class="field">
            <label>ETA</label>
            <input type="text" name="delivery_eta" value="<?= e($editingProduct['delivery_eta'] ?? '5min-1h'); ?>" required>
          </div>

          <div class="field">
            <label>Ativo</label>
            <select name="is_active">
              <option value="1" <?= $editingProduct && (int) $editingProduct['is_active'] === 1 ? 'selected' : ''; ?>>Sim</option>
              <option value="0" <?= $editingProduct && (int) $editingProduct['is_active'] === 0 ? 'selected' : ''; ?>>Não</option>
            </select>
          </div>
        </div>

        <div class="admin-form-divider"></div>

        <div class="admin-form-section">
          <label>Descricao do produto</label>
          <textarea name="product_description" rows="6"><?= e($productDescriptionValue); ?></textarea>
        </div>

        <div class="admin-account-fields <?= $productTypeValue === 'conta' ? '' : 'is-hidden'; ?>" data-account-fields>
          <div class="admin-form-divider"></div>

          <div class="admin-form-section">
            <label>Informacoes da conta</label>
            <textarea name="account_info" rows="8" placeholder="Exemplo:\nLevel 140\nServidor Omega\nSkin exclusiva\nItens raros incluidos"><?= e($accountInfoValue); ?></textarea>
            <small class="admin-hint">Use uma linha para cada informacao importante da conta.</small>
          </div>

          <div class="admin-form-section">
            <label>Imagens da conta</label>
            <input type="file" name="account_images[]" accept=".webp,image/webp" multiple>
            <small class="admin-hint">Envie ate 8 imagens. O sistema aceita apenas arquivos WEBP.</small>
          </div>

          <?php if (!empty($accountImagesValue)) : ?>
            <div class="admin-form-section">
              <label>Imagens atuais</label>
              <div class="account-media-grid">
                <?php foreach ($accountImagesValue as $imagePath) : ?>
                  <label class="account-media-card">
                    <img src="<?= e($imagePath); ?>" alt="Imagem da conta" loading="lazy">
                    <span class="account-media-caption">
                      <input type="checkbox" name="remove_account_images[]" value="<?= e($imagePath); ?>">
                      Remover esta imagem
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="admin-form-section">
          <label>Descricao de entrega</label>
          <textarea name="description" rows="8"><?= e($descriptionValue); ?></textarea>
        </div>

        <div class="admin-form-section">
          <label>Observacoes</label>
          <textarea name="notes" rows="10"><?= e($notesValue); ?></textarea>
        </div>

        <div class="actions">
          <button class="btn" type="submit"><?= $editingProduct ? 'Atualizar' : 'Salvar'; ?></button>
          <?php if ($editingProduct) : ?>
            <a class="btn-ghost" href="/admin/products">Cancelar</a>
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
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Preço</th>
            <th>Estoque</th>
            <th>Compra mínima</th>
            <th>Ativo</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product) : ?>
            <?php
              $typeKey = (string) ($product['product_type'] ?? 'item');
              $typeLabel = $typeKey === 'conta' ? 'Conta' : 'Item';
            ?>
            <tr>
              <td><?= (int) $product['id']; ?></td>
              <td><?= e($product['name']); ?></td>
              <td><?= e($typeLabel); ?></td>
              <td><?= e((string) $product['category_name']); ?></td>
              <td>R$ <?= number_format((float) $product['unit_price'], 2, ',', '.'); ?></td>
              <td><?= $product['stock'] === null ? 'ilimitado' : (int) $product['stock']; ?></td>
              <td><?= max(1, (int) ($product['minimum_quantity'] ?? 1)); ?></td>
              <td><?= (int) $product['is_active'] === 1 ? 'Sim' : 'Não'; ?></td>
              <td>
                <div class="actions">
                  <a class="btn small" href="/admin/products?edit=<?= (int) $product['id']; ?>">Editar</a>
                  <form method="post" action="/admin/products">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $product['id']; ?>">
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

<script>
(() => {
  const typeField = document.querySelector('select[name="product_type"]');
  const accountFields = document.querySelector('[data-account-fields]');
  if (!typeField || !accountFields) return;

  const syncVisibility = () => {
    accountFields.classList.toggle('is-hidden', typeField.value !== 'conta');
  };

  typeField.addEventListener('change', syncVisibility);
  syncVisibility();
})();
</script>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
