<?php
$title = 'Confirmar 2FA';
require __DIR__ . '/../partials/header.php';
?>

<section class="section alt auth-section">
  <div class="container auth-shell">
    <div class="auth-card auth-card--primary">
      <div class="auth-card-head">
        <p class="auth-eyebrow">Segurança da conta</p>
        <h1 class="auth-title">Confirmar 2FA</h1>
        <p class="auth-sub">Digite o código do app autenticador ou um código de recuperacao para concluir o login.</p>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="auth-highlight">
        <strong>Conta em validacao</strong>
        <p><?= e((string) ($userLogin ?? '')); ?></p>
      </div>

      <form method="post" action="/2fa/verify" class="form auth-form">
        <?= csrf_field(); ?>

        <div class="auth-field auth-field--full">
          <label>Código 2FA ou código de recuperacao</label>
          <input type="text" name="two_factor_code" autocomplete="one-time-code" placeholder="Ex.: 123456 ou ABCD-EFGH-IJKL" required>
        </div>

        <button class="btn auth-submit" type="submit">Validar acesso</button>
      </form>
    </div>

    <aside class="auth-side">
      <div class="auth-side-head">
        <p class="auth-side-kicker">Autenticação adicional</p>
        <h2>Mais proteção no login</h2>
      </div>

      <div class="auth-side-stack">
        <article class="auth-side-card">
          <strong>App autenticador</strong>
          <p>Use o código gerado no seu aplicativo para concluir a entrada com mais segurança.</p>
        </article>

        <article class="auth-side-card">
          <strong>Código de recuperacao</strong>
          <p>Se estiver sem o celular, você ainda pode entrar com um código de recuperacao salvo.</p>
        </article>

        <article class="auth-side-card">
          <strong>Conta protegida</strong>
          <p>Mesmo com a senha descoberta, outra pessoa não consegue entrar sem a segunda etapa.</p>
        </article>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
