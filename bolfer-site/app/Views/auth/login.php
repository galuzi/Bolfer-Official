<?php
$title = 'Login';
require __DIR__ . '/../partials/header.php';
$captcha = captcha_challenge('user-login');
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">&Aacute;rea do usu&aacute;rio</p>
        <h1 class="auth-title">Entrar</h1>
        <p class="auth-sub">Acesse sua conta para acompanhar pedidos, invent&aacute;rio e o mercado interno em um s&oacute; lugar.</p>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <form method="post" action="/login" class="form auth-form">
        <?= csrf_field(); ?>
        <input type="hidden" name="device_fingerprint" data-device-fingerprint-input>

        <div class="auth-field auth-field--full">
          <label>E-mail ou usu&aacute;rio</label>
          <input type="text" name="login" required>
        </div>

        <div class="auth-field auth-field--full">
          <label>Senha</label>
          <input type="password" name="password" required>
        </div>

        <div class="auth-field auth-field--full">
          <input type="hidden" name="captcha_id" value="<?= e((string) ($captcha['id'] ?? '')); ?>">
          <label><?= e((string) ($captcha['question'] ?? 'Quanto e 2 + 2?')); ?></label>
          <input type="text" name="captcha_answer" inputmode="numeric" autocomplete="off" required>
        </div>

        <button class="btn auth-submit" type="submit">Entrar na conta</button>
      </form>

      <div class="auth-links">
        <span>Esqueceu sua senha?</span>
        <a href="/forgot-password">Recuperar acesso</a>
      </div>

      <div class="auth-divider"></div>

      <div class="auth-links">
        <span>Sem conta?</span>
        <a href="/register">Criar cadastro</a>
      </div>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Acesso seguro</p>
        <h2>O que voc&ecirc; encontra aqui</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>Pedidos e hist&oacute;rico</strong>
          <p>Acompanhe compras, entregas e atualiza&ccedil;&otilde;es da sua conta sem ficar perdido entre v&aacute;rias telas.</p>
        </article>

        <article class="auth-side-card">
          <strong>Invent&aacute;rio e mercado</strong>
          <p>Gerencie itens, chaves, coins e tudo o que faz parte do ecossistema interno da Bolfer.</p>
        </article>

        <article class="auth-side-card">
          <strong>Conta protegida</strong>
          <p>Seu acesso passa por verifica&ccedil;&atilde;o de e-mail, CAPTCHA e as camadas de seguran&ccedil;a do sistema.</p>
        </article>
      </div>

      <div class="auth-highlight auth-highlight--soft">
        <strong>Importante</strong>
        <p>O login s&oacute; &eacute; liberado depois da verifica&ccedil;&atilde;o do e-mail. Se precisar, voc&ecirc; tamb&eacute;m pode recuperar a senha por e-mail.</p>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
