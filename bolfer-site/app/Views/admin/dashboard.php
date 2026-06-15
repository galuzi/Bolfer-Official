<?php
$title = 'Dashboard'; require __DIR__ . '/partials/admin_header.php'; ?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Dashboard</h1>
        <p class="admin-sub">Resumo rápido do fluxo de pedidos.</p>
      </div>
    </div>

    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')) : ?>
      <div class="alert error"><?= e($msg); ?></div>
    <?php endif; ?>

    <div class="grid">
      <div class="card">
        <strong>Pagos aguardando contato</strong>
        <p><?= (int) $paidWaiting; ?></p>
      </div>
      <div class="card">
        <strong>Em entrega</strong>
        <p><?= (int) $inDelivery; ?></p>
      </div>
      <div class="card">
        <strong>Entregues</strong>
        <p><?= (int) $delivered; ?></p>
      </div>
      <div class="card">
        <strong>2FA do painel</strong>
        <p><?= !empty($currentAdminProfile['two_factor_enabled']) ? 'Ativo' : 'Pendente'; ?></p>
      </div>
    </div>

    <?php if (!empty($twoFactorRecoveryCodes)) : ?>
      <div class="card admin-security-card" style="margin-top: 24px;">
        <div class="admin-page-header admin-page-header--compact">
          <div>
            <h2>2FA ativado com sucesso</h2>
            <p class="admin-sub">Guarde estes códigos de recuperação em local seguro. Eles aparecem apenas uma vez.</p>
          </div>
        </div>
        <div class="admin-recovery-codes">
          <?php foreach ($twoFactorRecoveryCodes as $recoveryCode) : ?>
            <code><?= e((string) $recoveryCode); ?></code>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card admin-block admin-discord-shell" style="margin-top: 24px;">
      <div class="admin-page-header admin-page-header--compact">
        <div>
          <h2>Atividade via Discord</h2>
          <p class="admin-sub">Mostre em tempo real o que a equipe esta fazendo no painel, com mensagens automaticas e manuais.</p>
        </div>
      </div>

      <div class="admin-discord-status-grid">
        <article class="admin-discord-status-card">
          <span>Status global</span>
          <strong><?= !empty($discordActivityConfig['enabled']) ? 'Ativo' : 'Desativado'; ?></strong>
          <small><?= !empty($discordActivityConfig['webhook_url']) ? 'Webhook configurado.' : 'Webhook ainda não configurado.'; ?></small>
        </article>
        <article class="admin-discord-status-card">
          <span>Seu status</span>
          <strong><?= !empty($currentAdminProfile['discord_activity_enabled']) ? 'Enviando' : 'Pausado'; ?></strong>
          <small><?= e((string) ($currentAdminProfile['discord_activity_display_name'] ?? $currentAdminProfile['username'] ?? 'Equipe Bolfer')); ?></small>
        </article>
        <article class="admin-discord-status-card">
          <span>Nome do bot</span>
          <strong><?= e((string) ($discordActivityConfig['bot_name'] ?? 'Bolfer Activity')); ?></strong>
          <small>Usado como nome visual no webhook.</small>
        </article>
      </div>

      <div class="admin-discord-grid">
        <section class="admin-discord-panel">
          <div class="admin-discord-panel-head">
            <strong>Seu perfil de atividade</strong>
            <span>Ative, pause ou mude o nome que aparece nos embeds do Discord.</span>
          </div>

          <form method="post" action="/admin/dashboard" class="form admin-discord-form">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="discord_profile">

            <label>Nome exibido no Discord</label>
            <input
              type="text"
              name="discord_activity_display_name"
              maxlength="120"
              value="<?= e((string) ($currentAdminProfile['discord_activity_display_name'] ?? '')); ?>"
              placeholder="Ex.: Bruno · Equipe Bolfer">

            <label class="checkbox-label">
              <input type="checkbox" name="discord_activity_enabled" value="1" <?= !empty($currentAdminProfile['discord_activity_enabled']) ? 'checked' : ''; ?>>
              Permitir que minhas atividades aparecam no Discord
            </label>

            <button class="btn" type="submit">Salvar status</button>
          </form>
        </section>

        <section class="admin-discord-panel">
          <div class="admin-discord-panel-head">
            <strong>Mensagem manual</strong>
            <span>Use para anunciar algo que não saiu de uma acao automatica do painel, como website, ticket ou acao de bastidor.</span>
          </div>

          <form method="post" action="/admin/dashboard" class="form admin-discord-form">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="discord_manual">

            <label>Tipo da atividade</label>
            <select name="activity_type">
              <?php foreach (($discordManualTypes ?? $discordActivityTypes) as $typeValue => $typeLabel) : ?>
                <option value="<?= e($typeValue); ?>"><?= e($typeLabel); ?></option>
              <?php endforeach; ?>
            </select>

            <label>Titulo</label>
            <input type="text" name="title" maxlength="180" placeholder="Ex.: Criando website para campanha nova">

            <label>Descricao</label>
            <textarea name="description" rows="6" maxlength="1900" placeholder="Explique de forma objetiva o que esta sendo feito e o contexto para a equipe e comunidade."></textarea>

            <button class="btn" type="submit">Enviar para o Discord</button>
          </form>
        </section>
      </div>

      <section class="admin-discord-history">
        <div class="admin-discord-panel-head">
          <strong>Últimas entregas ao Discord</strong>
          <span>Historico local para ver se enviou, falhou ou foi ignorado por webhook desligado.</span>
        </div>

        <?php if (empty($discordActivityLogs)) : ?>
          <div class="admin-log-empty">
            <strong>Nenhuma atividade registrada ainda.</strong>
            <p>Quando você enviar ou o sistema disparar algo automatico, o historico aparece aqui.</p>
          </div>
        <?php else : ?>
          <div class="admin-discord-history-list">
            <?php foreach ($discordActivityLogs as $activityLog) : ?>
              <article class="admin-discord-history-item">
                <div>
                  <strong><?= e($activityLog['title'] ?? 'Atividade'); ?></strong>
                  <span><?= e($activityLog['admin_username'] ?? 'Sistema'); ?> · <?= e($activityLog['activity_scope'] ?? 'admin'); ?> · <?= e($activityLog['activity_type'] ?? 'manual'); ?></span>
                </div>
                <div class="admin-discord-history-meta">
                  <span class="admin-log-pill <?= ($activityLog['status'] ?? '') === 'sent' ? 'is-success' : (($activityLog['status'] ?? '') === 'failed' ? 'is-danger' : 'is-warning'); ?>">
                    <?= e($activityLog['status'] ?? 'skipped'); ?>
                  </span>
                  <small><?= e((string) ($activityLog['created_at'] ?? '')); ?></small>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
