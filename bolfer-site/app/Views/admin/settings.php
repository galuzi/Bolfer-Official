<?php
$title = 'Configuracoes';
require __DIR__ . '/partials/admin_header.php';
?>

<section class="section">
  <div class="container">
    <div class="admin-page-header">
      <div>
        <h1>Configuracoes</h1>
        <p class="admin-sub">Canais do site e controles basicos do mercado interno.</p>
      </div>
    </div>

    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/settings" class="form">
      <?= csrf_field(); ?>
      <label>WhatsApp (link)</label>
      <input type="text" name="whatsapp_link" value="<?= e((string) $whatsapp); ?>">

      <label>Discord (link)</label>
      <input type="text" name="discord_link" value="<?= e((string) $discord); ?>">

      <label>Horario de atendimento</label>
      <input type="text" name="support_hours" value="<?= e((string) $supportHours); ?>" placeholder="Ex: Seg a Dom, 09h as 22h">

      <label>Preco mínimo por oferta no mercado</label>
      <input type="number" name="market_listing_min_price" min="100" value="<?= e((string) $marketListingMinPrice); ?>">

      <label>Coins por R$ 1</label>
      <input type="number" name="market_coin_rate" min="1" step="1" value="<?= e((string) $marketCoinRate); ?>">

      <label>Recarga mínima em R$</label>
      <input type="number" name="market_topup_min_brl" min="5" max="50" step="1" value="<?= e((string) $marketTopupMinBrl); ?>">

      <label>Recarga maxima em R$</label>
      <input type="number" name="market_topup_max_brl" min="5" max="50" step="1" value="<?= e((string) $marketTopupMaxBrl); ?>">

      <hr>

      <label class="checkbox-label">
        <input type="checkbox" name="discord_activity_enabled" value="1" <?= (string) $discordActivityEnabled === '1' ? 'checked' : ''; ?> <?= !empty($isFullAdmin) ? '' : 'disabled'; ?>>
        Ativar atividade automatica no Discord
      </label>

      <label>Webhook de atividade do Discord</label>
      <input type="text" name="discord_activity_webhook_url" value="<?= e((string) $discordActivityWebhookUrl); ?>" <?= !empty($isFullAdmin) ? '' : 'disabled'; ?>>

      <label>Nome do bot/embed</label>
      <input type="text" name="discord_activity_bot_name" maxlength="80" value="<?= e((string) $discordActivityBotName); ?>" <?= !empty($isFullAdmin) ? '' : 'disabled'; ?>>

      <label>Rodape do embed</label>
      <input type="text" name="discord_activity_footer" maxlength="180" value="<?= e((string) $discordActivityFooter); ?>" <?= !empty($isFullAdmin) ? '' : 'disabled'; ?>>

      <?php if (empty($isFullAdmin)) : ?>
        <p class="admin-sub" style="margin-top: 8px;">Somente admins principais podem alterar a integracao global com Discord. Staff continua podendo enviar atividade manual e automatica se o status pessoal estiver ativo.</p>
      <?php endif; ?>

      <button class="btn" type="submit">Salvar</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
