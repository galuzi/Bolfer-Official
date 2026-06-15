<?php
$title = $product['name'];

$stockValue = $product['stock'];
$stockLabel = $stockValue === null ? 'Ilimitado' : (string) (int) $stockValue;
$minimumQuantity = max(1, (int) ($product['minimum_quantity'] ?? 1));
$minimumQuantityLabel = $minimumQuantity === 1 ? '1 unidade' : $minimumQuantity . ' unidades';
$hasStock = ($stockValue === null || (int) $stockValue > 0) && ($stockValue === null || (int) $stockValue >= $minimumQuantity);
$productTypeKey = (string) ($product['product_type'] ?? 'item');
$productTypeLabel = $productTypeKey === 'conta' ? 'Conta' : 'Item';
$isAccountProduct = $productTypeKey === 'conta';

$serverLabel = trim((string) ($product['server_label'] ?? ''));
if ($serverLabel === '') {
  $serverLabel = 'Entrega digital combinada';
}

$deliveryEtaLabel = trim((string) ($product['delivery_eta'] ?? ''));
if ($deliveryEtaLabel === '') {
  $deliveryEtaLabel = 'Entrega combinada';
}

$rawDescription = trim((string) ($product['product_description'] ?? ''));
$descriptionSummary = '';
if ($rawDescription !== '') {
  $normalized = preg_replace('/\s+/', ' ', $rawDescription) ?? $rawDescription;
  $limit = 180;
  $length = function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized);
  $slice = function_exists('mb_substr') ? mb_substr($normalized, 0, $limit, 'UTF-8') : substr($normalized, 0, $limit);
  $descriptionSummary = $slice;
  if ($length > $limit) {
    $descriptionSummary .= '...';
  }
}

$deliveryDetailsRaw = trim((string) ($product['description'] ?? ''));
$notesRaw = trim((string) ($product['notes'] ?? ''));

$buildStructuredBlocks = static function (string $text): array {
  $lines = preg_split('/\R/u', trim($text)) ?: [];
  $blocks = [];
  $paragraphBuffer = [];
  $listBuffer = [];
  $afterHeading = false;

  $flushParagraph = static function () use (&$blocks, &$paragraphBuffer): void {
    if ($paragraphBuffer === []) {
      return;
    }
    $blocks[] = [
      'type' => 'paragraph',
      'text' => implode(' ', $paragraphBuffer),
    ];
    $paragraphBuffer = [];
  };

  $flushList = static function () use (&$blocks, &$listBuffer): void {
    if ($listBuffer === []) {
      return;
    }
    $blocks[] = [
      'type' => 'list',
      'items' => $listBuffer,
    ];
    $listBuffer = [];
  };

  foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '') {
      $flushParagraph();
      $flushList();
      $afterHeading = false;
      continue;
    }

    if (preg_match('/^\[(.+)\]$/u', $line, $matches)) {
      $flushParagraph();
      $flushList();
      $blocks[] = [
        'type' => 'label',
        'text' => trim($matches[1]),
      ];
      $afterHeading = false;
      continue;
    }

    if (preg_match('/:\s*$/u', $line) === 1) {
      $flushParagraph();
      $flushList();
      $blocks[] = [
        'type' => 'heading',
        'text' => rtrim(rtrim($line), ':'),
      ];
      $afterHeading = true;
      continue;
    }

    if (preg_match('/^(?:[-*\x{2022}]\s+)(.+)$/u', $line, $matches) === 1) {
      $flushParagraph();
      $listBuffer[] = trim($matches[1]);
      $afterHeading = true;
      continue;
    }

    if ($afterHeading) {
      $flushParagraph();
      $listBuffer[] = $line;
      continue;
    }

    $paragraphBuffer[] = $line;
  }

  $flushParagraph();
  $flushList();

  return $blocks;
};

$deliveryDetailsBlocks = $deliveryDetailsRaw !== '' ? $buildStructuredBlocks($deliveryDetailsRaw) : [];
$notesBlocks = $notesRaw !== '' ? $buildStructuredBlocks($notesRaw) : [];

