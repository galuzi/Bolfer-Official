<?php
$title = 'Mercado interno';
require __DIR__ . '/../partials/header.php';

$sellableInventoryItems = $sellableInventoryItems ?? [];
$myMarketListings = $myMarketListings ?? [];
$marketTransactions = $marketTransactions ?? [];
$marketTopups = $marketTopups ?? [];
$inventoryTypeOptions = $inventoryTypeOptions ?? [];
$marketCoins = (int) ($marketCoins ?? 0);
$marketKeys = (int) ($marketKeys ?? 0);
$marketListingMinPrice = (int) ($marketListingMinPrice ?? 100);
$marketCoinRaté = (float) ($marketCoinRaté ?? 10);
$marketTopupMinBrl = (float) ($marketTopupMinBrl ?? 10);
$marketTopupMaxBrl = (float) ($marketTopupMaxBrl ?? 50);
$marketTopupSuggestions = $marketTopupSuggestions ?? [];
$marketTopupMaxDigits = strlen((string) max(1, (int) floor($marketTopupMaxBrl)));
$blockedInventoryCount = count($sellableInventoryItems);
$myActiveListingsCount = count(array_filter(
    $myMarketListings,
    static fn(array $listing): bool => (string) ($listing['status'] ?? '') === 'active'
));
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card market-page">
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

        <div class="market-hero">
        <div class="market-hero-copy">
          <p class="user-kicker">Mercado Bolfer</p>
          <h1>Coins, chaves e jogos em um mercado mais simples.</h1>
          <p class="user-sub">Coins compram produtos no mercado, chaves desbloqueiam jogos no inventário, e jogos ou chaves podem ser vendidos entre usuários por coins.</p>

          <div class="market-hero-actions">
            <a class="btn-ghost" href="/usuario">Voltar para minha conta</a>
            <a class="btn-ghost" href="/usuario/inventario">Abrir inventário</a>
            <a class="btn-ghost" href="/usuario/mercado/comprar">Comprar itens</a>
            <a class="btn-ghost" href="/usuario/mercado/historico">Ver histórico</a>
          </div>
        </div>

        <aside class="market-wallet-card market-wallet-card--hero">
          <span class="user-status-label">Carteira atual</span>
          <strong><?= $marketCoins; ?> coins</strong>
          <p>Chaves disponiveis no inventário: <?= $marketKeys; ?>.</p>
          <p>Faixa de recarga: de R$ <?= e(number_format($marketTopupMinBrl, 0, ',', '.')); ?> até R$ <?= e(number_format($marketTopupMaxBrl, 0, ',', '.')); ?>.</p>
          <p>Conversao atual: 1 real = <?= rtrim(rtrim(number_format($marketCoinRate, 2, '.', ''), '0'), '.'); ?> coins.</p>
          <p>Preço mínimo por oferta: <?= $marketListingMinPrice; ?> coins.</p>
        </aside>
      </div>

      <div class="market-guide">
        <article class="market-guide-step">
          <span>1</span>
          <div>
            <strong>Coloque saldo na carteira</strong>
            <p>Escolha um valor rápido ou digite quanto você quer recarregar.</p>
          </div>
        </article>
        <article class="market-guide-step">
          <span>2</span>
          <div>
            <strong>Compre ou acumule chaves</strong>
            <p>As chaves ficam no seu inventário e servem para abrir os jogos que chegarem bloqueados.</p>
          </div>
        </article>
        <article class="market-guide-step">
          <span>3</span>
          <div>
            <strong>Venda jogos ou chaves por coins</strong>
            <p>As ofertas abertas ficam em uma aba separada e o mercado mostra o que já está disponível para compra.</p>
          </div>
        </article>
      </div>

      <div class="market-flow">
        <section class="market-panel market-panel--feature market-panel--stage market-panel--buy">
          <div class="market-panel-head market-panel-head--stage">
            <div class="market-panel-copy">
              <span class="market-step-label">Etapa 1</span>
              <p class="user-kicker">Comprar coins</p>
              <h2>Abasteca sua carteira</h2>
              <p class="user-sub">Primeiro você coloca saldo na carteira. Depois disso, comprar item ou publicar oferta fica bem mais simples.</p>
            </div>
            <span class="market-count-chip">Ate R$ <?= e(number_format($marketTopupMaxBrl, 0, ',', '.')); ?> por recarga</span>
          </div>

          <div class="market-stage-layout">
            <div class="market-stage-side">
              <div class="market-rule-grid market-rule-grid--stacked">
                <article class="market-rule-card">
                  <strong>Como isso funciona</strong>
                  <ul class="market-rule-list">
                    <li>Você paga em reais.</li>
                    <li>O sistema converte automaticamente para coins.</li>
                    <li>As coins entram na sua carteira apos a aprovacao do pagamento.</li>
                  </ul>
                </article>
                <article class="market-rule-card market-rule-card--accent">
                  <strong>Resumo rápido</strong>
                  <ul class="market-rule-list">
                    <li>Mínimo de recarga: R$ <?= e(number_format($marketTopupMinBrl, 0, ',', '.')); ?>.</li>
                    <li>Máximo de recarga: R$ <?= e(number_format($marketTopupMaxBrl, 0, ',', '.')); ?>.</li>
                    <li>Exemplo: R$ 25 vira <?= max(1, (int) floor(25 * $marketCoinRate)); ?> coins.</li>
                  </ul>
                </article>
              </div>
            </div>

            <div class="market-stage-main">
              <div class="market-topup-suggestions">
                <?php foreach ($marketTopupSuggestions as $suggestion) : ?>
                  <?php $coinsPreview = max(1, (int) floor(((float) $suggestion) * $marketCoinRate)); ?>
                  <button class="market-topup-chip" type="button" data-topup-value="<?= (int) round((float) $suggestion); ?>">
                    Comprar <?= $coinsPreview; ?> coins por R$ <?= e(number_format((float) $suggestion, 0, ',', '.')); ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <form method="post" action="/usuario/mercado/coins/comprar" class="market-topup-form" id="marketTopupForm">
                <?= csrf_field(); ?>
                <div class="market-form-grid">
                  <div class="field market-form-span">
                    <label>Valor da recarga em reais</label>
                    <input type="text" name="amount_brl" id="marketTopupAmount" inputmode="numeric" pattern="[0-9]*" maxlength="<?= $marketTopupMaxDigits; ?>" min="<?= e(number_format($marketTopupMinBrl, 2, '.', '')); ?>" max="<?= e(number_format($marketTopupMaxBrl, 2, '.', '')); ?>" value="<?= e(number_format($marketTopupMinBrl, 0, '.', '')); ?>">
                    <small class="market-field-hint">Digite um valor inteiro entre R$ <?= e(number_format($marketTopupMinBrl, 0, ',', '.')); ?> e R$ <?= e(number_format($marketTopupMaxBrl, 0, ',', '.')); ?>.</small>
                  </div>
                </div>

                <div class="market-topup-preview">
                  <span>Previsao da recarga</span>
                  <strong id="marketTopupCoinsPreview"><?= max(1, (int) floor($marketTopupMinBrl * $marketCoinRate)); ?> coins</strong>
                  <small>O valor em coins e calculado automaticamente antes de você pagar.</small>
                </div>

                <div class="market-panel-actions">
                  <button class="btn" type="submit">Comprar coins</button>
                </div>
              </form>

              <div class="market-subsection-head">
                <strong>Últimas recargas</strong>
                <span>Para você acompanhar o que já foi pedido.</span>
              </div>

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
                    <span>Quando você comprar coins, o pedido aparece aqui.</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </section>

        <section class="market-panel market-panel--feature market-panel--stage market-panel--sell">
          <div class="market-panel-head market-panel-head--stage">
            <div class="market-panel-copy">
              <span class="market-step-label">Etapa 2</span>
              <p class="user-kicker">Criar oferta</p>
              <h2>Venda itens do seu inventário</h2>
              <p class="user-sub">Aqui entram jogos ainda bloqueados e chaves disponíveis. Escolha o item, defina o preço em coins e publique.</p>
            </div>
            <span class="market-count-chip"><?= $blockedInventoryCount; ?> item(ns) disponível(is)</span>
          </div>

          <div class="market-stage-layout">
            <div class="market-stage-side">
              <div class="market-rule-grid market-rule-grid--stacked">
                <article class="market-rule-card market-rule-card--good">
                  <strong>Você pode vender</strong>
                  <ul class="market-rule-list">
                    <li>Jogos que ainda estao bloqueados.</li>
                    <li>Chaves disponiveis no inventário.</li>
                    <li>Ofertas a partir de <?= $marketListingMinPrice; ?> coins.</li>
                  </ul>
                </article>
                <article class="market-rule-card market-rule-card--warn">
                  <strong>Você não pode vender</strong>
                  <ul class="market-rule-list">
                    <li>Jogo que já foi desbloqueado.</li>
                    <li>Item vazio, sem conteudo ou sem quantidade.</li>
                    <li>Itens antigos que não entram nessa lógica nova.</li>
                  </ul>
                </article>
              </div>
            </div>

            <div class="market-stage-main">
              <?php if ($sellableInventoryItems === []) : ?>
                <div class="market-empty-note">
                  <strong>Nada disponível para anunciar agora.</strong>
                  <span>Assim que você tiver um item bloqueado no inventário, ele aparece aqui automaticamente.</span>
                </div>
              <?php else : ?>
                <form method="post" action="/usuario/mercado/ofertas/criar" class="market-sell-form">
                  <?= csrf_field(); ?>
                  <div class="market-form-grid">
                    <div class="field market-form-span">
                      <label>1. Escolha o jogo ou a chave</label>
                      <select name="inventory_id" id="marketSellInventory">
                        <?php foreach ($sellableInventoryItems as $inventoryItem) : ?>
                          <?php
                            $itemType = (string) ($inventoryItem['item_type'] ?? 'outro');
                            $itemLabel = $inventoryTypeOptions[$itemType] ?? 'Outro';
                            $itemUnlockCost = max(0, (int) ($inventoryItem['unlock_cost'] ?? 0));
                            $usesKeysToUnlock = \App\Repositories\UserInventoryRepository::usesKeyUnlock($itemType);
                            $isKeyType = \App\Repositories\UserInventoryRepository::isKeyType($itemType);
                            $itemQuantity = max(1, (int) ($inventoryItem['quantity'] ?? 1));
                          ?>
                          <option
                            value="<?= (int) ($inventoryItem['id'] ?? 0); ?>"
                            data-is-key="<?= $isKeyType ? '1' : '0'; ?>"
                            data-available-quantity="<?= $itemQuantity; ?>"
                          >
                            <?= e((string) ($inventoryItem['item_name'] ?? 'Item')); ?> - <?= e($itemLabel); ?> -
                            <?php if ($isKeyType) : ?>
                              <?= $itemQuantity; ?> unidade(s) pronta(s) para venda
                            <?php elseif ($usesKeysToUnlock) : ?>
                              desbloqueio por <?= $itemUnlockCost; ?> chave(s)
                            <?php else : ?>
                              desbloqueio por <?= $itemUnlockCost; ?> coins
                            <?php endif; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <small class="market-field-hint">Aqui aparecem chaves abertas e jogos que ainda não foram desbloqueados.</small>
                    </div>

                    <div class="field">
                      <label id="marketSellPriceLabel">2. Defina o preço da venda</label>
                      <input type="number" name="price_coins" id="marketSellPriceInput" min="<?= $marketListingMinPrice; ?>" value="<?= $marketListingMinPrice; ?>">
                      <small class="market-field-hint" id="marketSellPriceHint">Preço mínimo permitido: <?= $marketListingMinPrice; ?> coins.</small>
                    </div>

                    <div class="field is-hidden" id="marketSellQuantityField">
                      <label>3. Quantidade para anunciar</label>
                      <input type="number" name="quantity" id="marketSellQuantityInput" min="1" max="1" value="1">
                      <small class="market-field-hint" id="marketSellQuantityHint">Quando for chave, você pode anunciar mais de uma unidade no mesmo anúncio.</small>
                    </div>

                    <div class="market-helper-box">
                      <strong id="marketSellStepTitle">3. Publique a oferta</strong>
                      <p id="marketSellStepCopy">Quando a oferta for criada, o item sai do seu inventário e fica reservado no mercado. Se você cancelar, ele volta para você no mesmo estado anterior.</p>
                    </div>
                  </div>

                  <div class="market-panel-actions">
                    <button class="btn" type="submit">Publicar oferta</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </section>
      </div>

      <section class="market-panel market-panel--wide">
        <div class="market-panel-head">
          <div>
            <p class="user-kicker">Minhas ofertas</p>
            <h2>O que você colocou a venda</h2>
          </div>
          <span class="market-count-chip"><?= count($myMarketListings); ?> registro(s)</span>
        </div>
        <p class="user-sub">Use esse bloco para ver o status das suas ofertas. Se ela estiver ativa, você ainda pode cancelar e o item volta para o seu inventário.</p>

        <?php if ($myMarketListings === []) : ?>
          <div class="market-empty-note">
            <strong>Você ainda não publicou nenhuma oferta.</strong>
            <span>Escolha um item bloqueado no painel de venda para comecar.</span>
          </div>
        <?php else : ?>
          <div class="market-my-list-grid">
            <?php foreach ($myMarketListings as $listing) : ?>
              <?php
                $listingStatus = (string) ($listing['status'] ?? 'active');
                $listingType = (string) ($listing['item_type'] ?? 'outro');
                $usesKeysToUnlock = \App\Repositories\UserInventoryRepository::usesKeyUnlock($listingType);
                $isKeyType = \App\Repositories\UserInventoryRepository::isKeyType($listingType);
                $listingStatusLabel = match ($listingStatus) {
                  'sold' => 'Vendido',
                  'cancelled' => 'Cancelado',
                  default => 'Ativo',
                };
              ?>
              <article class="market-my-card">
                <div class="market-my-card-top">
                  <div>
                    <span class="inventory-type"><?= e($inventoryTypeOptions[$listingType] ?? 'Outro'); ?></span>
                    <h3><?= e((string) ($listing['item_name'] ?? 'Item')); ?></h3>
                  </div>
                  <span class="market-status-pill is-<?= e($listingStatus); ?>"><?= e($listingStatusLabel); ?></span>
                </div>

                <div class="market-detail-list">
                  <div class="market-detail-row">
                    <span><?= $isKeyType ? 'Preço por unidade' : 'Preço da venda'; ?></span>
                    <strong><?= (int) ($listing['price_coins'] ?? 0); ?> coins</strong>
                  </div>
                  <div class="market-detail-row">
                    <span>Desbloqueio para o comprador</span>
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
                  <div class="market-detail-row">
                    <span>Quantidade</span>
                    <strong><?= max(1, (int) ($listing['quantity'] ?? 1)); ?></strong>
                  </div>
                </div>

                <?php if ($listingStatus === 'sold') : ?>
                  <p>Comprador: <?= e((string) ($listing['buyer_username'] ?? 'Usuário')); ?></p>
                <?php endif; ?>

                <?php if ($listingStatus === 'active') : ?>
                  <form method="post" action="/usuario/mercado/ofertas/cancelar">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="listing_id" value="<?= (int) ($listing['id'] ?? 0); ?>">
                    <button class="btn-ghost" type="submit">Cancelar oferta</button>
                  </form>
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
  (function () {
    const amountInput = document.getElementById('marketTopupAmount');
    const coinsPreview = document.getElementById('marketTopupCoinsPreview');
    const suggestionButtons = document.querySelectorAll('[data-topup-value]');
    const coinRaté = <?= json_encode($marketCoinRate); ?>;
    const minTopup = <?= json_encode($marketTopupMinBrl); ?>;
    const maxTopup = <?= json_encode($marketTopupMaxBrl); ?>;
    const maxDigits = <?= json_encode($marketTopupMaxDigits); ?>;
    const sellInventory = document.getElementById('marketSellInventory');
    const sellQuantityField = document.getElementById('marketSellQuantityField');
    const sellQuantityInput = document.getElementById('marketSellQuantityInput');
    const sellQuantityHint = document.getElementById('marketSellQuantityHint');
    const sellPriceLabel = document.getElementById('marketSellPriceLabel');
    const sellPriceHint = document.getElementById('marketSellPriceHint');
    const sellStepTitle = document.getElementById('marketSellStepTitle');
    const sellStepCopy = document.getElementById('marketSellStepCopy');

    const sanitizeAmount = () => {
      if (!amountInput) {
        return 0;
      }

      const digits = (amountInput.value || '').replace(/\D+/g, '').slice(0, maxDigits);
      if (digits === '') {
        amountInput.value = '';
        return 0;
      }

      const safeAmount = Math.min(Number(digits), Math.floor(maxTopup));
      amountInput.value = String(safeAmount);
      return safeAmount;
    };

    const updatePreview = () => {
      if (!amountInput || !coinsPreview) {
        return;
      }

      const amount = sanitizeAmount();
      if (!Number.isFinite(amount) || amount < minTopup) {
        coinsPreview.textContent = '0 coins';
        return;
      }

      const safeAmount = Math.min(amount, maxTopup);
      coinsPreview.textContent = `${Math.max(1, Math.floor(safeAmount * coinRate))} coins`;
    };

    suggestionButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (!amountInput) {
          return;
        }

        amountInput.value = button.getAttribute('data-topup-value') || '';
        updatePreview();
      });
    });

    if (amountInput) {
      amountInput.addEventListener('input', updatePreview);
      amountInput.addEventListener('blur', () => {
        const amount = sanitizeAmount();
        if (amount > 0 && amount < minTopup) {
          amountInput.value = String(Math.floor(minTopup));
        }
        updatePreview();
      });
    }

    updatePreview();

    const syncSellForm = () => {
      if (!sellInventory || !sellQuantityField || !sellQuantityInput) {
        return;
      }

      const selectedOption = sellInventory.options[sellInventory.selectedIndex];
      if (!selectedOption) {
        return;
      }

      const isKeyType = selectedOption.getAttribute('data-is-key') === '1';
      const availableQuantity = Math.max(1, Number(selectedOption.getAttribute('data-available-quantity') || '1'));

      sellQuantityField.classList.toggle('is-hidden', !isKeyType);
      sellQuantityInput.max = String(availableQuantity);
      sellQuantityInput.value = String(Math.min(Math.max(1, Number(sellQuantityInput.value || '1')), availableQuantity));

      if (isKeyType) {
        sellPriceLabel.textContent = '2. Defina o preço por chave';
        sellPriceHint.textContent = 'Esse valor será cobrado por unidade. A compra total muda conforme a quantidade escolhida pelo comprador.';
        sellQuantityHint.textContent = `Você pode anunciar de 1 até ${availableQuantity} chave(s) nesse anúncio.`;
        sellStepTitle.textContent = '4. Publique a oferta';
        sellStepCopy.textContent = 'As chaves anunciadas saem do seu inventário e ficam reservadas no mercado. Se parte delas vender, o restante continua ativo no anúncio.';
      } else {
        sellPriceLabel.textContent = '2. Defina o preço da venda';
        sellPriceHint.textContent = 'Preço mínimo permitido: <?= $marketListingMinPrice; ?> coins.';
        sellQuantityInput.value = '1';
        sellQuantityHint.textContent = 'Quando for chave, você pode anunciar mais de uma unidade no mesmo anúncio.';
        sellStepTitle.textContent = '3. Publique a oferta';
        sellStepCopy.textContent = 'Quando a oferta for criada, o item sai do seu inventário e fica reservado no mercado. Se você cancelar, ele volta para você no mesmo estado anterior.';
      }
    };

    if (sellInventory) {
      sellInventory.addEventListener('change', syncSellForm);
      syncSellForm();
    }
  })();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
