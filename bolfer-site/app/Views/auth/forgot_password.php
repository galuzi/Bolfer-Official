<?php
$title = 'Recuperar senha';
require __DIR__ . '/../partials/header.php';
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">&Aacute;rea do usu&aacute;rio</p>
        <h1 class="auth-title">Recuperar senha</h1>
        <p class="auth-sub">Informe o e-mail da sua conta para receber o link de recupera&ccedil;&atilde;o sem sair do fluxo normal da Bolfer.</p>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/forgot-password" class="form auth-form">
        <?= csrf_field(); ?>

        <div class="auth-field auth-field--full">
          <label>E-mail da conta</label>
          <input type="email" name="email" autocomplete="email" required>
        </div>

        <button class="btn auth-submit" type="submit">Enviar link de recupera&ccedil;&atilde;o</button>
      </form>

      <div class="auth-divider"></div>

      <div class="auth-links">
        <span>Lembrou da senha?</span>
        <a href="/login">Voltar para o login</a>
      </div>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Recupera&ccedil;&atilde;o segura</p>
        <h2>Como funciona</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>1. Informe seu e-mail</strong>
          <p>Digite o mesmo e-mail usado no cadastro para iniciar a recupera&ccedil;&atilde;o da conta.</p>
        </article>

        <article class="auth-side-card">
          <strong>2. Abra a mensagem</strong>
          <p>O sistema envia um e-mail organizado pela conta no-reply@example.com com o link e o c&oacute;digo de recupera&ccedil;&atilde;o.</p>
        </article>

        <article class="auth-side-card">
          <strong>3. Crie a nova senha</strong>
          <p>Abra o link recebido, escolha a nova senha e volte a entrar normalmente na sua conta.</p>
        </article>
      </div>

      <div class="auth-highlight auth-highlight--soft">
        <strong>Importante</strong>
        <p>O link de recupera&ccedil;&atilde;o expira em <?= e((string) ($ttlMinutes ?? 60)); ?> minutos e pode chegar tamb&eacute;m na caixa de spam.</p>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
