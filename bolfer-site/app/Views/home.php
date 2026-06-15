<?php
$metaTitle = 'Bolfer Official | Comunidade, produtos digitais e serviços';
$metaDescription = 'Entre na Bolfer Official para acessar produtos digitais, serviços personalizados, prêmios e uma comunidade ativa.';
$loggedUser = user_session();
$roleKey = (string) ($loggedUser['role'] ?? 'user');
$roleMap = [
  'user' => 'Membro comum',
  'vip' => 'VIP',
  'doador' => 'Doador',
  'moderador' => 'Moderador',
];
$roleLabel = $roleMap[$roleKey] ?? 'Membro comum';

require __DIR__ . '/partials/header.php'; ?>


<section class="hero" id="home">
  <div class="container hero-content">
        <?php if ($loggedUser) : ?>
      <div class="hero-user">
        <span>Olá,</span>
        <strong class="hero-user-name"><?= e($loggedUser['username'] ?? ''); ?></strong>
        <span class="hero-user-role"><?= e($roleLabel); ?></span>
        <form method="post" action="/logout">
          <?= csrf_field(); ?>
          <button class="hero-logout" type="submit">Sair</button>
        </form>
      </div>
    <?php endif; ?>
    <p class="hero-kicker">Bolfer OFFICIAL</p>
    <h1 class="hero-title">SEJA, <span class="accent">MEMBRO,</span> DA NOSSA COMUNIDADE!</h1>
    <p class="hero-quote">Conecte-se com pessoas, compartilhe habilidades, jogue, crie e construa algo incrível juntos.</p>
    <div class="hero-actions">
      <a class="btn-cta" href="https://discord.gg/TyCx9KXkrm" target="_blank" rel="noopener noreferrer" data-ga-event="select_content" data-ga-content-type="hero_cta" data-ga-item-id="juntar_se_agora" data-ga-item-name="Juntar-se agora" data-ga-location="home_hero">JUNTAR-SE AGORA!</a>
    </div>
  </div>
  <div class="hero-strip">
    <div class="strip-track">
      <div class="strip-content">
        <span>Serviços Personalizados</span>
        <span class="sep">-</span>
        <span>Comunidade ativa</span>
        <span class="sep">-</span>
        <span>Prêmios exclusivos</span>
        <span class="sep">-</span>
         <span>Suporte ativo</span>
        <span class="sep">-</span>
        <span>Serviços Personalizados</span>
        <span class="sep">-</span>
        <span>Comunidade ativa</span>
        <span class="sep">-</span>
        <span>Prêmios exclusivos</span>
        <span class="sep">-</span>
        <span>Suporte ativo</span>
        <span class="sep">-</span>
      </div>
      <div class="strip-content" aria-hidden="true">
        <span>Serviços Personalizados</span>
        <span class="sep">-</span>
        <span>Comunidade ativa</span>
        <span class="sep">-</span>
        <span>Prêmios exclusivos</span>
        <span class="sep">-</span>
        <span>Suporte ativo</span>
        <span class="sep">-</span>
        <span>Serviços Personalizados</span>
        <span class="sep">-</span>
        <span>Comunidade ativa</span>
        <span class="sep">-</span>
        <span>Prêmios exclusivos</span>
        <span class="sep">-</span>
        <span>Suporte ativo</span>
        <span class="sep">-</span>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/home_rankings.php'; ?>

