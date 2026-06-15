<?php
$title = 'Comprar no mercado';
require __DIR__ . '/../partials/header.php';

$marketCoins = (int) ($marketCoins ?? 0);
$marketKeys = (int) ($marketKeys ?? 0);
$marketListings = $marketListings ?? [];
$inventoryTypeOptions = $inventoryTypeOptions ?? [];
$marketFilterTypeOptions = $marketFilterTypeOptions ?? [];
$marketBrowseFilters = $marketBrowseFilters ?? [];
$activeMarketCount = count($marketListings);
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card market-page market-page--buy">
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

        <div class="market-hero market-hero--history">
        <div class="market-hero-copy">
          <p class="user-kicker">Mercado aberto</p>
          <h1>Compre jogos e chaves em uma aba feita só para compra.</h1>
          <p class="user-sub">Coins compram as ofertas do mercado. Chaves vao para o seu inventário e jogos bloqueados podem ser abertos depois com as chaves que você juntar.</p>

          <div class="market-hero-actions">
            <a class="btn-ghost" href="/usuario/mercado">Voltar ao mercado</a>
            <a class="btn-ghost" href="/usuario/mercado/historico">Ver histórico</a>
          </div>
        </div>

        <aside class="market-wallet-card market-wallet-card--hero">
          <span class="user-status-label">Carteira atual</span>
          <strong><?= $marketCoins; ?> coins</strong>
          <p>Chaves no inventário: <?= $marketKeys; ?>.</p>
          <p>Ofertas visiveis agora: <?= $activeMarketCount; ?>.</p>
          <p>Use os filtros abaixo para achar mais rápido o item que você quer comprar.</p>
        </aside>
      </div>

      <section class="market-panel market-panel--wide">
        <div class="market-panel-head">
          <div>
            <p class="user-kicker">Filtros de compra</p>
            <h2>Refine as ofertas</h2>
          </div>
          <span class="market-count-chip"><?= $activeMarketCount; ?> oferta(s) encontrada(s)</span>
        </div>
        <p class="user-sub">Preencha só o que fizer sentido. Se quiser voltar ao normal, limpe os filtros e veja tudo de novo.</p>

        <form method="get" action="/usuario/mercado/comprar" class="market-filter-form">
          <div class="market-filter-grid">
            <div class="field">
              <label>Buscar por nome</label>
              <input type="text" name="q" value="<?= e((string) ($marketBrowseFilters['q'] ?? '')); ?>" placeholder="Ex: jogo, chave, dragon ball">
            </div>

            <div class="field">
              <label>Tipo do item</label>
              <select name="type">
                <option value="">Todos os tipos</option>
                <?php foreach ($marketFilterTypeOptions as $typeKey => $typeLabel) : ?>
                  <option value="<?= e($typeKey); ?>" <?= ($marketBrowseFilters['item_type'] ?? '') === $typeKey ? 'selected' : ''; ?>>
                    <?= e($typeLabel); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label>Preço máximo em coins</label>
              <input type="number" name="price_max" min="0" value="<?= e((string) ($marketBrowseFilters['price_max'] ?? '')); ?>" placeholder="Ex: 500">
            </div>

            <div class="field">
              <label>Ordenar por</label>
              <select name="sort">
                <option value="recent" <?= ($marketBrowseFilters['sort'] ?? 'recent') === 'recent' ? 'selected' : ''; ?>>Mais recentes</option>
                <option value="price_asc" <?= ($marketBrowseFilters['sort'] ?? '') === 'price_asc' ? 'selected' : ''; ?>>Menor preço</option>
                <option value="price_desc" <?= ($marketBrowseFilters['sort'] ?? '') === 'price_desc' ? 'selected' : ''; ?>>Maior preço</option>
                <option value="unlock_asc" <?= ($marketBrowseFilters['sort'] ?? '') === 'unlock_asc' ? 'selected' : ''; ?>>Menor desbloqueio</option>
              </select>
            </div>
          </div>

          <div class="market-panel-actions">
            <button class="btn" type="submit">Aplicar filtros</button>
            <a class="btn-ghost" href="/usuario/mercado/comprar">Limpar filtros</a>
          </div>
        </form>
      </section>

      <section class="market-panel market-panel--wide">
        <div class="market-panel-head">
          <div>
            <p class="user-kicker">Ofertas abertas</p>
            <h2>Itens disponiveis para comprar</h2>
          </div>
          <span class="market-count-chip"><?= $activeMarketCount; ?> oferta(s) no ar</span>
        </div>
        <p class="user-sub">Quando você compra, o item vai para o seu inventário e o vendedor recebe as coins automaticamente.</p>

        <?php if ($marketListings === []) : ?>
          <div class="market-empty-note">
            <strong>Nenhuma oferta bateu com os filtros.</strong>
            <span>Tente limpar os filtros ou aguarde novas ofertas entrarem no mercado.</span>
          </div>
        <?php else : ?>
          <div class="market-listings-grid">
            <?php foreach ($marketListings as $listing) : ?>
              <?php
                $listingType = (string) ($listing['item_type'] ?? 'outro');
                $isOwnListing = !empty($listing['is_own_listing']);
                $listingQuantity = max(1, (int) ($listing['quantity'] ?? 1));
                $unitPriceCoins = (int) ($listing['price_coins'] ?? 0);
                $hasEnoughCoins = $marketCoins >= $unitPriceCoins;
                $usesKeysToUnlock = \App\Repositories\UserInventoryRepository::usesKeyUnlock($listingType);
                $isKeyType = \App\Repositories\UserInventoryRepository::isKeyType($listingType);
              ?>
              <article class="market-listing-card market-listing-card--<?= e($listingType); ?>">
                <div class="market-listing-top">
                  <span class="inventory-type"><?= e($inventoryTypeOptions[$listingType] ?? 'Outro'); ?></span>
                  <span class="market-listing-price">
                    <?= $unitPriceCoins; ?> coins<?= $isKeyType ? ' / un' : ''; ?>
                  </span>
                </div>

                <h3><?= e((string) ($listing['item_name'] ?? 'Item')); ?></h3>
                <p><?= e((string) ($listing['description'] ?? 'Oferta criada por um usuário do mercado interno.')); ?></p>

                <div class="market-detail-list">
                  <div class="market-detail-row">
                    <span>Vendedor</span>
                    <strong>
                      <?= e((string) ($listing['seller_username'] ?? 'Usuário')); ?>
                      <?= $isOwnListing ? ' (você)' : ''; ?>
                    </strong>
                  </div>
                  <div class="market-detail-row">
                    <span>Quantidade</span>
                    <strong><?= $listingQuantity; ?></strong>
                  </div>
                  <div class="market-detail-row">
                    <span>Desbloqueio depois da compra</span>
                    <strong>
                      <?php if ($isKeyType) : ?>
                        Não precisa
                      <?php elseif ($usesKeysToUnlock) : ?>
                        <?= max(0, (int) ($listing['unlock_cost'] ?? 0)); ?> chave(s)
                      <?php else : ?>
                        <?= max(0, (int) ($listing['unlock_cost'] ?? 0)); ?> coins
                      <?php endif; ?>
                    </strong>
                  </div>
                </div>

                <form method="post" action="/usuario/mercado/ofertas/comprar" class="market-buy-form" data-market-buy-form data-is-key="<?= $isKeyType ? '1' : '0'; ?>" data-unit-price="<?= $unitPriceCoins; ?>" data-wallet-coins="<?= $marketCoins; ?>" data-max-quantity="<?= $listingQuantity; ?>" data-is-own="<?= $isOwnListing ? '1' : '0'; ?>">
                  <?= csrf_field(); ?>
                  <input type="hidden" name="listing_id" value="<?= (int) ($listing['id'] ?? 0); ?>">
                  <input type="hidden" name="return_to" value="/usuario/mercado/comprar">
                  <?php if ($isKeyType) : ?>
                    <div class="field market-buy-quantity-field">
                      <label>Quantidade para comprar</label>
                      <input type="number" name="quantity" min="1" max="<?= $listingQuantity; ?>" value="1" data-market-buy-quantity-input>
                      <small class="market-field-hint">O total em coins muda automaticamente conforme a quantidade escolhida.</small>
                    </div>
                    <div class="market-buy-total-box">
                      <span>Total da compra</span>
                      <strong data-market-buy-total><?= $unitPriceCoins; ?> coins</strong>
                    </div>
                  <?php else : ?>
                    <input type="hidden" name="quantity" value="1">
                  <?php endif; ?>
                  <button class="btn small" type="submit" data-market-buy-button <?= (!$isOwnListing && $hasEnoughCoins) ? '' : 'disabled'; ?>>
                    <?php if ($isOwnListing) : ?>
                      Sua oferta no mercado
                    <?php elseif ($hasEnoughCoins) : ?>
                      <?= $isKeyType ? 'Comprar agora' : 'Comprar item agora'; ?>
                    <?php else : ?>
                      Coins insuficientes
                    <?php endif; ?>
                  </button>
                </form>

                <?php if ($isOwnListing) : ?>
                  <p class="market-card-note">Esse anúncio já está público no mercado. Outro usuário pode comprar normalmente.</p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</section>

