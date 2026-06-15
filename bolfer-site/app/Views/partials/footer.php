  </main>
  <?php
    $settingsRepo = new App\Repositories\SettingsRepository();
    $whatsapp = $settingsRepo->get('whatsapp_link', env('WHATSAPP_LINK'));
    $discord = $settingsRepo->get('discord_link', env('DISCORD_LINK'));
    $supportHours = $settingsRepo->get('support_hours', env('SUPPORT_HOURS'));
  ?>
  <footer class="site-footer">
    <div class="container">
      <p>2026 BOLFER. Todos os direitos reservados.</p>
      <?php if (!empty($supportHours)) : ?>
        <p class="footer-support">Atendimento: <?= e($supportHours); ?></p>
      <?php endif; ?>
      <div class="footer-links">
        <a href="/termos">Termos e privacidade</a>
        <?php if (!empty($whatsapp)) : ?>
          <a href="<?= e($whatsapp); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
        <?php endif; ?>
        <?php if (!empty($discord)) : ?>
          <a href="<?= e($discord); ?>" target="_blank" rel="noopener noreferrer">Discord</a>
        <?php endif; ?>
      </div>
    </div>
  </footer>

  <?php if (!empty($whatsapp)) : ?>
    <a class="floating-btn whatsapp" href="<?= e($whatsapp); ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar no WhatsApp">W</a>
  <?php endif; ?>
  <?php if (!empty($discord)) : ?>
    <a class="floating-btn discord" href="<?= e($discord); ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar no Discord">D</a>
  <?php endif; ?>
  <script>
    (() => {
      const fingerprintInputs = document.querySelectorAll('[data-device-fingerprint-input]');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');

      postForms.forEach((form) => {
        if (!(form instanceof HTMLFormElement) || !csrfToken) {
          return;
        }

        if (form.querySelector('input[name="csrf_token"]')) {
          return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = csrfToken;
        form.appendChild(input);
      });

      const toHex = (buffer) => Array.from(new Uint8Array(buffer))
        .map((value) => value.toString(16).padStart(2, '0'))
        .join('');

      const fallbackHash = (value) => {
        let hash = 0;
        for (let index = 0; index < value.length; index += 1) {
          hash = ((hash << 5) - hash) + value.charCodeAt(index);
          hash |= 0;
        }

        const normalized = Math.abs(hash).toString(16).padStart(8, '0');
        return normalized.repeat(8).slice(0, 64);
      };

      const setFingerprint = (hash) => {
        if (!hash) {
          return;
        }

        const expiresAt = new Date(Date.now() + (180 * 24 * 60 * 60 * 1000)).toUTCString();
        const secureFlag = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `bolfer_fp=${hash}; expires=${expiresAt}; path=/; SameSite=Lax${secureFlag}`;
        fingerprintInputs.forEach((input) => {
          if (input instanceof HTMLInputElement) {
            input.value = hash;
          }
        });
      };

      const buildFingerprintSeed = () => {
        const parts = [
          navigator.userAgent || '',
          navigator.language || '',
          Intl.DateTimeFormat().resolvedOptions().timeZone || '',
          String(window.screen?.width || ''),
          String(window.screen?.height || ''),
          String(window.screen?.colorDepth || ''),
          String(navigator.hardwareConcurrency || ''),
          String(navigator.maxTouchPoints || ''),
          String(navigator.deviceMemory || ''),
          navigator.platform || '',
        ];

        return parts.join('|');
      };

      if (!window.crypto?.subtle) {
        const fallback = buildFingerprintSeed();
        if (fallback) {
          setFingerprint(fallbackHash(fallback));
        }
        return;
      }

      const seed = buildFingerprintSeed();
      const encoder = new TextEncoder();
      window.crypto.subtle.digest('SHA-256', encoder.encode(seed))
        .then((buffer) => setFingerprint(toHex(buffer)))
        .catch(() => {
          if (seed) {
            setFingerprint(fallbackHash(seed));
          }
        });
    })();
  </script>
</body>
</html>