<section class="section prizes-section" id="prêmios">
  <div class="container">
    <div class="prizes-header">
      <div>
        <p class="section-kicker">Prêmios Bolfer</p>
        <h2 class="section-title">Prêmios Exclusivos</h2>
        <p class="prizes-intro">Confira alguns prêmios que os membros podem conquistar. São recompensas especiais para quem participa e fortalece a comunidade.</p>
      </div>
      <div class="prizes-note">
        <h3>Coins</h3>
        <p>Obtenha coins para desbloquear todos os itens do inventário e se divertir com os prêmios.</p>
      </div>
    </div>
    <div class="prizes-grid">
      <article class="prize-card" data-tone="epic">
        <div class="prize-top">
          <div class="prize-icon">
            <img src="/assets/img/premios/nitro.webp" alt="Prêmio Nitro" loading="lazy">
          </div>
          <div>
            <h3>Nitro (1 mês)</h3>
            <p class="prize-sub">Destaque seu perfil com Discord Nitro.</p>
          </div>
        </div>
        <div class="prize-tags">
          <span class="prize-tag">Insígnia única</span>
          <span class="prize-tag prize-tag--accent">Comunidade</span>
        </div>
        <ul class="prize-list">
          <li>Perks e destaque visual</li>
          <li>Entrega digital imediata</li>
        </ul>
      </article>

      <article class="prize-card" data-tone="epic">
        <div class="prize-top">
          <div class="prize-icon">
            <img src="/assets/img/premios/jogoAAA.webp" alt="Prêmio Jogo AAA" loading="lazy">
          </div>
          <div>
            <h3>Jogo AAA</h3>
            <p class="prize-sub">Títulos premium escolhidos pela equipe.</p>
          </div>
        </div>
        <div class="prize-tags">
          <span class="prize-tag">Insígnia única</span>
          <span class="prize-tag prize-tag--accent">Raro</span>
        </div>
        <ul class="prize-list">
          <li>Catálogo rotativo</li>
          <li>Chave oficial</li>
        </ul>
      </article>

      <article class="prize-card" data-tone="rare">
        <div class="prize-top">
          <div class="prize-icon">
            <img src="/assets/img/premios/giftcard.webp" alt="Prêmio Gift Card" loading="lazy">
          </div>
          <div>
            <h3>Gift Card</h3>
            <p class="prize-sub">Crédito para Steam, PSN ou Xbox.</p>
          </div>
        </div>
        <div class="prize-tags">
          <span class="prize-tag">Insígnia única</span>
          <span class="prize-tag prize-tag--accent">Exclusivo</span>
        </div>
        <ul class="prize-list">
          <li>Resgate instantâneo</li>
          <li>Valores variados</li>
        </ul>
      </article>

      <article class="prize-card" data-tone="rare">
        <div class="prize-top">
          <div class="prize-icon">
            <img src="/assets/img/premios/skinExclusiva.webp" alt="Prêmio Skin Exclusiva" loading="lazy">
          </div>
          <div>
            <h3>Skin exclusiva</h3>
            <p class="prize-sub">Itens cosméticos para jogos populares.</p>
          </div>
        </div>
        <div class="prize-tags">
          <span class="prize-tag">Insígnia única</span>
          <span class="prize-tag prize-tag--accent">Único</span>
        </div>
        <ul class="prize-list">
          <li>Itens limitados</li>
          <li>Entrega segura</li>
        </ul>
      </article>

      <article class="prize-card" data-tone="epic">
        <div class="prize-top">
          <div class="prize-icon">
            <img src="/assets/img/premios/vip.webp" alt="Prêmio Acesso VIP" loading="lazy">
          </div>
          <div>
            <h3>Acesso VIP</h3>
            <p class="prize-sub">Canal exclusivo e benefícios internos.</p>
          </div>
        </div>
        <div class="prize-tags">
          <span class="prize-tag">Insígnia única</span>
          <span class="prize-tag prize-tag--accent">Comunidade</span>
        </div>
        <ul class="prize-list">
          <li>Vagas limitadas</li>
          <li>Benefícios especiais</li>
        </ul>
      </article>

      <article class="prize-card" data-tone="legendary">
        <div class="prize-top">
          <div class="prize-icon">
            <img src="/assets/img/premios/item.webp" alt="Prêmio Pacote de Itens" loading="lazy">
          </div>
          <div>
            <h3>Pacote de itens</h3>
            <p class="prize-sub">Moedas, boosts e extras in game.</p>
          </div>
        </div>
        <div class="prize-tags">
          <span class="prize-tag">Insígnia única</span>
          <span class="prize-tag prize-tag--accent">Última chance</span>
        </div>
        <ul class="prize-list">
          <li>Conteúdo premium</li>
          <li>Entrega combinada</li>
        </ul>
      </article>
    </div>
  </div>
</section>

