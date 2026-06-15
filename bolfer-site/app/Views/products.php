<?php
$title = 'Catálogo de produtos';
$metaTitle = 'Produtos Bolfer Official | Catálogo digital da comunidade';
$metaDescription = 'Veja os produtos digitais da Bolfer Official, compare categorias, estoque e compra mínima e escolha o item ideal com segurança.';
require __DIR__ . '/partials/header.php';

$categoryMap = [];
$categoryCounts = [];
$totalProducts = count($products ?? []);
$totalCategories = count($categories ?? []);
$availableProducts = 0;
$startingPrice = null;

foreach ($categories ?? [] as $category) {
  $categoryMap[(int) $category['id']] = $category;
}

foreach ($products ?? [] as $product) {
  $categoryId = (int) ($product['category_id'] ?? 0);
  $categoryCounts[$categoryId] = ($categoryCounts[$categoryId] ?? 0) + 1;

  $stockValue = $product['stock'];
  $minimumQuantity = max(1, (int) ($product['minimum_quantity'] ?? 1));
  $hasStock = ($stockValue === null || (int) $stockValue > 0) && ($stockValue === null || (int) $stockValue >= $minimumQuantity);

  if ($hasStock) {
    $availableProducts++;
  }

  $priceValue = (float) ($product['unit_price'] ?? 0);
  if ($startingPrice === null || $priceValue < $startingPrice) {
    $startingPrice = $priceValue;
  }
}
?>

