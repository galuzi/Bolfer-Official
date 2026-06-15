<?php
$title = 'Logs';
require __DIR__ . '/partials/admin_header.php';

$summary = $summary ?? [];
$filters = $filters ?? [];
$scopeOptions = $scopeOptions ?? [];
$banStatusOptions = $banStatusOptions ?? [];
$marketEventOptions = $marketEventOptions ?? [];
$accessActionLabels = $accessActionLabels ?? [];
$marketEventLabels = $marketEventLabels ?? [];
$scopeValue = (string) ($filters['scope'] ?? 'all');
$qValue = (string) ($filters['q'] ?? '');
$ipValue = (string) ($filters['ip'] ?? '');
$banStatusValue = (string) ($filters['ban_status'] ?? '');
$marketEventValue = (string) ($filters['market_event'] ?? '');

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return $value;
    }
};

$buildScopeUrl = static function (string $scope) use ($filters): string {
    $query = $filters;
    $query['scope'] = $scope;
    $query = array_filter($query, static fn(mixed $value): bool => $value !== '' && $value !== null);
    $queryString = http_build_query($query);

    return '/admin/logs' . ($queryString !== '' ? '?' . $queryString : '');
};

$banStatusLabel = static function (string $status): string {
    return $status === 'active' ? 'Ativo' : 'Revogado';
};

