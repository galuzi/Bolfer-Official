<?php
$title = 'Nova senha';
require __DIR__ . '/../partials/header.php';
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">&Aacute;rea do usu&aacute;rio</p>
        <h1 class="auth-title">Redefinir senha</h1>
        <p class="auth-sub">Cole o c&oacute;digo de recupera&ccedil;&atilde;o ou use o link recebido por e-mail para escolher sua nova senha com seguran&ccedil;a.</p>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <?php if (!empty($tokenChecked) && empty($resetTokenValid)) : ?>
        <div class="alert error"><?= e((string) ($resetTokenMessage ?? 'Este link de recuperação não é mais válido.')); ?></div>
      <?php endif; ?>

      <form method="post" action="/forgot-password/reset" class="form auth-form">
        <?= csrf_field(); ?>

        <div class="auth-field auth-field--full">
          <label>C&oacute;digo de recupera&ccedil;&atilde;o</label>
          <input type="text" name="token" value="<?= e((string) ($resetToken ?? '')); ?>" autocomplete="one-time-code" spellcheck="false" required>
        </div>

        <div class="auth-field-grid">
          <div class="auth-field">
            <label>Nova senha</label>
            <input type="password" name="password" autocomplete="new-password" required>
          </div>

          <div class="auth-field">
            <label>Confirmar nova senha</label>
            <input type="password" name="password_confirm" autocomplete="new-password" required>
          </div>
        </div>

        <button class="btn auth-submit" type="submit">Salvar nova senha</button>
      </form>

      <div class="auth-divider"></div>

      <div class="auth-links">
        <span>Precisa de um novo link?</span>
        <a href="/forgot-password">Pedir nova recupera&ccedil;&atilde;o</a>
      </div>

      <div class="auth-links">
        <span>Voltar ao acesso</span>
        <a href="/login">Entrar na conta</a>
      </div>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Nova senha</p>
        <h2>O que voc&ecirc; precisa saber</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>C&oacute;digo ou link</strong>
          <p>O link recebido por e-mail j&aacute; preenche o c&oacute;digo automaticamente, mas voc&ecirc; tamb&eacute;m pode colar o c&oacute;digo manualmente.</p>
        </article>

        <article class="auth-side-card">
          <strong>Nova senha forte</strong>
          <p>Use pelo menos 8 caracteres e escolha uma combina&ccedil;&atilde;o que voc&ecirc; realmente consiga guardar sem repetir em todo lugar.</p>
        </article>

        <article class="auth-side-card">
          <strong>Sess&otilde;es antigas</strong>
          <p>Quando a senha &eacute; redefinida, as sess&otilde;es anteriores da conta deixam de valer para proteger o acesso.</p>
        </article>
      </div>

      <div class="auth-highlight auth-highlight--soft">
        <strong>Validade do link</strong>
        <p>O c&oacute;digo de recupera&ccedil;&atilde;o expira em <?= e((string) ($ttlMinutes ?? 60)); ?> minutos. Se passar desse tempo, basta pedir um novo envio.</p>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>