<section class="section catalog-page">
  <div class="container">
    <div class="catalog-hero is-hidden">
      <div class="catalog-hero-main">
        <p class="section-kicker">Catálogo do Bolfer</p>
        <h1 class="catalog-title">Produtos Bolfer</h1>
        <p class="catalog-intro">Navegue pelas categorias, compare os itens disponíveis e encontre com facilidade a melhor opção para você.</p>
        <div class="catalog-hero-badges">
          <span class="catalog-pill">Estoque em tempo real</span>
          <span class="catalog-pill">Entrega digital</span>
          <span class="catalog-pill">Compra segura</span>
        </div>
      </div>
    </div>

    <?php if (!empty($categories)) : ?>
      <div class="catalog-filter-bar">
        <div class="catalog-filter-top">
          <div class="catalog-grid-header catalog-grid-header--compact">
            <p class="section-kicker">Categorias</p>
            <h2>Escolha seu categoria</h2>
            <p>Filtre por jogo e veja primeiro os itens que fazem sentido para a sua compra.</p>
          </div>
          <?php if (!empty($products)) : ?>
            <p class="catalog-status" data-catalog-status>Mostrando <?= (int) $totalProducts; ?> <?= $totalProducts === 1 ? 'produto disponível agora.' : 'produtos disponíveis agora.'; ?></p>
          <?php endif; ?>
        </div>
        <div class="catalog-filters" role="list" aria-label="Filtrar produtos por categoria">
          <button class="filter-pill is-active" type="button" data-filter="all" data-filter-label="todos os produtos">
            <span>Todos</span>
            <strong><?= (int) $totalProducts; ?></strong>
          </button>
          <?php foreach ($categories as $category) : ?>
            <button class="filter-pill" type="button" data-filter="<?= e($category['slug']); ?>" data-filter-label="<?= e($category['name']); ?>">
              <span><?= e($category['name']); ?></span>
              <strong><?= (int) ($categoryCounts[(int) $category['id']] ?? 0); ?></strong>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (empty($products)) : ?>
      <div class="card catalog-empty-state">
        <p class="section-kicker">Sem itens no momento</p>
        <h2>Nenhum produto disponível agora</h2>
        <p>Assim que novos itens entrarem no catálogo, eles vão aparecer aqui com estoque, categoria e botão de compra prontos.</p>
      </div>
    <?php else : ?>
      <div class="catalog-grid-top<?= $totalProducts === 1 ? ' is-single' : ''; ?>">
        <div class="catalog-grid-header catalog-grid-header--compact">
          <p class="section-kicker">Produtos</p>
          <h2>Itens disponíveis</h2>
          <p>Preço, estoque e compra mínima aparecem logo de cara para deixar a escolha mais leve.</p>
        </div>
        <div class="catalog-grid-actions<?= $totalProducts === 1 ? ' is-single' : ''; ?>">
          <a class="catalog-toolbar-link" href="/#contato">Precisa de ajuda para comprar?</a>
        </div>
      </div>

      <div class="catalog-grid<?= $totalProducts === 1 ? ' is-single' : ''; ?>">
        <?php foreach ($products as $product) : ?>
          <?php
            $stockValue = $product['stock'];
            $stockLabel = $stockValue === null ? 'Ilimitado' : (string) (int) $stockValue;
            $minimumQuantity = max(1, (int) ($product['minimum_quantity'] ?? 1));
            $hasStock = ($stockValue === null || (int) $stockValue > 0) && ($stockValue === null || (int) $stockValue >= $minimumQuantity);
            $category = $categoryMap[(int) $product['category_id']] ?? null;
            $categoryName = $category['name'] ?? 'Sem categoria';
            $categorySlug = $category['slug'] ?? 'sem-categoria';
            $typeKey = (string) ($product['product_type'] ?? 'item');
            $typeLabel = $typeKey === 'conta' ? 'Conta' : 'Item';
            $serverLabel = trim((string) ($product['server_label'] ?? ''));
            if ($serverLabel === '') {
              $serverLabel = 'Entrega digital combinada';
            }

            if (!$hasStock) {
              $stockStateLabel = 'Esgotado';
              $stockStateClass = 'is-sold-out';
            } elseif ($stockValue !== null && (int) $stockValue <= max(5, $minimumQuantity)) {
              $stockStateLabel = 'Últimas unidades';
              $stockStateClass = 'is-low';
            } else {
              $stockStateLabel = 'Disponível';
              $stockStateClass = 'is-ready';
            }
          ?>
          <article class="catalog-card" data-category="<?= e($categorySlug); ?>">
            <div class="catalog-card-top">
              <div class="catalog-card-content">
                <div class="catalog-card-head">
                  <div class="catalog-card-badges">
                    <span class="catalog-card-chip"><?= e($categoryName); ?></span>
                    <span class="catalog-card-chip is-muted"><?= e($typeLabel); ?></span>
                  </div>
                  <span class="catalog-stock-pill <?= e($stockStateClass); ?>"><?= e($stockStateLabel); ?></span>
                </div>

                <div class="catalog-card-main">
                  <h3><?= e($product['name']); ?></h3>
                  <p><?= e($serverLabel); ?></p>
                </div>

                <div class="catalog-card-stats">
                  <div class="catalog-card-stat">
                    <span>Estoque</span>
                    <strong><?= e($stockLabel); ?></strong>
                  </div>
                  <div class="catalog-card-stat">
                    <span>Compra mínima</span>
                    <strong><?= (int) $minimumQuantity; ?> <?= $minimumQuantity === 1 ? 'unidade' : 'unidades'; ?></strong>
                  </div>
                  <div class="catalog-card-stat">
                    <span>Entrega</span>
                    <strong>Digital</strong>
                  </div>
                </div>
              </div>

              <div class="catalog-card-buy">
                <div class="catalog-card-price">
                  <span>Valor unitário</span>
                  <strong>R$ <?= number_format((float) $product['unit_price'], 2, ',', '.'); ?></strong>
                </div>

                <?php if ($hasStock) : ?>
                  <a class="btn-buy" href="/produto/<?= e($product['slug']); ?>">Comprar agora</a>
                <?php else : ?>
                  <span class="btn-buy is-disabled" aria-disabled="true">Esgotado</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="card catalog-empty-note is-hidden" data-catalog-empty>
        <p class="section-kicker">Filtro sem resultado</p>
        <h3>Nenhum item nessa categoria</h3>
        <p>Troque o filtro para ver outras opções disponíveis no catálogo.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($products) && !empty($categories)) : ?>
  <script>
  (() => {
    const filterButtons = document.querySelectorAll('.filter-pill[data-filter]');
    const cards = document.querySelectorAll('.catalog-grid .catalog-card');
    const statusLabel = document.querySelector('[data-catalog-status]');
    const emptyState = document.querySelector('[data-catalog-empty]');
    if (!filterButtons.length || !cards.length) return;

    const setActive = (activeBtn) => {
      filterButtons.forEach((btn) => {
        btn.classList.toggle('is-active', btn === activeBtn);
      });
    };

    const updateCatalog = (activeBtn) => {
      const filter = activeBtn.dataset.filter || 'all';
      const filterLabel = activeBtn.dataset.filterLabel || 'todos os produtos';
      let visibleCount = 0;

      cards.forEach((card) => {
        const category = card.dataset.category || '';
        const matches = filter === 'all' || category === filter;
        card.classList.toggle('is-hidden', !matches);
        if (matches) visibleCount += 1;
      });

      if (statusLabel) {
        const productLabel = visibleCount === 1 ? 'produto' : 'produtos';
        statusLabel.textContent = filter === 'all'
          ? `Mostrando ${visibleCount} ${productLabel} disponíveis agora.`
          : `Mostrando ${visibleCount} ${productLabel} em ${filterLabel}.`;
      }

      if (emptyState) {
        emptyState.classList.toggle('is-hidden', visibleCount !== 0);
      }
    };

    filterButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        setActive(btn);
        updateCatalog(btn);
      });
    });

    const initialButton = document.querySelector('.filter-pill.is-active[data-filter]') || filterButtons[0];
    if (initialButton) {
      setActive(initialButton);
      updateCatalog(initialButton);
    }
  })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