$accountInfoRaw = trim((string) ($product['account_info'] ?? ''));
$accountInfoItems = [];
if ($accountInfoRaw !== '') {
  $accountInfoItems = preg_split('/\R/u', $accountInfoRaw) ?: [];
  $accountInfoItems = array_values(array_filter(array_map('trim', $accountInfoItems), static fn(string $item): bool => $item !== ''));
}

$accountImages = [];
if (!empty($product['account_images'])) {
  $decodedAccountImages = json_decode((string) $product['account_images'], true);
  if (is_array($decodedAccountImages)) {
    $accountImages = array_values(array_filter($decodedAccountImages, static fn($item): bool => is_string($item) && trim($item) !== ''));
  }
}

if (!$hasStock) {
  $stockStateLabel = 'Esgotado';
  $stockStateClass = 'is-sold-out';
} elseif ($stockValue !== null && (int) $stockValue <= max(5, $minimumQuantity)) {
  $stockStateLabel = 'Ãšltimas unidades';
  $stockStateClass = 'is-low';
} else {
  $stockStateLabel = 'DisponÃ­vel';
  $stockStateClass = 'is-ready';
}

$metaTitle = $product['name'] . ' | Bolfer Official';
$metaDescription = $descriptionSummary !== '' ? $descriptionSummary : 'Produto digital da comunidade Bolfer com entrega combinada e compra segura.';
$metaImage = !empty($accountImages[0]) ? (string) $accountImages[0] : '/assets/img/logo.webp';
$schemaData = [
  [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => (string) $product['name'],
    'description' => (string) ($descriptionSummary !== '' ? $descriptionSummary : 'Produto digital da comunidade Bolfer com entrega combinada e compra segura.'),
    'image' => $metaImage,
    'sku' => (string) ($product['id'] ?? ''),
    'brand' => [
      '@type' => 'Brand',
      'name' => 'Bolfer Official',
    ],
    'offers' => [
      '@type' => 'Offer',
      'priceCurrency' => 'BRL',
      'price' => number_format((float) $product['unit_price'], 2, '.', ''),
      'availability' => $hasStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      'url' => url('/produto/' . rawurlencode((string) ($product['slug'] ?? ''))),
      'seller' => [
        '@type' => 'Organization',
        'name' => 'Bolfer Official',
      ],
    ],
  ],
];

require __DIR__ . '/partials/header.php';

$maxQty = null;
if ($product['stock'] !== null && (int) $product['stock'] > 0) {
  $maxQty = (int) $product['stock'];
}

$loggedUser = user_session();
?>

