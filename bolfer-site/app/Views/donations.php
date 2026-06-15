<?php
$donationMinAmount = isset($donationMinAmount) ? (float) $donationMinAmount : 1.0;
$donationMaxAmount = isset($donationMaxAmount) ? (float) $donationMaxAmount : 1000.0;
$donationMinLabel = number_format($donationMinAmount, 0, ',', '.');
$donationMaxLabel = number_format($donationMaxAmount, 0, ',', '.');
$donationMaxDigits = strlen((string) max(1, (int) floor($donationMaxAmount)));
$metaTitle = 'Doações Bolfer Official | Apoie a comunidade';
$metaDescription = 'Apoie a Bolfer Official com doações seguras e ajude a fortalecer a comunidade, prêmios, eventos e melhorias da plataforma.';
$title = 'Doações';
require __DIR__ . '/partials/header.php';
?>

<section class="section donations-section">
  <div class="container">
    <?php if ($msg = flash_get('error')) : ?>
      <div class="alert error"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>

    <div class="donations-hero">
      <div class="donations-hero-main">
        <p class="section-kicker">Apoie a comunidade</p>
        <h1 class="donations-title">Doações para fortalecer a Bolfer</h1>
        <p class="donations-intro">Escolha um valor rápido ou personalize. Toda doação ajuda a manter a comunidade ativa e cria novas oportunidades.</p>
        <div class="donations-hero-badges">
          <span class="donations-pill">Sem cobrança recorrente</span>
          <span class="donations-pill">Você escolhe o valor</span>
          <span class="donations-pill">Apoio direto</span>
        </div>
      </div>
      <div class="donations-hero-aside">
        <div class="donations-note">
          <span>Doação segura</span>
          <strong>Escolha o valor e combine o pagamento</strong>
        </div>
        <div class="donations-impact">
          <div class="donations-impact-item">
            <strong>Suporte ativo</strong>
            <span>Equipe pronta para ajudar</span>
          </div>
          <div class="donations-impact-item">
            <strong>Prêmios e eventos</strong>
            <span>Novas experiências para membros</span>
          </div>
          <div class="donations-impact-item">
            <strong>Melhorias constantes</strong>
            <span>Plataforma sempre evoluindo</span>
          </div>
        </div>
      </div>
    </div>

    <div class="donations-grid-header">
      <h2>Escolha um valor</h2>
      <p>Selecione um valor rápido ou personalize sua doação.</p>
    </div>

    <div class="donations-grid">
      <button class="donation-card" type="button" data-value="5">
        <span class="donation-value">R$ 5</span>
        <span class="donation-label">Apoio rápido</span>
      </button>
      <button class="donation-card is-featured" type="button" data-value="20">
        <span class="donation-badge">Recomendado</span>
        <span class="donation-value">R$ 20</span>
        <span class="donation-label">Força a comunidade</span>
      </button>
      <button class="donation-card" type="button" data-value="250">
        <span class="donation-value">R$ 250</span>
        <span class="donation-label">Patrocínio especial</span>
      </button>
      <div class="donation-card donation-custom">
        <label for="donationCustom">Personalizado</label>
        <div class="donation-input">
          <span>R$</span>
          <input id="donationCustom" type="text" inputmode="numeric" autocomplete="off" maxlength="<?= e((string) $donationMaxDigits); ?>" data-max-digits="<?= e((string) $donationMaxDigits); ?>" placeholder="Digite o valor">
        </div>
        <small>Escolha um valor personalizado para apoiar a comunidade.</small>
      </div>
    </div>

    <div class="donations-summary" id="donationSummary">
      <div>
        <span>Valor selecionado</span>
        <strong id="donationSelected">R$ --</strong>
      </div>
      <p id="donationMessage">Selecione um valor para continuar com a doação.</p>
    </div>

    <div class="donations-actions">
      <form method="post" action="/doacoes" id="donationForm">
        <?= csrf_field(); ?>
        <input type="hidden" name="amount" id="donationAmount" value="">
        <button class="btn" id="donationAction" type="submit">Doar agora</button>
      </form>
      <a class="btn-ghost" href="/#contato">Falar com a equipe</a>
    </div>

    <p class="donations-footnote">Ao clicar em doar, você será redirecionado ao Mercado Pago para concluir o pagamento com segurança.</p>
  </div>
