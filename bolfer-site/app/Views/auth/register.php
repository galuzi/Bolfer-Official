<?php
$title = 'Cadastro';
require __DIR__ . '/../partials/header.php';
$captcha = captcha_challenge('user-register');
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">&Aacute;rea do usu&aacute;rio</p>
        <h1 class="auth-title">Criar conta</h1>
        <p class="auth-sub">Preencha seus dados, confirme o e-mail e libere sua conta do jeito certo desde o primeiro acesso.</p>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/register" class="form auth-form">
        <?= csrf_field(); ?>
        <input type="hidden" name="device_fingerprint" data-device-fingerprint-input>

        <div class="auth-field-grid">
          <div class="auth-field">
            <label>Usu&aacute;rio</label>
            <input type="text" name="username" required>
          </div>

          <div class="auth-field">
            <label>E-mail</label>
            <input type="email" name="email" required>
          </div>

          <div class="auth-field">
            <label>Senha</label>
            <input type="password" name="password" required>
          </div>

          <div class="auth-field">
            <label>Confirmar senha</label>
            <input type="password" name="password_confirm" required>
          </div>
        </div>

        <div class="auth-field auth-field--full">
          <input type="hidden" name="captcha_id" value="<?= e((string) ($captcha['id'] ?? '')); ?>">
          <label><?= e((string) ($captcha['question'] ?? 'Quanto e 2 + 2?')); ?></label>
          <input type="text" name="captcha_answer" inputmode="numeric" autocomplete="off" required>
        </div>

        <button class="btn auth-submit" type="submit">Criar conta</button>
      </form>

      <div class="auth-divider"></div>

      <div class="auth-links">
        <span>J&aacute; tem conta?</span>
        <a href="/login">Fazer login</a>
      </div>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Cria&ccedil;&atilde;o da conta</p>
        <h2>Como a libera&ccedil;&atilde;o funciona</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>1. Cadastro</strong>
          <p>Voc&ecirc; preenche os dados b&aacute;sicos e a conta j&aacute; nasce protegida no sistema.</p>
        </article>

        <article class="auth-side-card">
          <strong>2. Confirma&ccedil;&atilde;o do e-mail</strong>
          <p>O sistema envia um e-mail bonito e organizado, com bot&atilde;o de confirma&ccedil;&atilde;o e c&oacute;digo de verifica&ccedil;&atilde;o, por meio da conta no-reply@example.com.</p>
        </article>

        <article class="auth-side-card">
          <strong>3. Conta pronta</strong>
          <p>Depois da confirma&ccedil;&atilde;o, voc&ecirc; pode entrar e usar pedidos, invent&aacute;rio e mercado normalmente.</p>
        </article>
      </div>

      <div class="auth-highlight auth-highlight--soft">
        <strong>Boas pr&aacute;ticas</strong>
        <p>Use uma senha forte e procure a mensagem enviada por no-reply@example.com, inclusive na caixa de spam.</p>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