<section class="section alt product-page">
  <div class="container product-layout">
    <section class="card product-overview">
      <div class="product-overview-head">
        <div class="product-overview-copy">
          <p class="section-kicker">Produto digital Bolfer</p>
          <h1 class="product-title"><?= e($product['name']); ?></h1>
          <p class="product-server"><?= e($serverLabel); ?></p>
          <?php if ($descriptionSummary !== '') : ?>
            <p class="product-intro"><?= e($descriptionSummary); ?></p>
          <?php endif; ?>
        </div>

        <div class="product-price-panel">
          <span class="price-label">Valor por unidade</span>
          <strong class="price-value">R$ <?= number_format((float) $product['unit_price'], 2, ',', '.'); ?></strong>
          <small>Pedido mÃ­nimo: <?= e($minimumQuantityLabel); ?></small>
        </div>
      </div>

      <div class="product-pill-row">
        <span class="product-badge"><?= e($productTypeLabel); ?></span>
        <span class="product-badge <?= e($stockStateClass); ?>"><?= e($stockStateLabel); ?></span>
        <span class="product-badge">Entrega digital</span>
        <span class="product-badge">Suporte ativo</span>
        <span class="product-badge">Pagamento seguro</span>
      </div>

      <div class="product-summary-grid">
        <article class="product-summary-card">
          <span>Entrega estimada</span>
          <strong><?= e($deliveryEtaLabel); ?></strong>
          <small>Prazo mÃ©dio informado pela equipe.</small>
        </article>

        <article class="product-summary-card">
          <span>Estoque atual</span>
          <strong><?= e($stockLabel); ?></strong>
          <small><?= $stockValue === null ? 'Disponibilidade flexÃ­vel para esse item.' : 'Atualizado conforme o estoque do sistema.'; ?></small>
        </article>

        <article class="product-summary-card">
          <span>Compra mÃ­nima</span>
          <strong><?= e($minimumQuantityLabel); ?></strong>
          <small>O pedido precisa respeitar essa quantidade.</small>
        </article>

        <article class="product-summary-card">
          <span>Canal de entrega</span>
          <strong>WhatsApp ou Discord</strong>
          <small>VocÃª escolhe no checkout o contato mais fÃ¡cil.</small>
        </article>
      </div>

      <?php if ($isAccountProduct && !empty($accountImages)) : ?>
        <div class="product-gallery-panel">
          <div class="product-gallery-header">
            <p class="section-kicker">Preview</p>
            <h2>Imagens da conta</h2>
            <p>Clique em qualquer bloco para ampliar a imagem sem sair da página.</p>
          </div>

          <div class="product-account-gallery-grid">
            <?php foreach ($accountImages as $imageIndex => $imagePath) : ?>
              <button
                class="product-account-image"
                type="button"
                data-account-image-trigger
                data-account-image-src="<?= e($imagePath); ?>"
                data-account-image-alt="Imagem da conta <?= e($product['name']); ?> - <?= (int) $imageIndex + 1; ?>"
                data-account-image-label="Imagem <?= (int) $imageIndex + 1; ?>"
                aria-label="Ampliar imagem <?= (int) $imageIndex + 1; ?> da conta"
              >
                <span class="product-account-image-frame">
                  <img src="<?= e($imagePath); ?>" alt="Imagem da conta <?= e($product['name']); ?> - <?= (int) $imageIndex + 1; ?>" loading="lazy">
                </span>
                <span class="product-account-image-meta">
                  <strong>Imagem <?= (int) $imageIndex + 1; ?></strong>
                  <small>Clique para ampliar</small>
                </span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="product-image-lightbox" data-account-lightbox hidden>
          <button class="product-image-lightbox-backdrop" type="button" data-account-lightbox-close aria-label="Fechar visualizador"></button>
          <div class="product-image-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Visualizador de imagem da conta">
            <div class="product-image-lightbox-head">
              <div>
                <p class="section-kicker">Preview ampliado</p>
                <strong data-account-lightbox-label>Imagem da conta</strong>
              </div>
              <button class="product-image-lightbox-close" type="button" data-account-lightbox-close aria-label="Fechar visualizador">Fechar</button>
            </div>
            <div class="product-image-lightbox-media">
              <img src="" alt="" data-account-lightbox-image>
            </div>
            <div class="product-image-lightbox-footer">
              <span>Use esta visualização para conferir melhor os detalhes antes da compra.</span>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <a href="#" target="_blank" rel="noopener noreferrer" data-account-lightbox-link>Abrir em outra guia</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <aside class="card product-checkout">
      <div class="checkout-head">
        <p class="section-kicker">Compra guiada</p>
        <h2>Finalize sua compra</h2>
        <p class="checkout-sub">Preencha os dados abaixo para seguir direto para o pagamento com seguranÃ§a.</p>
      </div>

      <div class="checkout-summary">
        <div class="checkout-summary-row">
          <span>Total do pedido</span>
          <strong data-total>R$ <?= number_format((float) $product['unit_price'] * $minimumQuantity, 2, ',', '.'); ?></strong>
        </div>
        <div class="checkout-summary-meta">
          <span data-qty-chip><?= (int) $minimumQuantity; ?> <?= $minimumQuantity === 1 ? 'unidade' : 'unidades'; ?></span>
          <span><?= e($deliveryEtaLabel); ?></span>
          <span><?= e($stockStateLabel); ?></span>
        </div>
      </div>

      <div class="checkout-guide" aria-label="Fluxo rÃ¡pido de compra">
        <div class="checkout-guide-item">
          <strong>1. Quantidade</strong>
          <small>Total automÃ¡tico.</small>
        </div>
        <div class="checkout-guide-item">
          <strong>2. Contato</strong>
          <small>Escolha onde receber.</small>
        </div>
        <div class="checkout-guide-item">
          <strong>3. Pagamento</strong>
          <small>Entrega apÃ³s confirmaÃ§Ã£o.</small>
        </div>
      </div>

      <?php if (!$loggedUser) : ?>
        <div class="checkout-login-lock">
          <p class="checkout-login-lock-title">FaÃ§a login para comprar</p>
          <p class="checkout-login-lock-text">Somente usuÃ¡rios logados podem seguir para o checkout deste produto.</p>
          <a class="btn" href="/login">Entrar na conta</a>
        </div>
      <?php else : ?>
        <form method="post" action="/checkout/create" class="form checkout-form product-checkout-form">
          <?= csrf_field(); ?>
          <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">

          <button class="btn" type="submit" <?= $hasStock ? '' : 'disabled'; ?>><?= $hasStock ? 'Comprar agora' : 'IndisponÃ­vel no momento'; ?></button>
          <?php if (!$hasStock) : ?>
            <p class="checkout-note">Este produto nÃ£o tem estoque suficiente para atender a compra mÃ­nima no momento.</p>
          <?php endif; ?>

          <div class="checkout-form-grid">
            <div class="checkout-field checkout-field--quantity">
              <label for="checkoutQuantity">
                Quantidade
                <?php if ($minimumQuantity > 1) : ?>
                  <span class="checkout-label-note">MÃ­nimo: <?= e($minimumQuantityLabel); ?></span>
                <?php endif; ?>
              </label>
              <div class="checkout-qty-control">
                <button type="button" class="checkout-qty-btn" data-qty-step="-1" aria-label="Diminuir quantidade">-</button>
                <input id="checkoutQuantity" type="number" name="quantity" min="<?= (int) $minimumQuantity; ?>" value="<?= (int) $minimumQuantity; ?>" <?= $maxQty ? 'max="' . (int) $maxQty . '"' : ''; ?> required>
                <button type="button" class="checkout-qty-btn" data-qty-step="1" aria-label="Aumentar quantidade">+</button>
              </div>
            </div>

            <div class="checkout-field checkout-field--channel">
              <label for="checkoutChannel">Canal de contato</label>
              <select id="checkoutChannel" name="contact_channel" required>
                <option value="discord" selected>Discord</option>
                <option value="whatsapp">WhatsApp</option>
              </select>
            </div>

            <div class="checkout-field checkout-field--nick">
              <label for="checkoutNick">Nick no jogo</label>
              <input id="checkoutNick" type="text" name="in_game_nick" maxlength="60" placeholder="Como devemos identificar vocÃª?" required>
            </div>

            <div class="checkout-field checkout-field--contact">
              <label for="checkoutContact">Contato para entrega</label>
              <input id="checkoutContact" type="text" name="contact_value" placeholder="Ex: 11999999999" required>
              <p class="checkout-field-hint" data-contact-hint>Use o nÃºmero com DDD para agilizar a entrega.</p>
            </div>
          </div>

          <div class="checkout-field checkout-field--notes">
            <label for="checkoutNotes">ObservaÃ§Ãµes de entrega <span class="checkout-optional">Opcional</span></label>
            <textarea id="checkoutNotes" name="delivery_notes" rows="3" placeholder="Se precisar, deixe alguma observaÃ§Ã£o curta para a equipe."></textarea>
          </div>

          <div class="checkout-trust is-hidden" aria-label="InformaÃ§Ãµes da compra">
            <p class="checkout-trust-label">InformaÃ§Ãµes automÃ¡ticas da compra</p>
            <div class="checkout-trust-list">
              <div class="checkout-trust-item">
                <span class="checkout-trust-dot" aria-hidden="true"></span>
                <span>Pagamento via Mercado Pago</span>
              </div>
              <div class="checkout-trust-item">
                <span class="checkout-trust-dot" aria-hidden="true"></span>
                <span>Entrega digital</span>
              </div>
              <div class="checkout-trust-item">
                <span class="checkout-trust-dot" aria-hidden="true"></span>
                <span>Suporte humano</span>
              </div>
            </div>
          </div>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?php if (($isAccountProduct && !empty($accountInfoItems)) || $deliveryDetailsRaw !== '' || $notesRaw !== '') : ?>
  <section class="section product-details-section">
    <div class="container">
      <div class="product-details-grid">
        <?php if ($isAccountProduct && !empty($accountInfoItems)) : ?>
          <article class="card product-detail-card product-account-details">
            <p class="section-kicker">Conta</p>
            <h3>O que vem nesse pacote</h3>
            <ul class="product-account-list">
              <?php foreach ($accountInfoItems as $item) : ?>
                <li><?= e($item); ?></li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endif; ?>

        <?php if ($deliveryDetailsRaw !== '') : ?>
          <article class="card product-detail-card">
            <p class="section-kicker">Entrega</p>
            <h3>Como funciona</h3>
            <div class="product-rich-content">
              <?php foreach ($deliveryDetailsBlocks as $block) : ?>
                <?php if ($block['type'] === 'label') : ?>
                  <p class="product-rich-label"><?= e($block['text']); ?></p>
                <?php elseif ($block['type'] === 'heading') : ?>
                  <h4 class="product-rich-heading"><?= e($block['text']); ?></h4>
                <?php elseif ($block['type'] === 'list') : ?>
                  <ul class="product-rich-list">
                    <?php foreach ($block['items'] as $item) : ?>
                      <li><?= e($item); ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else : ?>
                  <p class="product-rich-text"><?= e($block['text']); ?></p>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endif; ?>

        <?php if ($notesRaw !== '') : ?>
          <article class="card product-detail-card">
            <p class="section-kicker">ObservaÃ§Ãµes</p>
            <h3>Detalhes importantes</h3>
            <div class="product-rich-content">
              <?php foreach ($notesBlocks as $block) : ?>
                <?php if ($block['type'] === 'label') : ?>
                  <p class="product-rich-label"><?= e($block['text']); ?></p>
                <?php elseif ($block['type'] === 'heading') : ?>
                  <h4 class="product-rich-heading"><?= e($block['text']); ?></h4>
                <?php elseif ($block['type'] === 'list') : ?>
                  <ul class="product-rich-list">
                    <?php foreach ($block['items'] as $item) : ?>
                      <li><?= e($item); ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else : ?>
                  <p class="product-rich-text"><?= e($block['text']); ?></p>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<script>
