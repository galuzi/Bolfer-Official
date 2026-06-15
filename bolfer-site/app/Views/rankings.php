<?php
$metaTitle = 'Bolfer Official | Ranking da comunidade';
$metaDescription = 'Veja quem lidera o Top Coins e o Top Donates da Bolfer Official em um ranking visual, atualizado e facil de acompanhar.';
$leaderboardsPayload = is_array($leaderboardsPayload ?? null) ? $leaderboardsPayload : [];
$leaderboardsJson = json_encode($leaderboardsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$schemaData = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Ranking Bolfer Official',
    'description' => $metaDescription,
    'url' => url('/rankings'),
];
require __DIR__ . '/partials/header.php';
?>
<div class="leaderboard-page-shell">
  <section class="leaderboard-page-hero">
    <div class="container leaderboard-page-hero-inner animate__animated animate__fadeInUp">
      <p class="section-kicker">Hall da fama</p>
      <h1 class="section-title">RANKING OFICIAL DA BOLFER</h1>
      <p class="leaderboard-page-intro">Coins no topo, doa&ccedil;&otilde;es em destaque e um p&oacute;dio que mostra r&aacute;pido quem realmente est&aacute; puxando a comunidade.</p>
      <div class="leaderboard-page-badges">
        <span>Top 10 ao vivo</span>
        <span>P&oacute;dio atualizado</span>
        <span>Leitura r&aacute;pida</span>
      </div>
    </div>
  </section>

  <section class="section leaderboard-page-section">
    <div class="container leaderboard-page-stack" data-leaderboard-root data-leaderboard-context="page" data-leaderboard-endpoint="/api/rankings?limit=10">
      <article class="leaderboard-page-board" data-leaderboard-page-board="coins"></article>
      <article class="leaderboard-page-board" data-leaderboard-page-board="donates"></article>

      <div class="leaderboard-page-footer">
        <span>Atualizado <strong data-leaderboard-updated>agora</strong></span>
        <span>Somente doa&ccedil;&otilde;es aprovadas de contas logadas entram no Top Donates.</span>
      </div>

      <script type="application/json" data-leaderboard-json><?= $leaderboardsJson !== false ? $leaderboardsJson : '{}'; ?></script>
      <noscript>
        <p class="leaderboard-noscript">Ative o JavaScript para ver o ranking completo em tempo real.</p>
      </noscript>
    </div>
  </section>
</div>
<script src="/assets/leaderboards.js" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>