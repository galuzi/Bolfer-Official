<?php
$title = 'Confirmação de e-mail';
require __DIR__ . '/../partials/header.php';
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">Verificação de e-mail</p>
        <h1 class="auth-title"><?= !empty($verificationOk) ? 'E-mail confirmado' : 'Não foi possível confirmar'; ?></h1>
        <p class="auth-sub"><?= e((string) ($verificationMessage ?? '')); ?></p>
      </div>

      <div class="auth-status-card <?= !empty($verificationOk) ? 'auth-status-card--success' : 'auth-status-card--warning'; ?>">
        <strong class="auth-status-title"><?= !empty($verificationOk) ? 'Tudo certo' : 'Verificação pendente'; ?></strong>
        <p><?= !empty($verificationOk) ? 'Seu e-mail foi confirmado e o login já está liberado.' : 'Seu acesso continua bloqueado até que a verificação seja concluída com um link ou código válido.'; ?></p>
      </div>

      <?php if (!empty($verificationOk)) : ?>
        <div class="auth-action-row">
          <a class="btn auth-submit" href="/login">Entrar na conta</a>
        </div>
      <?php else : ?>
        <div class="auth-highlight">
          <strong>Precisa de um novo envio?</strong>
          <p>Se o código expirou ou não chegou, você pode solicitar uma nova verificação enviada pela no-reply@example.com.</p>
        </div>

        <form method="post" action="/verify-email/resend" class="form auth-form">
          <?= csrf_field(); ?>

          <div class="auth-field auth-field--full">
            <label>E-mail do cadastro</label>
            <input type="email" name="email" value="<?= e((string) ($verificationEmail ?? '')); ?>" required>
          </div>

          <button class="btn auth-submit" type="submit">Reenviar e-mail</button>
        </form>

        <div class="auth-highlight auth-highlight--soft">
          <strong>Ou confirme com o código</strong>
          <p>Se você recebeu o código por e-mail, cole abaixo para validar sua conta manualmente.</p>
        </div>

        <form method="get" action="/verify-email/confirm" class="form auth-form">
          <div class="auth-field auth-field--full">
            <label>Código de verificação</label>
            <input type="text" name="token" inputmode="text" autocomplete="one-time-code" placeholder="ABCD-EFGH-IJKL" required>
          </div>

          <button class="btn auth-submit" type="submit">Confirmar código</button>
        </form>
      <?php endif; ?>

      <div class="auth-divider"></div>

      <div class="auth-links">
        <span>Voltar</span>
        <a href="<?= !empty($verificationOk) ? '/login' : '/verify-email/pending' ?>">Continuar</a>
      </div>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Status da conta</p>
        <h2>Como esse fluxo funciona</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>Cadastro protegido</strong>
          <p>A conta só fica ativa depois da verificação do e-mail.</p>
        </article>

        <article class="auth-side-card">
          <strong>Link ou código</strong>
          <p>Você pode confirmar pelo botao do e-mail ou colando o código recebido.</p>
        </article>

        <article class="auth-side-card">
          <strong>Reenvio disponível</strong>
          <p>Se precisar, você pode solicitar um novo e-mail a qualquer momento e procurar a mensagem da no-reply@example.com.</p>
        </article>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
