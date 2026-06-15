<?php
$title = 'Serviços';
$metaTitle = 'Serviços Bolfer Official | Equipe, suporte e projetos personalizados';
$metaDescription = 'Conheça os serviços da Bolfer Official, fale com a equipe e envie um pedido personalizado para web, design, marketing, consultoria e comunidade.';
require __DIR__ . '/partials/header.php';
$loggedUser = user_session();
?>

<section class="section services-section">
  <div class="container">
    <?php if ($msg = flash_get('error')) : ?>
      <div class="alert error"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('success')) : ?>
      <div class="alert success"><?= e($msg); ?></div>
    <?php endif; ?>

    <div class="services-hero">
      <div class="services-hero-main">
        <p class="section-kicker">Servi&ccedil;os da Comunidade</p>
        <h1 class="services-title">Servi&ccedil;os exclusivos da Bolfer</h1>
        <p class="services-intro">Conhe&ccedil;a os servi&ccedil;os oferecidos pela equipe oficial. Cada entrega &eacute; personalizada e feita por moderadores com experi&ecirc;ncia real na comunidade.</p>
        <div class="services-hero-badges">
          <span class="services-pill">Briefing personalizado</span>
          <span class="services-pill">Equipe oficial</span>
          <span class="services-pill">Entrega assistida</span>
        </div>
      </div>

      <div class="services-hero-aside">
        <div class="services-note">
          <span>Disponibilidade limitada</span>
          <strong>Atendimento direto com moderadores</strong>
        </div>

        <div class="services-quick">
          <div class="services-quick-item">
            <span>Tempo m&eacute;dio</span>
            <strong>24-72h</strong>
          </div>

          <div class="services-quick-item">
            <span>Suporte</span>
            <strong>Di&aacute;rio</strong>
          </div>

          <div class="services-quick-item">
            <span>Equipe</span>
            <strong>Moderadores oficiais</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="services-grid">
      <article class="service-card" data-tone="core">
        <h2>Cria&ccedil;&atilde;o de websites</h2>
        <p>Sites modernos, otimizados e alinhados ao seu objetivo.</p>
        <div class="service-tags">
          <span>Entrega r&aacute;pida</span>
          <span>Layout exclusivo</span>
        </div>
      </article>

      <article class="service-card" data-tone="core">
        <h2>Design digital</h2>
        <p>Identidade visual, banners e artes para destacar sua marca.</p>
        <div class="service-tags">
          <span>Arte premium</span>
          <span>Padr&atilde;o Bolfer</span>
        </div>
      </article>

      <article class="service-card" data-tone="growth">
        <h2>Marketing digital</h2>
        <p>Estrat&eacute;gias para crescer comunidade, projetos e engajamento.</p>
        <div class="service-tags">
          <span>Planejamento</span>
          <span>Campanhas</span>
        </div>
      </article>

      <article class="service-card" data-tone="growth">
        <h2>Consultoria</h2>
        <p>Diagn&oacute;stico e melhorias para elevar seu projeto ao pr&oacute;ximo n&iacute;vel.</p>
        <div class="service-tags">
          <span>An&aacute;lise detalhada</span>
          <span>Roadmap</span>
        </div>
      </article>

      <article class="service-card" data-tone="games">
        <h2>Servi&ccedil;os para jogos</h2>
        <p>Itens, suporte e configura&ccedil;&otilde;es para experi&ecirc;ncias dentro do game.</p>
        <div class="service-tags">
          <span>Entrega segura</span>
          <span>Suporte direto</span>
        </div>
      </article>

      <article class="service-card" data-tone="community">
        <h2>Gest&atilde;o de comunidade</h2>
        <p>Organiza&ccedil;&atilde;o, regras e crescimento para comunidades online.</p>
        <div class="service-tags">
          <span>Modera&ccedil;&atilde;o</span>
          <span>Eventos</span>
        </div>
      </article>
    </div>

    <div class="services-actions">
      <a class="btn-ghost" href="/#moderadores">Ver moderadores</a>
      <a class="btn" href="/#contato">Falar com a equipe</a>
    </div>

    <div class="services-cta">
      <div class="services-cta-copy">
        <p class="section-kicker">Pedido r&aacute;pido</p>
        <h2>Solicite seu servi&ccedil;o agora</h2>
        <p><?= $loggedUser ? 'Preencha os dados e nossa equipe retorna com uma proposta personalizada.' : 'Para enviar um pedido de servi&ccedil;o, primeiro entre na sua conta.'; ?></p>
      </div>

      <?php if ($loggedUser) : ?>
        <form class="service-form" method="post" action="/servicos">
          <?= csrf_field(); ?>

          <div class="service-form-grid">
            <label>
              Seu nome
              <input type="text" name="name" value="<?= e((string) ($loggedUser['username'] ?? '')); ?>" placeholder="Digite seu nome" required>
            </label>

            <label>
              Canal de contato
              <select name="channel" required>
                <option value="">Selecione</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="discord">Discord</option>
              </select>
            </label>

            <label>
              Contato
              <input type="text" name="contact" placeholder="Seu numero ou usuario" required>
            </label>

            <label>
              Servi&ccedil;o desejado
              <select name="service" required>
                <option value="">Selecione</option>
                <option value="websites">Cria&ccedil;&atilde;o de websites</option>
                <option value="design">Design digital</option>
                <option value="marketing">Marketing digital</option>
                <option value="consultoria">Consultoria</option>
                <option value="games">Servi&ccedil;os para jogos</option>
                <option value="comunidade">Gest&atilde;o de comunidade</option>
              </select>
            </label>
          </div>

          <label>
            Detalhes do pedido
            <textarea name="details" rows="4" placeholder="Conte mais sobre o que você precisa"></textarea>
          </label>

          <div class="service-form-actions">
            <button class="btn" type="submit">Enviar pedido</button>

            <div class="service-form-hint">
              <?php if (!empty($whatsapp)) : ?>
                <span>WhatsApp ativo: <?= e($whatsapp); ?></span>
              <?php endif; ?>
              <?php if (!empty($discord)) : ?>
                <span>Discord ativo: <?= e($discord); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </form>
      <?php else : ?>
        <div class="service-form auth-highlight auth-highlight--soft">
          <strong>Fa&ccedil;a login para enviar seu pedido</strong>
          <p>Para enviar um pedido de servi&ccedil;o, voc&ecirc; precisa entrar na sua conta.</p>

          <div class="service-form-actions">
            <a class="btn" href="/login">Entrar na conta</a>
            <a class="btn-ghost" href="/register">Criar conta</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