$marketLockLabel = static function (string $state): string {
    return $state === 'locked' ? 'Bloqueado' : 'Aberto';
};
?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Logs</h1>
        <p class="admin-sub">Acompanhe banimentos, tentativas bloqueadas, mercado interno e o historico de IPs das contas em uma area separada do resto do admin.</p>
      </div>
    </div>

    <div class="admin-log-summary-grid">
      <article class="admin-log-summary-card">
        <span class="admin-log-summary-label">Banimentos ativos</span>
        <strong><?= (int) ($summary['active_bans'] ?? 0); ?></strong>
        <small>Contas ainda bloqueadas no sistema reforcado.</small>
      </article>
      <article class="admin-log-summary-card">
        <span class="admin-log-summary-label">Tentativas hoje</span>
        <strong><?= (int) ($summary['attempts_today'] ?? 0); ?></strong>
        <small>Bloqueios e falhas recentes no sistema de ban.</small>
      </article>
      <article class="admin-log-summary-card">
        <span class="admin-log-summary-label">Vendas no mercado</span>
        <strong><?= (int) ($summary['market_sales'] ?? 0); ?></strong>
        <small>Compras concluidas registradas na auditoria.</small>
      </article>
      <article class="admin-log-summary-card">
        <span class="admin-log-summary-label">Contas rastreadas</span>
        <strong><?= (int) ($summary['tracked_accounts'] ?? 0); ?></strong>
        <small>Usuarios com historico de acesso e IP salvo.</small>
      </article>
      <article class="admin-log-summary-card">
        <span class="admin-log-summary-label">IPs unicos</span>
        <strong><?= (int) ($summary['unique_ips'] ?? 0); ?></strong>
        <small>Enderecos distintos vistos nas contas registradas.</small>
      </article>
    </div>

    <div class="card admin-block admin-log-toolbar">
      <form method="get" action="/admin/logs" class="admin-log-filters">
        <div class="field">
          <label>Buscar</label>
          <input type="text" name="q" value="<?= e($qValue); ?>" placeholder="Usuario, email, item, admin ou motivo">
        </div>

        <div class="field">
          <label>IP</label>
          <input type="text" name="ip" value="<?= e($ipValue); ?>" placeholder="Ex.: 189.0.0.10">
        </div>

        <div class="field">
          <label>Area</label>
          <select name="scope">
            <?php foreach ($scopeOptions as $scopeKey => $scopeLabel) : ?>
              <option value="<?= e($scopeKey); ?>" <?= $scopeValue === $scopeKey ? 'selected' : ''; ?>><?= e($scopeLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Status do ban</label>
          <select name="ban_status">
            <?php foreach ($banStatusOptions as $statusKey => $statusLabel) : ?>
              <option value="<?= e($statusKey); ?>" <?= $banStatusValue === $statusKey ? 'selected' : ''; ?>><?= e($statusLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Evento do mercado</label>
          <select name="market_event">
            <?php foreach ($marketEventOptions as $eventKey => $eventLabel) : ?>
              <option value="<?= e($eventKey); ?>" <?= $marketEventValue === $eventKey ? 'selected' : ''; ?>><?= e($eventLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="admin-filter-actions">
          <button class="btn" type="submit">Aplicar filtros</button>
          <a class="btn-ghost" href="/admin/logs?clear=1">Limpar</a>
        </div>
      </form>

      <div class="admin-log-tabs">
        <?php foreach ($scopeOptions as $scopeKey => $scopeLabel) : ?>
          <a class="admin-log-tab <?= $scopeValue === $scopeKey ? 'is-active' : ''; ?>" href="<?= e($buildScopeUrl($scopeKey)); ?>">
            <?= e($scopeLabel); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($showBan)) : ?>
      <section class="admin-log-section">
        <div class="admin-log-section-head">
          <div>
            <span class="admin-log-kicker">Segurança</span>
            <h2>Banimentos e tentativas bloqueadas</h2>
            <p>Veja quem foi banido, por qual admin, quando isso aconteceu e quais tentativas de retorno o sistema interceptou.</p>
          </div>
        </div>

        <div class="admin-log-dual">
          <article class="card admin-block admin-log-panel">
            <div class="admin-log-panel-head">
              <div>
                <h3>Banimentos aplicados</h3>
                <p>Historico oficial do ban reforcado, inclusive revogacoes.</p>
              </div>
            </div>

            <?php if (empty($banLogs)) : ?>
              <div class="admin-log-empty">
                <strong>Nenhum banimento bateu com o filtro.</strong>
                <p>Ajuste a busca ou espere novos registros entrarem no sistema.</p>
              </div>
            <?php else : ?>
              <div class="admin-log-list">
                <?php foreach ($banLogs as $ban) : ?>
                  <article class="admin-log-card">
                    <header class="admin-log-card-head">
                      <div>
                        <strong><?= e($ban['target_username'] ?? 'Conta'); ?></strong>
                        <span><?= e($ban['target_email'] ?? '-'); ?></span>
                      </div>
                      <span class="admin-log-pill <?= ($ban['status'] ?? '') === 'active' ? 'is-danger' : 'is-muted'; ?>">
                        <?= e($banStatusLabel((string) ($ban['status'] ?? 'revoked'))); ?>
                      </span>
                    </header>

                    <div class="admin-log-meta-grid">
                      <div>
                        <span>Motivo</span>
                        <strong><?= e($ban['reason'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Banido por</span>
                        <strong><?= e($ban['created_by_username'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Banido em</span>
                        <strong><?= e($formatDate($ban['created_at'] ?? null)); ?></strong>
                      </div>
                      <div>
                        <span>IP salvo</span>
                        <strong><?= e($ban['ip_address'] ?? '-'); ?></strong>
                      </div>
                    </div>

                    <div class="admin-log-inline-grid">
                      <div>
                        <span>Fingerprint</span>
                        <code><?= e($ban['fingerprint_hash'] ?? '-'); ?></code>
                      </div>
                      <div>
                        <span>Revogado por</span>
                        <strong><?= e($ban['revoked_by_username'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Revogado em</span>
                        <strong><?= e($formatDate($ban['revoked_at'] ?? null)); ?></strong>
                      </div>
                    </div>

                    <?php if (!empty($ban['note'])) : ?>
                      <p class="admin-log-note"><?= e($ban['note']); ?></p>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>

          <article class="card admin-block admin-log-panel">
            <div class="admin-log-panel-head">
              <div>
                <h3>Tentativas bloqueadas</h3>
                <p>Mostra tentativa de login, rate limit e tentativa de retorno de conta banida.</p>
              </div>
            </div>

            <?php if (empty($banAttempts)) : ?>
              <div class="admin-log-empty">
                <strong>Nenhuma tentativa recente encontrada.</strong>
                <p>Quando o sistema bloquear algo, o evento aparece aqui com IP e contexto tecnico.</p>
              </div>
            <?php else : ?>
              <div class="admin-log-list">
                <?php foreach ($banAttempts as $attempt) : ?>
                  <article class="admin-log-card">
                    <header class="admin-log-card-head">
                      <div>
                        <strong><?= e($attempt['matched_username'] ?? $attempt['username_input'] ?? $attempt['login_input'] ?? 'Tentativa sem usuario'); ?></strong>
                        <span><?= e($attempt['matched_email'] ?? $attempt['email_input'] ?? '-'); ?></span>
                      </div>
                      <span class="admin-log-pill is-warning"><?= e($attempt['action'] ?? 'evento'); ?></span>
                    </header>

                    <div class="admin-log-meta-grid">
                      <div>
                        <span>Hora</span>
                        <strong><?= e($formatDate($attempt['created_at'] ?? null)); ?></strong>
                      </div>
                      <div>
                        <span>IP</span>
                        <strong><?= e($attempt['ip_address'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Rota</span>
                        <strong><?= e($attempt['route'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Ban relacionado</span>
                        <strong><?= e($attempt['matched_ban_reason'] ?? '-'); ?></strong>
                      </div>
                    </div>

                    <div class="admin-log-inline-grid">
                      <div>
                        <span>Login informado</span>
                        <strong><?= e($attempt['login_input'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Fingerprint</span>
                        <code><?= e($attempt['fingerprint_hash'] ?? '-'); ?></code>
                      </div>
                    </div>

                    <?php if (!empty($attempt['note'])) : ?>
                      <p class="admin-log-note"><?= e($attempt['note']); ?></p>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($showMarket)) : ?>
      <section class="admin-log-section">
        <div class="admin-log-section-head">
          <div>
            <span class="admin-log-kicker">Mercado interno</span>
            <h2>Compras, vendas, transferencias e desbloqueios</h2>
            <p>Esta area mostra quem vendeu, quem comprou, para qual conta o item foi, se chegou aberto ou bloqueado e quando tudo aconteceu.</p>
          </div>
        </div>

        <article class="card admin-block admin-log-panel">
          <div class="admin-log-panel-head">
            <div>
              <h3>Auditoria do mercado</h3>
              <p>Oferta criada, recarga, venda, cancelamento, ajuste de coins e desbloqueio do item ficam registrados aqui.</p>
            </div>
          </div>

          <?php if (empty($marketLogs)) : ?>
            <div class="admin-log-empty">
              <strong>Nenhum evento do mercado bateu com o filtro.</strong>
              <p>Quando tiver compra, venda, recarga ou desbloqueio, a trilha aparece aqui.</p>
            </div>
          <?php else : ?>
            <div class="admin-log-list">
              <?php foreach ($marketLogs as $marketLog) : ?>
                <article class="admin-log-card">
                  <header class="admin-log-card-head">
                    <div>
                      <strong><?= e($marketEventLabels[$marketLog['event_type']] ?? ($marketLog['event_type'] ?? 'Evento')); ?></strong>
                      <span><?= e($marketLog['item_name_snapshot'] ?? 'Sem item vinculado'); ?></span>
                    </div>
                    <span class="admin-log-pill <?= ($marketLog['item_lock_state'] ?? '') === 'locked' ? 'is-warning' : 'is-success'; ?>">
                      <?= e($marketLockLabel((string) ($marketLog['item_lock_state'] ?? 'open'))); ?>
                    </span>
                  </header>

                  <div class="admin-log-meta-grid">
                    <div>
                      <span>Hora</span>
                      <strong><?= e($formatDate($marketLog['created_at'] ?? null)); ?></strong>
                    </div>
                    <div>
                      <span>Vendedor</span>
                      <strong><?= e($marketLog['seller_username'] ?? '-'); ?></strong>
                    </div>
                    <div>
                      <span>Comprador</span>
                      <strong><?= e($marketLog['buyer_username'] ?? '-'); ?></strong>
                    </div>
                    <div>
                      <span>Conta que recebeu</span>
                      <strong><?= e($marketLog['target_username'] ?? '-'); ?></strong>
                    </div>
                  </div>

                  <div class="admin-log-inline-grid">
                    <div>
                      <span>Item</span>
                      <strong><?= e($marketLog['item_type_snapshot'] ?? '-'); ?> · x<?= max(1, (int) ($marketLog['quantity'] ?? 1)); ?></strong>
                    </div>
                    <div>
                      <span>Preco</span>
                      <strong><?= isset($marketLog['price_coins']) && $marketLog['price_coins'] !== null ? e((string) $marketLog['price_coins']) . ' coins' : '-'; ?></strong>
                    </div>
                    <div>
                      <span>Coins</span>
                      <strong><?= isset($marketLog['coins_amount']) && $marketLog['coins_amount'] !== null ? e((string) $marketLog['coins_amount']) : '-'; ?></strong>
                    </div>
                    <div>
                      <span>Desbloqueio</span>
                      <strong><?= isset($marketLog['unlock_cost']) && $marketLog['unlock_cost'] !== null ? e((string) $marketLog['unlock_cost']) : '-'; ?></strong>
                    </div>
                    <div>
                      <span>Pedido / recarga</span>
                      <strong><?= !empty($marketLog['order_id']) ? '#' . e((string) $marketLog['order_id']) : '-'; ?></strong>
                    </div>
                    <div>
                      <span>Admin responsavel</span>
                      <strong><?= e($marketLog['admin_username'] ?? '-'); ?></strong>
                    </div>
                  </div>

                  <?php if (!empty($marketLog['amount_brl'])) : ?>
                    <p class="admin-log-note">Valor em reais: R$ <?= e(number_format((float) $marketLog['amount_brl'], 2, ',', '.')); ?></p>
                  <?php endif; ?>

                  <?php if (!empty($marketLog['note'])) : ?>
                    <p class="admin-log-note"><?= e($marketLog['note']); ?></p>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>
    <?php endif; ?>

    <?php if (!empty($showAccess)) : ?>
      <section class="admin-log-section">
        <div class="admin-log-section-head">
          <div>
            <span class="admin-log-kicker">IPs e acessos</span>
            <h2>Historico tecnico das contas</h2>
            <p>Veja todos os IPs ja usados por cada conta, inclusive cadastro inicial e logins aprovados.</p>
          </div>
        </div>

        <div class="admin-log-dual">
          <article class="card admin-block admin-log-panel">
            <div class="admin-log-panel-head">
              <div>
                <h3>IPs por conta</h3>
                <p>Resumo agrupado por usuario, IP, primeira vez vista e ultimo acesso.</p>
              </div>
            </div>

            <?php if (empty($accessIpSummary)) : ?>
              <div class="admin-log-empty">
                <strong>Nenhum IP registrado ainda.</strong>
                <p>Assim que houver cadastro ou login aprovado, o IP entra nesta lista.</p>
              </div>
            <?php else : ?>
              <div class="table-wrap">
                <table class="table admin-table admin-log-ip-table">
                  <thead>
                    <tr>
                      <th>Conta</th>
                      <th>IP</th>
                      <th>Primeira vez</th>
                      <th>Última vez</th>
                      <th>Hits</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($accessIpSummary as $ipEntry) : ?>
                      <tr>
                        <td>
                          <strong><?= e($ipEntry['target_username'] ?? 'Conta'); ?></strong><br>
                          <span class="admin-table-muted"><?= e($ipEntry['target_email'] ?? '-'); ?></span>
                        </td>
                        <td><code><?= e($ipEntry['ip_address'] ?? '-'); ?></code></td>
                        <td><?= e($formatDate($ipEntry['first_seen_at'] ?? null)); ?></td>
                        <td><?= e($formatDate($ipEntry['last_seen_at'] ?? null)); ?></td>
                        <td><?= (int) ($ipEntry['total_hits'] ?? 0); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </article>

          <article class="card admin-block admin-log-panel">
            <div class="admin-log-panel-head">
              <div>
                <h3>Acessos recentes</h3>
                <p>Lista cronologica de cadastro e login com rota, IP e fingerprint.</p>
              </div>
            </div>

            <?php if (empty($accessLogs)) : ?>
              <div class="admin-log-empty">
                <strong>Nenhum acesso encontrado.</strong>
                <p>Tente limpar os filtros ou aguarde novos acessos ao sistema.</p>
              </div>
            <?php else : ?>
              <div class="admin-log-list">
                <?php foreach ($accessLogs as $accessLog) : ?>
                  <article class="admin-log-card">
                    <header class="admin-log-card-head">
                      <div>
                        <strong><?= e($accessLog['target_username'] ?? 'Conta'); ?></strong>
                        <span><?= e($accessLog['target_email'] ?? '-'); ?></span>
                      </div>
                      <span class="admin-log-pill is-muted"><?= e($accessActionLabels[$accessLog['action']] ?? ($accessLog['action'] ?? 'Acesso')); ?></span>
                    </header>

                    <div class="admin-log-meta-grid">
                      <div>
                        <span>Hora</span>
                        <strong><?= e($formatDate($accessLog['created_at'] ?? null)); ?></strong>
                      </div>
                      <div>
                        <span>IP</span>
                        <strong><?= e($accessLog['ip_address'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Rota</span>
                        <strong><?= e($accessLog['route'] ?? '-'); ?></strong>
                      </div>
                      <div>
                        <span>Fingerprint</span>
                        <code><?= e($accessLog['fingerprint_hash'] ?? '-'); ?></code>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
        </div>
      </section>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
