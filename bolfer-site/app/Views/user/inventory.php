<?php
$title = 'Inventário';
require __DIR__ . '/../partials/header.php';

$inventoryItems = $inventoryItems ?? [];
$inventorySummary = $inventorySummary ?? ['entries' => 0, 'units' => 0, 'types' => 0, 'locked' => 0, 'unlocked' => 0];
$inventoryTypeOptions = $inventoryTypeOptions ?? [];
$marketCoins = (int) ($marketCoins ?? 0);
$marketKeys = (int) ($marketKeys ?? 0);
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card user-market-page">
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="user-head user-market-top">
        <div class="user-market-intro">
          <p class="user-kicker">Mercado interno</p>
          <h1>Invent&aacute;rio de <strong><?= e($user['username'] ?? ''); ?></strong></h1>
          <p class="user-sub">Aqui voc&ecirc; acompanha os jogos, itens e chaves da sua conta. As coins compram itens no mercado, e as chaves desbloqueiam os jogos que chegarem travados no invent&aacute;rio.</p>

          <div class="user-footer user-market-actions">
            <a class="btn-ghost" href="/usuario">Voltar para minha conta</a>
            <a class="btn-ghost" href="/usuario/mercado">Abrir mercado</a>
            <a class="btn-ghost" href="/usuario/mercado/historico">Ver hist&oacute;rico</a>
          </div>
        </div>

        <aside class="user-status user-market-wallet">
          <span class="user-status-label">Carteira e chaves</span>
          <span class="user-badge user-badge--vip"><?= $marketCoins; ?> coins</span>
          <p class="user-market-wallet-note"><?= $marketKeys; ?> chave(s) dispon&iacute;veis para desbloquear jogos.</p>
        </aside>
      </div>

      <div class="user-market-steps">
        <article class="user-market-step">
          <span>1</span>
          <div>
            <strong>Receba itens</strong>
            <p>Os itens enviados pela equipe aparecem organizados no seu invent&aacute;rio.</p>
          </div>
        </article>
        <article class="user-market-step">
          <span>2</span>
          <div>
            <strong>Veja o que precisa de chaves</strong>
            <p>Jogos travados mostram quantas chaves voc&ecirc; precisa para abrir o conte&uacute;do.</p>
          </div>
        </article>
        <article class="user-market-step">
          <span>3</span>
          <div>
            <strong>Desbloqueie quando quiser</strong>
            <p>Quando voc&ecirc; tiver chaves suficientes, basta us&aacute;-las no jogo para liberar a key ou o acesso.</p>
          </div>
        </article>
      </div>

      <div class="user-inventory">
        <div class="user-summary-panel">
          <div class="user-inventory-head user-inventory-head--stacked user-summary-panel__head">
            <div class="user-summary-copy">
              <span class="user-summary-pill">Painel r&aacute;pido</span>
              <div>
                <p class="user-kicker">Resumo do invent&aacute;rio</p>
                <h2>Itens e conte&uacute;dos bloqueados</h2>
              </div>
              <p class="user-sub">Abaixo voc&ecirc; encontra um resumo r&aacute;pido do seu invent&aacute;rio e, logo depois, a lista completa dos itens da sua conta.</p>
            </div>

            <div class="user-inventory-stats user-inventory-stats--market user-summary-stats">
              <article class="user-stat-card user-stat-card--items">
                <span>Itens</span>
                <strong><?= (int) ($inventorySummary['entries'] ?? 0); ?></strong>
              </article>
              <article class="user-stat-card user-stat-card--locked">
                <span>Bloqueados</span>
                <strong><?= (int) ($inventorySummary['locked'] ?? 0); ?></strong>
              </article>
              <article class="user-stat-card user-stat-card--open">
                <span>Liberados</span>
                <strong><?= (int) ($inventorySummary['unlocked'] ?? 0); ?></strong>
              </article>
            </div>
          </div>

          <div class="user-summary-note">
            <strong>Como ler este painel</strong>
            <p>Bloqueados s&atilde;o jogos aguardando chaves ou itens antigos aguardando coins. Liberados s&atilde;o itens j&aacute; abertos ou revelados na sua conta.</p>
          </div>
        </div>

        <?php if ($inventoryItems === []) : ?>
          <div class="user-inventory-empty user-inventory-empty--featured">
            <span class="user-empty-badge">Invent&aacute;rio vazio</span>
            <strong>Nenhum item apareceu aqui ainda.</strong>
            <p>Quando a equipe liberar itens ou recompensas, eles entram nesta p&aacute;gina e voc&ecirc; passa a acompanhar tudo por aqui.</p>
          </div>
        <?php else : ?>
          <div class="user-market-section-head">
            <div>
              <p class="user-kicker">Seus itens</p>
              <h2>Lista completa do invent&aacute;rio</h2>
            </div>
            <p class="user-sub">Cada card mostra o tipo do item, a quantidade e o estado do conte&uacute;do protegido.</p>
          </div>

          <div class="user-inventory-grid">
            <?php foreach ($inventoryItems as $inventoryItem) : ?>
              <?php
                $itemType = (string) ($inventoryItem['item_type'] ?? 'outro');
                $itemLabel = $inventoryTypeOptions[$itemType] ?? 'Outro';
                $itemQuantity = max(1, (int) ($inventoryItem['quantity'] ?? 1));
                $receivedAt = !empty($inventoryItem['created_at']) ? date('d/m/Y', strtotime((string) $inventoryItem['created_at'])) : null;
                $unlockCost = (int) ($inventoryItem['unlock_cost'] ?? 0);
                $lockedContent = trim((string) ($inventoryItem['locked_content'] ?? ''));
                $hasLockedContent = $unlockCost > 0 && $lockedContent !== '';
                $isUnlocked = !empty($inventoryItem['is_unlocked']);
                $usesKeysToUnlock = \App\Repositories\UserInventoryRepository::usesKeyUnlock($itemType);
                $canUnlock = $usesKeysToUnlock ? $marketKeys >= $unlockCost : $marketCoins >= $unlockCost;
              ?>
              <article class="inventory-card inventory-card--<?= e($itemType); ?>">
                <div class="inventory-card-top">
                  <span class="inventory-type"><?= e($itemLabel); ?></span>
                  <span class="inventory-quantity">x<?= $itemQuantity; ?></span>
                </div>

                <h3><?= e($inventoryItem['item_name'] ?? 'Item sem nome'); ?></h3>

                <?php if (!empty($inventoryItem['description'])) : ?>
                  <p><?= e($inventoryItem['description']); ?></p>
                <?php else : ?>
                  <p>Item registrado pela equipe para compor o seu invent&aacute;rio oficial dentro da plataforma.</p>
                <?php endif; ?>

                <?php if ($hasLockedContent && !$isUnlocked) : ?>
                  <div class="inventory-lock-box">
                    <strong>Conte&uacute;do bloqueado</strong>
                    <p>
                      <?php if ($usesKeysToUnlock) : ?>
                        Esse jogo precisa de <?= $unlockCost; ?> chave(s) para revelar a key ou as informa&ccedil;&otilde;es privadas.
                      <?php else : ?>
                        Esse item precisa de <?= $unlockCost; ?> coins para revelar a key ou as informa&ccedil;&otilde;es privadas.
                      <?php endif; ?>
                    </p>
                    <form method="post" action="/usuario/inventario/desbloquear" class="inventory-unlock-form">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="inventory_id" value="<?= (int) ($inventoryItem['id'] ?? 0); ?>">
                      <button class="btn small" type="submit" <?= $canUnlock ? '' : 'disabled'; ?>>
                        <?php if ($usesKeysToUnlock) : ?>
                          <?= $canUnlock ? 'Usar ' . $unlockCost . ' chave(s)' : 'Chaves insuficientes'; ?>
                        <?php else : ?>
                          <?= $canUnlock ? 'Desbloquear agora' : 'Coins insuficientes'; ?>
                        <?php endif; ?>
                      </button>
                    </form>
                  </div>
                <?php elseif ($hasLockedContent) : ?>
                  <div class="inventory-secret-box">
                    <span class="inventory-secret-label">Conte&uacute;do desbloqueado</span>
                    <div class="inventory-secret-content"><?= nl2br(e($lockedContent)); ?></div>
                  </div>
                <?php else : ?>
                  <div class="inventory-open-box">
                    <span class="inventory-secret-label">Item aberto</span>
                    <p>Esse item n&atilde;o precisa de desbloqueio para ser usado.</p>
                  </div>
                <?php endif; ?>

                <div class="inventory-meta">
                  <span><?= $receivedAt ? 'Adicionado em ' . e($receivedAt) : 'Adicionado recentemente'; ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
