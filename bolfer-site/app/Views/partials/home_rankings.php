<?php
$leaderboardsPayload = is_array($leaderboardsPayload ?? null) ? $leaderboardsPayload : [];
$leaderboardsJson = json_encode($leaderboardsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="section leaderboard-home-section is-hidden" id="ranking-bolfer">
  <div class="container leaderboard-home-shell" data-leaderboard-root data-leaderboard-context="home" data-leaderboard-endpoint="/api/rankings?limit=10">
    <div class="leaderboard-home-head animate__animated animate__fadeInUp">
      <div class="leaderboard-home-copy">
        <p class="section-kicker">Ranking Bolfer</p>
        <h2 class="section-title">A COROA DO TOPO</h2>
        <p class="leaderboard-home-intro">Coins ou apoio real: o p&oacute;dio mostra, sem bagun&ccedil;a, quem est&aacute; puxando a comunidade para cima agora.</p>
      </div>

      <div class="leaderboard-home-toolbar">
        <div class="leaderboard-home-switch" role="tablist" aria-label="Alternar ranking da home">
          <button class="leaderboard-switch-btn is-active" type="button" data-leaderboard-toggle="coins">Top Coins</button>
          <button class="leaderboard-switch-btn" type="button" data-leaderboard-toggle="donates">Top Donates</button>
        </div>

        <div class="leaderboard-home-actions">
          <span class="leaderboard-updated">Atualizado <strong data-leaderboard-updated>agora</strong></span>
          <a class="btn" href="/rankings">Ver ranking completo</a>
        </div>
      </div>
    </div>

    <div class="leaderboard-home-boards">
      <div class="leaderboard-home-board is-active" data-leaderboard-home-board="coins"></div>
      <div class="leaderboard-home-board" data-leaderboard-home-board="donates"></div>
    </div>

    <script type="application/json" data-leaderboard-json><?= $leaderboardsJson !== false ? $leaderboardsJson : '{}'; ?></script>
    <noscript>
      <p class="leaderboard-noscript">Ative o JavaScript para ver o p&oacute;dio animado da Bolfer.</p>
    </noscript>
  </div>
</section>
<script src="/assets/leaderboards.js" defer></script>