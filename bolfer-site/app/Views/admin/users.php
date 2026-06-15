<?php
$title = 'Usuarios';
require __DIR__ . '/partials/admin_header.php';

$qValue = (string) ($filters['q'] ?? '');
$roleValue = (string) ($filters['role'] ?? '');
$statusValue = (string) ($filters['status'] ?? '');
$inventoryByUserId = $inventoryByUserId ?? [];
$inventorySummary = $inventorySummary ?? [];
$inventoryTypeOptions = $inventoryTypeOptions ?? [];
$marketTypeOptions = $marketTypeOptions ?? [];
$coinBalances = $coinBalances ?? [];
?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Usuarios</h1>
        <p class="admin-sub">Gerencie banimentos, saldo de coins e itens do mercado interno sem misturar tudo na mesma area.</p>
      </div>
    </div>

    <div class="card admin-block">
      <form method="get" action="/admin/users" class="admin-filters">
        <div class="field">
          <label>Buscar</label>
          <input type="text" name="q" value="<?= e($qValue); ?>" placeholder="Usuario ou email">
        </div>

        <div class="field">
          <label>Cargo</label>
          <select name="role">
            <option value="">Todos</option>
            <option value="user" <?= $roleValue === 'user' ? 'selected' : ''; ?>>User</option>
            <option value="vip" <?= $roleValue === 'vip' ? 'selected' : ''; ?>>VIP</option>
            <option value="moderador" <?= $roleValue === 'moderador' ? 'selected' : ''; ?>>Moderador</option>
            <option value="doador" <?= $roleValue === 'doador' ? 'selected' : ''; ?>>Doador</option>
          </select>
        </div>

        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="">Todos</option>
            <option value="active" <?= $statusValue === 'active' ? 'selected' : ''; ?>>Ativo</option>
            <option value="banned" <?= $statusValue === 'banned' ? 'selected' : ''; ?>>Banido</option>
          </select>
        </div>

        <div class="admin-filter-actions">
          <button class="btn" type="submit">Filtrar</button>
          <a class="btn-ghost" href="/admin/users?clear=1">Limpar</a>
        </div>
      </form>
    </div>

    <div class="card admin-block">
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <?php if (empty($users)) : ?>
        <p>Nenhum usuario encontrado.</p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="table admin-table admin-users-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Cargo</th>
                <th>Status</th>
                <th>Banimento</th>
                <th>Mercado interno</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user) : ?>
                <?php
                  $isBanned = !empty($user['is_banned']);
                  $adminRole = admin_user()['role'] ?? '';
                  $isFullAdmin = admin_is_full();
                  $userRole = $user['role'] ?? 'user';
                  $canManage = admin_role_level($adminRole) > user_role_level($userRole);
                  $canManageEconomy = $canManage && $isFullAdmin;
                  $inventoryUserId = (int) ($user['id'] ?? 0);
                  $inventoryItems = $inventoryByUserId[$inventoryUserId] ?? [];
                  $inventoryResume = $inventorySummary[$inventoryUserId] ?? ['entries' => 0, 'units' => 0, 'types' => 0, 'locked' => 0];
                  $coinBalance = (int) ($coinBalances[$inventoryUserId] ?? 0);
                ?>
                <tr>
                  <td><?= (int) $user['id']; ?></td>
                  <td><?= e($user['username'] ?? ''); ?></td>
                  <td><?= e($user['email'] ?? ''); ?></td>
                  <td><?= e($userRole); ?></td>
                  <td><?= $isBanned ? 'Banido' : 'Ativo'; ?></td>
                  <td>
                    <?php if ($isBanned) : ?>
                      <div>Motivo: <?= e($user['banned_reason'] ?? '-'); ?></div>
                      <div>Por: <?= e($user['banned_by_username'] ?? '-'); ?></div>
                      <div>Em: <?= e($user['banned_at'] ?? '-'); ?></div>
                    <?php else : ?>
                      <span class="admin-table-muted">Nenhum bloqueio</span>
                    <?php endif; ?>
                  </td>
                  <td class="admin-inventory-cell">
                    <details class="admin-inventory-panel">
                      <summary class="admin-market-summary">
                        <span class="admin-market-summary-pill">
                          <strong><?= $coinBalance; ?></strong>
                          <small>Coins</small>
                        </span>
                        <span class="admin-market-summary-pill">
                          <strong><?= (int) ($inventoryResume['entries'] ?? 0); ?></strong>
                          <small>Itens</small>
                        </span>
                        <span class="admin-market-summary-pill">
                          <strong><?= (int) ($inventoryResume['locked'] ?? 0); ?></strong>
                          <small>Bloqueados</small>
                        </span>
                      </summary>

                      <div class="admin-market-grid">
                        <section class="admin-market-section">
                          <div class="admin-market-section-head">
                            <strong>Carteira do usuario</strong>
                            <span>Veja o saldo atual e faca ajustes rapidos de coins.</span>
                          </div>

                          <div class="admin-market-balance-cards">
                            <article class="admin-market-balance-card is-highlight">
                              <strong>Saldo atual</strong>
                              <span><?= $coinBalance; ?> coins</span>
                            </article>
                            <article class="admin-market-balance-card">
                              <strong>Itens cadastrados</strong>
                              <span><?= (int) ($inventoryResume['entries'] ?? 0); ?></span>
                            </article>
                            <article class="admin-market-balance-card">
                              <strong>Itens bloqueados</strong>
                              <span><?= (int) ($inventoryResume['locked'] ?? 0); ?></span>
                            </article>
                          </div>

                          <form method="post" action="/admin/users" class="admin-coin-form">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="market_adjust">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">

                            <div class="field">
                              <label>Coins</label>
                              <input type="number" name="coin_amount" min="-999999" max="999999" value="0" <?= $canManageEconomy ? '' : 'disabled'; ?>>
                            </div>

                            <div class="field admin-coin-form__wide">
                              <label>Observacao</label>
                              <input type="text" name="coin_note" maxlength="255" placeholder="Ex.: recompensa semanal, bonus, desconto ou correcao manual" <?= $canManageEconomy ? '' : 'disabled'; ?>>
                            </div>

                            <div class="admin-coin-form__actions">
                              <button class="btn small" type="submit" <?= $canManageEconomy ? '' : 'disabled'; ?>>Salvar coins</button>
                            </div>
                          </form>
                          <?php if (!$isFullAdmin) : ?>
                            <p class="admin-sub" style="margin-top: 10px;">Somente admins principais podem alterar saldo e inventário do mercado.</p>
                          <?php endif; ?>
                        </section>

                        <section class="admin-market-section">
                          <div class="admin-market-section-head">
                            <strong>Itens ja cadastrados</strong>
                            <span>Confira os jogos e chaves que o usuario recebeu e o que ainda precisa ser desbloqueado.</span>
                          </div>

                          <?php if ($inventoryItems === []) : ?>
                            <div class="admin-inventory-empty">
                              <strong>Nenhum item ainda.</strong>
                              <p>Assim que você cadastrar um item, ele aparece organizado nesta lista.</p>
                            </div>
                          <?php else : ?>
                            <div class="admin-inventory-list">
                              <?php foreach ($inventoryItems as $inventoryItem) : ?>
                                <?php
                                  $inventoryType = (string) ($inventoryItem['item_type'] ?? 'outro');
                                  $unlockCost = (int) ($inventoryItem['unlock_cost'] ?? 0);
                                  $hasLockedContent = $unlockCost > 0 && trim((string) ($inventoryItem['locked_content'] ?? '')) !== '';
                                  $isUnlocked = !empty($inventoryItem['is_unlocked']);
                                  $usesKeysToUnlock = \App\Repositories\UserInventoryRepository::usesKeyUnlock($inventoryType);
                                ?>
                                <article class="admin-inventory-item admin-inventory-item--<?= e($inventoryType); ?>">
                                  <div class="admin-inventory-item-content">
                                    <strong><?= e($inventoryItem['item_name'] ?? 'Item'); ?></strong>
                                    <span><?= e($inventoryTypeOptions[$inventoryType] ?? 'Outro'); ?> · x<?= max(1, (int) ($inventoryItem['quantity'] ?? 1)); ?></span>
                                    <?php if (!empty($inventoryItem['description'])) : ?>
                                      <p><?= e($inventoryItem['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($hasLockedContent) : ?>
                                      <p class="admin-item-lock-state">
                                        <?=
                                          $isUnlocked
                                            ? 'Conteudo ja desbloqueado pelo usuario.'
                                            : (
                                              $usesKeysToUnlock
                                                ? 'Conteudo bloqueado por ' . $unlockCost . ' chave(s).'
                                                : 'Conteudo bloqueado por ' . $unlockCost . ' coins.'
                                            );
                                        ?>
                                      </p>
                                    <?php else : ?>
                                      <p class="admin-item-lock-state admin-item-lock-state--open">Item aberto, sem custo de desbloqueio.</p>
                                    <?php endif; ?>
                                  </div>

                                  <form method="post" action="/admin/users" class="admin-inventory-item-action">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="inventory_remove">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">
                                    <input type="hidden" name="inventory_id" value="<?= (int) ($inventoryItem['id'] ?? 0); ?>">
                                    <button class="btn danger small" type="submit" <?= $canManageEconomy ? '' : 'disabled'; ?>>Remover item</button>
                                  </form>
                                </article>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </section>

                        <section class="admin-market-section">
                          <div class="admin-market-section-head">
                            <strong>Adicionar item ao mercado</strong>
                            <span>Entregue chaves abertas ou jogos que o usuario so libera usando chaves.</span>
                          </div>

                          <p class="admin-market-hint">Dica: use <strong>Chave</strong> para entregar unidades abertas no inventário. Use <strong>Jogo</strong> com custo e conteudo bloqueado para exigir chaves no desbloqueio.</p>

                          <form method="post" action="/admin/users" class="admin-inventory-form">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="inventory_add">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">

                            <div class="field admin-inventory-form__name">
                              <label>Nome do item</label>
                              <input type="text" name="item_name" maxlength="140" placeholder="Ex.: Dragon Ball Online ou Chave dourada" <?= $canManageEconomy ? '' : 'disabled'; ?>>
                            </div>

                            <div class="field">
                              <label>Tipo</label>
                              <select name="item_type" data-market-item-type <?= $canManageEconomy ? '' : 'disabled'; ?>>
                                <?php foreach ($marketTypeOptions as $typeValue => $typeLabel) : ?>
                                  <option value="<?= e($typeValue); ?>"><?= e($typeLabel); ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>

                            <div class="field">
                              <label>Quantidade</label>
                              <input type="number" name="quantity" min="1" max="999" value="1" <?= $canManageEconomy ? '' : 'disabled'; ?>>
                            </div>

                            <div class="field" data-game-only-field>
                              <label>Chaves para desbloquear</label>
                              <input type="number" name="unlock_cost" min="0" max="999999" value="0" data-game-only-input <?= $canManageEconomy ? '' : 'disabled'; ?>>
                            </div>

                            <div class="field admin-inventory-form__description" data-game-only-field>
                              <label>Descricao visivel</label>
                              <input type="text" name="description" maxlength="500" placeholder="Texto que o usuario ve antes de usar chaves ou antes da venda" data-game-only-input <?= $canManageEconomy ? '' : 'disabled'; ?>>
                            </div>

                            <div class="field admin-inventory-form__secret" data-game-only-field>
                              <label>Conteudo bloqueado</label>
                              <textarea name="locked_content" rows="5" maxlength="5000" placeholder="Cole aqui a key, o acesso ou as instrucoes que aparecem depois que o usuario usar as chaves do jogo." data-game-only-input <?= $canManageEconomy ? '' : 'disabled'; ?>></textarea>
                            </div>

                            <div class="admin-inventory-form__actions">
                              <button class="btn small" type="submit" <?= $canManageEconomy ? '' : 'disabled'; ?>>Adicionar item</button>
                            </div>
                          </form>
                        </section>
                      </div>
                    </details>
                  </td>
                  <td>
                    <form method="post" action="/admin/users" class="actions">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">
                      <?php if ($isBanned) : ?>
                        <input type="hidden" name="action" value="unban">
                        <button class="btn small" type="submit" <?= $canManage ? '' : 'disabled'; ?>>Desbanir</button>
                      <?php else : ?>
                        <input type="hidden" name="action" value="ban">
                        <input type="text" name="reason" maxlength="255" placeholder="Motivo (opcional)" <?= $canManage ? '' : 'disabled'; ?>>
                        <button class="btn danger small" type="submit" <?= $canManage ? '' : 'disabled'; ?>>Banir</button>
                      <?php endif; ?>
                    </form>
                    <?php if (!$canManage) : ?>
                      <div class="alert error" style="margin-top: 8px;">Sem permissao.</div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
(() => {
  const inventoryForms = document.querySelectorAll('.admin-inventory-form');
  if (!inventoryForms.length) return;

  inventoryForms.forEach((form) => {
    const typeField = form.querySelector('[data-market-item-type]');
    const itemNameField = form.querySelector('input[name="item_name"]');
    const gameOnlyFields = form.querySelectorAll('[data-game-only-field]');
    const gameOnlyInputs = form.querySelectorAll('[data-game-only-input]');

    if (!typeField || !gameOnlyFields.length) {
      return;
    }

    const syncMarketTypeFields = () => {
      const isKeyType = typeField.value === 'chave';

      gameOnlyFields.forEach((field) => {
        field.classList.toggle('is-hidden', isKeyType);
      });

      gameOnlyInputs.forEach((input) => {
        if (!(input instanceof HTMLInputElement) && !(input instanceof HTMLTextAreaElement)) {
          return;
        }

        input.disabled = isKeyType;
        if (isKeyType) {
          input.value = input instanceof HTMLInputElement && input.type === 'number' ? '0' : '';
        }
      });

      if (itemNameField instanceof HTMLInputElement) {
        if (isKeyType) {
          itemNameField.value = 'Chave';
          itemNameField.readOnly = true;
        } else {
          itemNameField.readOnly = false;
          if (itemNameField.value === 'Chave') {
            itemNameField.value = '';
          }
        }
      }
    };

    typeField.addEventListener('change', syncMarketTypeFields);
    syncMarketTypeFields();
  });
})();
</script>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