(() => {
  const unitPrice = <?= json_encode((float) $product['unit_price']); ?>;
  const productId = <?= json_encode((string) ($product['id'] ?? '')); ?>;
  const productName = <?= json_encode((string) ($product['name'] ?? '')); ?>;
  const productCategory = <?= json_encode((string) ($productTypeLabel ?? 'Produto digital')); ?>;
  const qtyInput = document.getElementById('checkoutQuantity');
  const totalEls = document.querySelectorAll('[data-total]');
  const qtyChip = document.querySelector('[data-qty-chip]');
  const channelSelect = document.getElementById('checkoutChannel');
  const contactInput = document.getElementById('checkoutContact');
  const contactHint = document.querySelector('[data-contact-hint]');
  const stepButtons = document.querySelectorAll('[data-qty-step]');
  const checkoutForm = document.querySelector('.product-checkout-form');
  const imageTriggers = document.querySelectorAll('[data-account-image-trigger]');
  const lightbox = document.querySelector('[data-account-lightbox]');
  const lightboxImage = document.querySelector('[data-account-lightbox-image]');
  const lightboxLabel = document.querySelector('[data-account-lightbox-label]');
  const lightboxLink = document.querySelector('[data-account-lightbox-link]');
  const lightboxCloseButtons = document.querySelectorAll('[data-account-lightbox-close]');

  const minQty = qtyInput && qtyInput.min ? Number(qtyInput.min) : 1;
  const maxQty = qtyInput && qtyInput.max ? Number(qtyInput.max) : null;
  const format = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });

  const clampQty = (value) => {
    let qty = Number(value || minQty || 1);
    if (!Number.isFinite(qty) || qty <= 0) {
      qty = minQty || 1;
    }
    qty = Math.max(minQty || 1, Math.floor(qty));
    if (maxQty) {
      qty = Math.min(qty, maxQty);
    }
    return qty;
  };

  const syncContactMode = () => {
    if (!channelSelect || !contactInput || !contactHint) return;
    const isWhatsapp = channelSelect.value === 'whatsapp';
    contactInput.placeholder = isWhatsapp ? 'Ex: 11999999999' : 'Ex: user, user#1234 ou @usuÃ¡rio';
    contactHint.textContent = isWhatsapp
      ? 'Use o nÃºmero com DDD para agilizar a entrega.'
      : 'Use sua tag ou identificador completo do Discord para evitar erro na entrega.';
  };

  const updateTotal = (nextValue) => {
    if (!qtyInput) {
      return;
    }
    const qty = clampQty(nextValue ?? qtyInput.value);
    qtyInput.value = String(qty);
    totalEls.forEach((node) => {
      node.textContent = format.format(unitPrice * qty);
    });
    if (qtyChip) {
      qtyChip.textContent = qty === 1 ? '1 unidade' : `${qty} unidades`;
    }
  };

  const buildAnalyticsItem = (quantity) => ({
    item_id: productId,
    item_name: productName,
    item_category: productCategory,
    price: unitPrice,
    quantity: quantity
  });

  if (typeof window.bolferTrack === 'function') {
    window.bolferTrack('view_item', {
      currency: 'BRL',
      value: unitPrice,
      items: [buildAnalyticsItem(1)]
    });
  }

  stepButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (!qtyInput) {
        return;
      }
      updateTotal(Number(qtyInput.value || minQty) + Number(button.dataset.qtyStep || 0));
    });
  });

  if (qtyInput) {
    qtyInput.addEventListener('input', () => updateTotal(qtyInput.value));
    qtyInput.addEventListener('change', () => updateTotal(qtyInput.value));
  }

  if (channelSelect) {
    channelSelect.addEventListener('change', syncContactMode);
    syncContactMode();
  }

  if (checkoutForm) {
    let beginCheckoutTracked = false;
    checkoutForm.addEventListener('submit', (event) => {
      if (beginCheckoutTracked || typeof window.bolferTrack !== 'function') {
        return;
      }

      event.preventDefault();
      beginCheckoutTracked = true;

      const currentQty = qtyInput ? clampQty(qtyInput.value) : 1;
      let checkoutSubmitted = false;
      const resumeSubmit = () => {
        if (checkoutSubmitted) {
          return;
        }
        checkoutSubmitted = true;
        checkoutForm.submit();
      };

      window.bolferTrack('begin_checkout', {
        currency: 'BRL',
        value: unitPrice * currentQty,
        items: [buildAnalyticsItem(currentQty)]
      }, resumeSubmit);

      window.setTimeout(resumeSubmit, 900);
    });
  }

  if (imageTriggers.length && lightbox && lightboxImage && lightboxLabel && lightboxLink) {
    const openLightbox = (trigger) => {
      const src = trigger.getAttribute('data-account-image-src') || '';
      const alt = trigger.getAttribute('data-account-image-alt') || 'Imagem da conta';
      const label = trigger.getAttribute('data-account-image-label') || 'Imagem da conta';
      if (!src) {
        return;
      }

      lightbox.hidden = false;
      document.body.classList.add('lightbox-open');
      lightboxImage.src = src;
      lightboxImage.alt = alt;
      lightboxLabel.textContent = label;
      lightboxLink.href = src;
    };

    const closeLightbox = () => {
      lightbox.hidden = true;
      document.body.classList.remove('lightbox-open');
      lightboxImage.src = '';
      lightboxImage.alt = '';
      lightboxLink.href = '#';
    };

    imageTriggers.forEach((trigger) => {
      trigger.addEventListener('click', () => openLightbox(trigger));
    });

    lightboxCloseButtons.forEach((button) => {
      button.addEventListener('click', closeLightbox);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !lightbox.hidden) {
        closeLightbox();
      }
    });
  }

  if (qtyInput) {
    updateTotal(qtyInput.value);
  }
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