<script>
(() => {
  const buyForms = document.querySelectorAll('[data-market-buy-form]');
  if (!buyForms.length) {
    return;
  }

  buyForms.forEach((form) => {
    const isKeyType = form.getAttribute('data-is-key') === '1';
    const isOwnListing = form.getAttribute('data-is-own') === '1';
    const unitPrice = Math.max(0, Number(form.getAttribute('data-unit-price') || '0'));
    const walletCoins = Math.max(0, Number(form.getAttribute('data-wallet-coins') || '0'));
    const maxQuantity = Math.max(1, Number(form.getAttribute('data-max-quantity') || '1'));
    const quantityInput = form.querySelector('[data-market-buy-quantity-input]');
    const totalOutput = form.querySelector('[data-market-buy-total]');
    const button = form.querySelector('[data-market-buy-button]');

    const syncBuyForm = () => {
      if (!button) {
        return;
      }

      if (!isKeyType || !(quantityInput instanceof HTMLInputElement)) {
        return;
      }

      const safeQuantity = Math.min(maxQuantity, Math.max(1, Number(quantityInput.value || '1')));
      quantityInput.value = String(safeQuantity);

      const totalPrice = unitPrice * safeQuantity;
      if (totalOutput) {
        totalOutput.textContent = `${totalPrice} coins`;
      }

      const hasEnoughCoins = walletCoins >= totalPrice;
      button.disabled = isOwnListing || !hasEnoughCoins;
      button.textContent = isOwnListing ? 'Sua oferta no mercado' : (hasEnoughCoins ? `Comprar ${safeQuantity} chave(s)` : 'Coins insuficientes');
    };

    if (isKeyType && quantityInput instanceof HTMLInputElement) {
      quantityInput.addEventListener('input', syncBuyForm);
      quantityInput.addEventListener('blur', syncBuyForm);
      syncBuyForm();
    }
  });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
