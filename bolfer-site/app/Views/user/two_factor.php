<?php
$title = 'Segurança 2FA';
require __DIR__ . '/../partials/header.php';

$twoFactorEnabled = !empty($twoFactorEnabled);
$username = (string) ($currentUserProfile['username'] ?? $user['username'] ?? '');
$email = (string) ($currentUserProfile['email'] ?? $user['email'] ?? '');
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card user-security-card admin-security-card">
      <div class="user-head">
        <div>
          <p class="user-kicker">Seguran&ccedil;a da conta</p>
          <h1>App autenticador</h1>
          <p class="user-sub">Ative o 2FA com um aplicativo autenticador para proteger o login da sua conta.</p>
        </div>
        <div class="user-status">
          <span class="user-status-label">2FA</span>
          <span class="user-badge user-badge--<?= $twoFactorEnabled ? 'vip' : 'comum'; ?>">
            <?= $twoFactorEnabled ? 'Ativo' : 'Desativado'; ?>
          </span>
        </div>
      </div>

      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="user-actions-grid">
        <a class="user-action" href="/usuario">
          <strong>Voltar para minha conta</strong>
          <span>Volte para o painel principal do usu&aacute;rio.</span>
        </a>
        <a class="user-action" href="/usuario/inventario">
          <strong>Invent&aacute;rio</strong>
          <span>Acesse seus itens e desbloqueios sem sair da conta.</span>
        </a>
        <a class="user-action" href="/usuario/mercado">
          <strong>Mercado interno</strong>
          <span>Compre coins e gerencie negocia&ccedil;&otilde;es da comunidade.</span>
        </a>
      </div>

      <?php if ($twoFactorEnabled) : ?>
        <div class="admin-security-steps">
          <div class="admin-security-step">
            <strong>Status da prote&ccedil;&atilde;o</strong>
            <span>Seu login agora exige o c&oacute;digo do app autenticador ou um c&oacute;digo de recupera&ccedil;&atilde;o.</span>
          </div>
          <div class="admin-security-step">
            <strong>Conta protegida</strong>
            <span><?= e($email !== '' ? $email : $username); ?></span>
          </div>
        </div>

        <?php if (!empty($recoveryCodes)) : ?>
          <div class="admin-security-card">
            <div>
              <h2>Guarde seus c&oacute;digos de recupera&ccedil;&atilde;o</h2>
              <p class="admin-sub">Eles aparecem apenas uma vez. Salve em local seguro antes de sair desta tela.</p>
            </div>
            <div class="admin-recovery-codes">
              <?php foreach ($recoveryCodes as $recoveryCode) : ?>
                <code><?= e((string) $recoveryCode); ?></code>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else : ?>
          <div class="auth-highlight auth-highlight--soft">
            <strong>2FA j&aacute; est&aacute; ativo</strong>
            <p>Se voc&ecirc; j&aacute; guardou os c&oacute;digos de recupera&ccedil;&atilde;o, n&atilde;o precisa fazer mais nada nesta tela.</p>
          </div>
        <?php endif; ?>
      <?php else : ?>
        <div class="admin-security-steps">
          <div class="admin-security-step">
            <strong>1. Conta</strong>
            <span><?= e($email !== '' ? $email : $username); ?></span>
          </div>
          <div class="admin-security-step admin-security-step--qr">
            <strong>2. Escaneie o QR Code</strong>
            <div class="admin-qr-shell">
              <img src="<?= e((string) ($qrCodeUrl ?? '')); ?>" alt="QR Code para ativar o 2FA" loading="lazy" referrerpolicy="no-referrer">
            </div>
            <span>Use Google Authenticator, Microsoft Authenticator, Authy ou outro app compat&iacute;vel.</span>
          </div>
          <div class="admin-security-step">
            <strong>3. Chave secreta</strong>
            <code><?= e((string) ($secret ?? '')); ?></code>
          </div>
          <div class="admin-security-step">
            <strong>4. Otpauth URI</strong>
            <textarea rows="3" readonly><?= e((string) ($otpAuthUri ?? '')); ?></textarea>
          </div>
        </div>

        <form method="post" action="/usuario/2fa/setup" class="form auth-form user-security-form">
          <?= csrf_field(); ?>

          <div class="auth-field auth-field--full">
            <label>C&oacute;digo de 6 d&iacute;gitos do aplicativo autenticador</label>
            <input type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Ex.: 123456" required>
          </div>

          <button class="btn auth-submit" type="submit">Ativar 2FA</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