<section class="section is-hidden" id="como-funciona">
  <div class="container">
    <h2 class="section-title">Como funciona</h2>
    <p class="section-sub">Compra simples e entrega garantida em poucos passos.</p>
    <div class="steps-grid">
      <div class="card step-card">
        <div class="step-number">1</div>
        <h3>Escolha o produto</h3>
        <p>Selecione a quantidade ideal e informe seu nick e contato.</p>
      </div>
      <div class="card step-card">
        <div class="step-number">2</div>
        <h3>Pagamento aprovado</h3>
        <p>Assim que o pagamento confirmar, seu pedido entra em processamento.</p>
      </div>
      <div class="card step-card">
        <div class="step-number">3</div>
        <h3>Entrega rápida</h3>
        <p>Entrega digital combinada com você no canal escolhido.</p>
      </div>
    </div>
  </div>
</section>

<section class="section is-hidden" id="produtos">
  <div class="container">
    <h2 class="section-title">Produtos</h2>
    <p class="section-sub">Escolha a quantidade ideal e finalize com segurança.</p>
    <?php if (empty($products)) : ?>
      <p>Nenhum produto disponível no momento.</p>
    <?php else : ?>
      <div class="grid">
        <?php foreach ($products as $product) : ?>
          <?php
            $stockValue = $product['stock'];
            $stockLabel = $stockValue === null ? 'ilimitado' : (string) (int) $stockValue;
            $minimumQuantity = max(1, (int) ($product['minimum_quantity'] ?? 1));
            $hasStock = ($stockValue === null || (int) $stockValue > 0) && ($stockValue === null || (int) $stockValue >= $minimumQuantity);
          ?>
          <div class="card product-card">
            <div>
              <h3><?= e($product['name']); ?></h3>
              <p><?= e($product['server_label']); ?></p>
              <p class="product-stock">Estoque: <?= e($stockLabel); ?></p>
              <?php if ($minimumQuantity > 1) : ?>
                <p class="product-stock">Compra mínima: <?= (int) $minimumQuantity; ?> unidade(s)</p>
              <?php endif; ?>
            </div>
            <div class="product-meta">
              <span class="price">R$ <?= number_format((float) $product['unit_price'], 2, ',', '.'); ?></span>
              <?php if ($hasStock) : ?>
                <a class="btn-buy" href="/produto/<?= e($product['slug']); ?>">Comprar</a>
              <?php else : ?>
                <span class="btn-buy is-disabled" aria-disabled="true">Esgotado</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section is-hidden" id="pagamentos">
  <div class="container">
    <h2 class="section-title">Métodos de pagamento</h2>
    <p class="section-sub">Opções seguras e instantâneas para você comprar.</p>
    <div class="payment-grid">
      <div class="payment-badge">
        <span class="payment-icon payment-icon--pix">
          <img src="/assets/img/pix.png" alt="Pix" loading="lazy">
        </span>
        <div>
          <strong>Pix</strong>
          <span>Instantâneo</span>
        </div>
      </div>
      <div class="payment-badge">
        <span class="payment-icon payment-icon--card">
          <img src="/assets/img/card.png" alt="Cartão" loading="lazy">
        </span>
        <div>
          <strong>Cartão</strong>
          <span>Crédito e débito</span>
        </div>
      </div>
      <div class="payment-badge">
        <span class="payment-icon payment-icon--boleto">
          <img src="/assets/img/boleto.png" alt="Boleto" loading="lazy">
        </span>
        <div>
          <strong>Boleto</strong>
          <span>Até 3 dias úteis</span>
        </div>
      </div>
      <div class="payment-badge">
        <span class="payment-icon payment-icon--mercado">
          <img src="/assets/img/mercado.png" alt="Mercado Pago" loading="lazy">
        </span>
        <div>
          <strong>Mercado Pago</strong>
          <span>Checkout seguro</span>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($categories)) : ?>
  <section class="section is-hidden" id="categorias">
    <div class="container">
      <h2 class="section-title">Categorias</h2>
      <p class="section-sub">Selecione o tipo de game ideal para o seu objetivo.</p>
      <div class="grid">
        <?php foreach ($categories as $category) : ?>
          <div class="card">
            <h3><?= e($category['name']); ?></h3>
            <p>Produtos selecionados para entrega segura.</p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section moderators-section" id="moderadores">
  <div class="container">
    <div class="moderators-header">
      <div class="moderators-heading">
        <p class="section-kicker">Equipe Bolfer</p>
        <h2 class="section-title">Conheça os Moderadores</h2>
        <p class="moderators-intro">Nossa comunidade é administrada por uma equipe dedicada de moderadores que trabalham para manter o ambiente seguro, organizado e acolhedor para todos. Cada moderador possui habilidades específicas e contribui de forma única para o crescimento da comunidade.</p>
      </div>
      <div class="moderators-panel">
        <div class="moderators-panel-line">
          <span>Equipe real</span>
          <strong><?= count($moderators ?? []); ?> moderadores ativos</strong>
        </div>
        <div class="moderators-panel-line">
          <span>Habilidades</span>
          <strong>desenvolvimento e marketing</strong>
        </div>
        <div class="moderators-panel-line">
          <span>Futuro</span>
          <strong>Serviços, comunidade e consultoria</strong>
        </div>
      </div>
    </div>
    <div class="moderators-divider" aria-hidden="true"></div>
    <div class="moderators-grid">
      <?php foreach (($moderators ?? []) as $slug => $moderator) : ?>
        <article class="moderator-card" data-tone="<?= e($moderator['tone'] ?? 'founder'); ?>" id="moderador-<?= e($slug); ?>">
          <div class="moderator-top">
            <div class="moderator-avatar">
              <img src="<?= e($moderator['avatar'] ?? '/assets/img/logo.png'); ?>" alt="Foto de <?= e($moderator['name'] ?? ''); ?>" loading="lazy">
            </div>
            <div class="moderator-identity">
              <h3><?= e($moderator['name'] ?? ''); ?></h3>
              <span class="moderator-role"><?= e($moderator['role'] ?? ''); ?></span>
            </div>
          </div>
          <p class="moderator-desc"><?= e($moderator['summary'] ?? $moderator['headline'] ?? ''); ?></p>
          <ul class="moderator-skills">
            <?php foreach (array_slice($moderator['skills'] ?? [], 0, 4) as $skill) : ?>
              <li><?= e($skill); ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="moderator-actions">
            <a class="btn-mod-outline" href="/moderadores/<?= e($slug); ?>">Ver Perfil</a>
            <a class="btn-mod" href="/servicos">Ver Serviços</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section alt is-hidden" id="faq">
  <div class="container">
    <h2 class="section-title">FAQ</h2>
    <p class="section-sub">Dúvidas rápidas antes de comprar.</p>
    <div class="faq-list">
      <details class="faq-item">
        <summary>Qual o prazo de entrega?</summary>
        <p>Normalmente entre 5 a 30 minutos após a confirmação do pagamento. Em casos raros, pode levar até 24 horas.</p>
      </details>
      <details class="faq-item">
        <summary>Como a entrega é feita?</summary>
        <p>Entrega 100% digital, combinada pelo canal de contato informado no pedido.</p>
      </details>
      <details class="faq-item">
        <summary>Posso pedir reembolso?</summary>
        <p>Sim, caso o produto não seja entregue no prazo máximo ou haja erro comprovado da loja.</p>
      </details>
      <details class="faq-item">
        <summary>Meu contato fica seguro?</summary>
        <p>Sim. Usamos seus dados apenas para confirmar e entregar o pedido.</p>
      </details>
      <details class="faq-item">
        <summary>Onde acompanho meu pedido?</summary>
        <p>Na área "Acompanhar pedido" você vê o status atualizado em tempo real.</p>
      </details>
    </div>
  </div>
</section>

<section class="section alt" id="contato">
  <div class="container contact-grid">
    <div>
      <h2 class="section-title">Contato</h2>
      <p class="section-sub">Atendimento ativo para resolver qualquer tipo de problema.</p>
      <div class="contact-actions">
        <div class="support-channels">
          <?php if (!empty($whatsapp)) : ?>
            <div class="support-channel">
              <strong>WhatsApp</strong>
              <?php if (!empty($supportHours)) : ?>
                <span><?= e($supportHours); ?></span>
              <?php endif; ?>
              <a class="btn" href="<?= e($whatsapp); ?>" target="_blank">Chamar no WhatsApp</a>
            </div>
          <?php endif; ?>
          <?php if (!empty($discord)) : ?>
            <div class="support-channel">
              <strong>Discord</strong>
              <?php if (!empty($supportHours)) : ?>
                <span><?= e($supportHours); ?></span>
              <?php endif; ?>
              <a class="btn-ghost" href="<?= e($discord); ?>" target="_blank">Chamar no Discord</a>
            </div>
          <?php endif; ?>
        </div>
        <a class="btn-ghost" href="/termos">Termos e privacidade</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
