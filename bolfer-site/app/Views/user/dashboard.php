<?php
$title = 'Área do usuário';
require __DIR__ . '/../partials/header.php';

$roleKey = (string) ($user['role'] ?? 'user');
$roleMap = [
  'user' => 'Membro comum',
  'vip' => 'VIP',
  'doador' => 'Doador',
  'moderador' => 'Moderador',
];
$roleLabel = $roleMap[$roleKey] ?? 'Membro comum';
$roleClass = match ($roleKey) {
  'vip' => 'vip',
  'doador' => 'doador',
  'moderador' => 'moderador',
  default => 'comum',
};
$canAccessVipArea = !empty($canAccessVipArea);
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card">
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="user-head">
        <div>
          <p class="user-kicker">&Aacute;rea do usu&aacute;rio</p>
          <h1>Bem-vindo, <strong><?= e($user['username'] ?? ''); ?></strong></h1>
          <p class="user-sub">Use esta &aacute;rea para acompanhar pedidos, acessar o invent&aacute;rio e abrir o mercado interno do site.</p>
        </div>
        <div class="user-status">
          <span class="user-status-label">Status</span>
          <span class="user-badge user-badge--<?= e($roleClass); ?>"><?= e($roleLabel); ?></span>
        </div>
      </div>

      <div class="user-actions-grid">
        <a class="user-action" href="/servicos">
          <strong>Comprar servi&ccedil;os</strong>
          <span>Pacotes e ofertas dispon&iacute;veis</span>
        </a>
        <a class="user-action" href="/usuario/2fa">
          <strong>Seguran&ccedil;a 2FA</strong>
          <span><?= !empty($user['two_factor_enabled']) ? 'App autenticador ativo na sua conta' : 'Ative o app autenticador para refor&ccedil;ar seu login'; ?></span>
        </a>
        <a class="user-action" href="/usuario/inventario">
          <strong>Invent&aacute;rio</strong>
          <span>Acompanhe jogos, chaves e desbloqueios da conta</span>
        </a>
        <a class="user-action" href="/usuario/mercado">
          <strong>Mercado interno</strong>
          <span>Compre coins e negocie jogos ou chaves com outros usu&aacute;rios</span>
        </a>
        <?php if ($canAccessVipArea) : ?>
          <a class="user-action user-action--vip" href="/usuario/vip">
            <strong>Acesso VIP</strong>
            <span>Benef&iacute;cios premium, atalhos reservados e uma central exclusiva para VIP e Moderador.</span>
          </a>
        <?php endif; ?>
      </div>

      <div class="user-footer">
        <a class="btn-ghost" href="/pedido">Acompanhar pedido</a>
        <form method="post" action="/logout">
          <?= csrf_field(); ?>
          <button class="btn danger" type="submit">Sair</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>

