<?php
$title = 'Verifique seu e-mail';
require __DIR__ . '/../partials/header.php';
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">Verificação obrigatoria</p>
        <h1 class="auth-title">Confirme seu e-mail</h1>
        <p class="auth-sub">Seu cadastro foi criado, mas a conta só e liberada depois da confirmação do e-mail.</p>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="auth-status-card auth-status-card--pending">
        <strong class="auth-status-title">Proximo passo</strong>
        <p>Abra sua caixa de entrada e use o link ou o código enviado pela no-reply@example.com<?= !empty($email) ? ' para ' . e((string) $email) : '' ?>.</p>
      </div>

      <form method="post" action="/verify-email/resend" class="form auth-form">
        <?= csrf_field(); ?>

        <div class="auth-field auth-field--full">
          <label>E-mail do cadastro</label>
          <input type="email" name="email" value="<?= e((string) ($email ?? '')); ?>" required>
        </div>

        <button class="btn auth-submit" type="submit">Reenviar e-mail</button>
      </form>

      <div class="auth-highlight">
        <strong>Confirmar com código</strong>
        <p>Se preferir, cole abaixo o código recebido no e-mail. O formato costuma vir como `ABCD-EFGH-IJKL`.</p>
      </div>

      <form method="get" action="/verify-email/confirm" class="form auth-form">
        <div class="auth-field auth-field--full">
          <label>Código de verificação</label>
          <input type="text" name="token" inputmode="text" autocomplete="one-time-code" placeholder="ABCD-EFGH-IJKL" required>
        </div>

        <button class="btn auth-submit" type="submit">Confirmar código</button>
      </form>

      <div class="auth-divider"></div>

      <div class="auth-links">
        <span>Ja confirmou?</span>
        <a href="/login">Tentar entrar</a>
      </div>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Confirmação da conta</p>
        <h2>O que você precisa saber</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>Link e código com prazo</strong>
          <p>O link e o código de verificação expiram em <?= (int) ($ttlMinutes ?? 1440); ?> minuto(s).</p>
        </article>

        <article class="auth-side-card">
          <strong>Login bloqueado até confirmar</strong>
          <p>Sem essa etapa, a conta não entra no sistema e o acesso continua fechado.</p>
        </article>

        <article class="auth-side-card">
          <strong>Reenvio simples</strong>
          <p>Se não encontrou a mensagem, você pode pedir um novo envio nesta mesma tela.</p>
        </article>
      </div>

      <div class="auth-highlight auth-highlight--soft">
        <strong>Dica</strong>
        <p>Olhe também a caixa de spam ou lixo eletronico e procure pelo remetente no-reply@example.com.</p>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