</section>

<script>
  (function () {
    const minAmount = <?= json_encode($donationMinAmount); ?>;
    const maxAmount = <?= json_encode($donationMaxAmount); ?>;
    const maxDigits = <?= json_encode($donationMaxDigits); ?>;
    const cards = document.querySelectorAll('.donation-card[data-value]');
    const summary = document.getElementById('donationSummary');
    const summaryValue = document.getElementById('donationSelected');
    const summaryMessage = document.getElementById('donationMessage');
    const customCard = document.querySelector('.donation-custom');
    const custom = document.getElementById('donationCustom');
    const action = document.getElementById('donationAction');
    const form = document.getElementById('donationForm');
    const hiddenAmount = document.getElementById('donationAmount');
    let selected = null;

    const format = (value) => `R$ ${Number(value).toLocaleString('pt-BR')}`;
    const sanitizeDigits = (value) => String(value).replace(/\D+/g, '').slice(0, maxDigits);
    const parseValue = (value) => Number(sanitizeDigits(value));

    const updateSummary = (value) => {
      if (!summary || !summaryValue || !summaryMessage) {
        return;
      }

      if (!value) {
        summary.classList.remove('is-active');
        if (summaryValue) summaryValue.textContent = 'R$ --';
        if (summaryMessage) summaryMessage.textContent = 'Selecione um valor para continuar com a doação.';
        if (action) action.classList.remove('is-ready');
        if (hiddenAmount) hiddenAmount.value = '';
        return;
      }

      const numericValue = parseValue(value);

      if (!Number.isFinite(numericValue) || numericValue < minAmount) {
        summary.classList.remove('is-active');
        if (summaryValue) summaryValue.textContent = 'R$ --';
        if (summaryMessage) summaryMessage.textContent = `O valor mínimo para doar é ${format(minAmount)}.`;
        if (action) action.classList.remove('is-ready');
        if (hiddenAmount) hiddenAmount.value = '';
        return;
      }

      if (numericValue > maxAmount) {
        summary.classList.remove('is-active');
        if (summaryValue) summaryValue.textContent = 'R$ --';
        if (summaryMessage) summaryMessage.textContent = `O valor máximo permitido no personalizado é ${format(maxAmount)}.`;
        if (action) action.classList.remove('is-ready');
        if (hiddenAmount) hiddenAmount.value = '';
        return;
      }

      summary.classList.add('is-active');
      if (summaryValue) summaryValue.textContent = format(numericValue);
      if (summaryMessage) summaryMessage.textContent = 'Seu apoio gera impacto real na comunidade.';
      if (action) action.classList.add('is-ready');
      if (hiddenAmount) hiddenAmount.value = String(numericValue);
    };

    const clearActive = () => {
      cards.forEach((card) => card.classList.remove('is-active'));
      if (customCard) customCard.classList.remove('is-active');
    };

    cards.forEach((card) => {
      card.addEventListener('click', () => {
        clearActive();
        card.classList.add('is-active');
        selected = card.getAttribute('data-value');
        if (custom) {
          custom.value = '';
        }
        updateSummary(selected);
      });
    });

    if (custom) {
      custom.addEventListener('input', () => {
        custom.value = sanitizeDigits(custom.value);
        clearActive();
        selected = custom.value ? custom.value : null;
        if (customCard && selected) {
          customCard.classList.add('is-active');
        }
        updateSummary(selected);
      });
    }

    updateSummary(null);

    if (form) {
      form.addEventListener('submit', (event) => {
        const value = selected || (custom ? custom.value : '');
        const numericValue = parseValue(value);
        if (!value || !Number.isFinite(numericValue) || numericValue < minAmount || numericValue > maxAmount) {
          event.preventDefault();
          alert(`Escolha um valor válido para doar entre ${format(minAmount)} e ${format(maxAmount)}.`);
          return;
        }
      });
    }
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
