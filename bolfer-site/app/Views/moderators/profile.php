<?php
$title = 'Moderador - ' . e($moderator['name'] ?? 'Perfil');
require __DIR__ . '/../partials/header.php';
?>

<section class="section mod-profile" data-tone="<?= e((string) ($moderator['tone'] ?? 'founder')); ?>">
  <div class="container">
    <div class="mod-hero">
      <div class="mod-avatar-wrap">
        <div class="mod-avatar">
          <img src="<?= e($moderator['avatar'] ?? '/assets/img/logo.png'); ?>" alt="Foto de <?= e($moderator['name'] ?? ''); ?>" loading="lazy">
        </div>
        <div class="mod-avatar-caption">
          <span class="mod-chip">Equipe Bolfer</span>
          <span class="mod-chip mod-chip--accent">Moderador oficial</span>
        </div>
      </div>
      <div class="mod-hero-content mod-hero-card">
        <p class="mod-kicker">Perfil oficial</p>
        <h1 class="mod-name"><?= e($moderator['name'] ?? ''); ?></h1>
        <p class="mod-role"><?= e($moderator['role'] ?? ''); ?></p>
        <div class="mod-badges">
          <span class="mod-badge">Disponível para serviços</span>
          <span class="mod-badge">Atendimento direto</span>
          <span class="mod-badge mod-badge--accent">Confiança Bolfer</span>
        </div>
        <div class="mod-divider" aria-hidden="true"></div>
        <p class="mod-headline"><?= e($moderator['headline'] ?? ''); ?></p>
        <p class="mod-bio"><?= e($moderator['bio'] ?? ''); ?></p>
        <div class="mod-actions">
          <a class="btn-ghost" href="/#moderadores">Voltar aos moderadores</a>
          <a class="btn" href="/servicos">Ver serviços</a>
        </div>
      </div>
    </div>

    <div class="mod-grid">
      <article class="mod-card">
        <h2>Habilidades</h2>
        <div class="mod-tags">
          <?php foreach (($moderator['skills'] ?? []) as $skill) : ?>
            <span class="mod-tag"><?= e($skill); ?></span>
          <?php endforeach; ?>
        </div>
      </article>

      <article class="mod-card">
        <h2>Foco atual</h2>
        <ul class="mod-list">
          <?php foreach (($moderator['focus'] ?? []) as $item) : ?>
            <li><?= e($item); ?></li>
          <?php endforeach; ?>
        </ul>
      </article>

      <article class="mod-card">
        <h2>Impacto na comunidade</h2>
        <p><?= e($moderator['impact'] ?? 'Presença ativa na comunidade, com entregas consistentes, suporte ao time e foco em evoluir a experiência dos membros.'); ?></p>
      </article>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
