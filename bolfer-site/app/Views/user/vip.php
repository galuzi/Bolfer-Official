<?php
$title = 'Área VIP';
require __DIR__ . '/../partials/header.php';

$roleKey = strtolower(trim((string) ($user['role'] ?? 'user')));
$isModerator = $roleKey === 'moderador';
$roleLabel = $isModerator ? 'Moderador' : 'VIP';
$discordLink = trim((string) env('DISCORD_LINK', ''));
$twoFactorEnabled = !empty($user['two_factor_enabled']);

$heroTags = [
    'Acesso restrito',
    $isModerator ? 'Rotina operacional' : 'Benefícios premium',
    $twoFactorEnabled ? '2FA ativo' : '2FA recomendado',
];

$heroStats = [
    [
        'label' => 'Nível liberado',
        'value' => $roleLabel,
        'hint' => $isModerator ? 'Faixa com acesso de operação e comunidade.' : 'Faixa premium com acesso reservado e comunicação prioritária.',
    ],
    [
        'label' => 'Canal direto',
        'value' => $discordLink !== '' ? 'Discord ativo' : 'Pendente',
        'hint' => $discordLink !== '' ? 'Canal reservado pronto para avisos e alinhamentos.' : 'Defina o link do Discord para abrir o canal exclusivo aqui.',
    ],
    [
        'label' => 'Segurança',
        'value' => $twoFactorEnabled ? '2FA ativo' : 'Reforçar conta',
        'hint' => $twoFactorEnabled ? 'Sua conta já está com camada extra de proteção.' : 'Ative o autenticador para proteger um acesso que vale mais.',
    ],
];

$valueCards = $isModerator
    ? [
        [
            'eyebrow' => 'Operação',
            'title' => 'Fila clara e foco no que importa',
            'text' => 'A área do cargo precisa diminuir atrito: menos cliques, leitura rápida e uma rotina que ajude a cuidar de pedidos, usuários e entregas com calma.',
        ],
        [
            'eyebrow' => 'Auditoria',
            'title' => 'Histórico confiável e decisão segura',
            'text' => 'Moderação boa depende de contexto. O ideal é ter registros, responsável, horário e motivo para qualquer ação sensível.',
        ],
        [
            'eyebrow' => 'Entrega',
            'title' => 'Controle real de fluxo e prioridade',
            'text' => 'Pedidos urgentes, casos travados e itens pendentes precisam aparecer primeiro para a equipe agir com mais velocidade.',
        ],
        [
            'eyebrow' => 'Comunicação',
            'title' => 'Alinhamento interno sem ruído',
            'text' => 'Canal privado, avisos curtos e padrões de atendimento ajudam a manter o time consistente mesmo quando há troca de turno.',
        ],
    ]
    : [
        [
            'eyebrow' => 'Exclusividade',
            'title' => 'Ofertas que fazem sentido para VIP',
            'text' => 'Acesso antecipado, janelas de compra reservadas e campanhas com condições melhores criam um motivo real para permanecer com o cargo.',
        ],
        [
            'eyebrow' => 'Retenção',
            'title' => 'Benefícios que voltam todo mês',
            'text' => 'Drops, cashback em coins, alertas de reposição e prioridades em lançamentos ajudam o VIP a sentir valor contínuo.',
        ],
        [
            'eyebrow' => 'Atendimento',
            'title' => 'Suporte mais direto e organizado',
            'text' => 'Canal próprio e comunicados dedicados evitam fila pública e deixam a experiência mais premium, rápida e pessoal.',
        ],
        [
            'eyebrow' => 'Comunidade',
            'title' => 'Espaço reservado para quem realmente participa',
            'text' => 'Uma área com anúncios, calendário e próximas ondas de benefício fortalece pertencimento e aumenta a permanência.',
        ],
    ];

$quickLinks = [
    [
        'href' => '/usuario/2fa',
        'title' => 'Segurança da conta',
        'text' => $twoFactorEnabled
            ? 'Seu autenticador já está ligado. Revise a segurança sempre que trocar de dispositivo.'
            : 'Ative o app autenticador para proteger um acesso premium e reduzir risco de invasão.',
        'tone' => $twoFactorEnabled ? 'good' : 'warn',
    ],
    [
        'href' => '/pedido',
        'title' => 'Acompanhar pedidos',
        'text' => 'Consulte entregas, acompanhe andamento e centralize tudo em um fluxo mais claro.',
        'tone' => 'default',
    ],
    [
        'href' => '/usuario/inventario',
        'title' => 'Inventário',
        'text' => 'Veja itens, chaves, desbloqueios e acompanhe o que já está liberado para a sua conta.',
        'tone' => 'default',
    ],
    [
        'href' => '/usuario/mercado',
        'title' => 'Mercado interno',
        'text' => 'Compre, acompanhe coins e use o mercado como extensão natural da sua área restrita.',
        'tone' => 'default',
    ],
];

if ($discordLink !== '') {
    array_unshift($quickLinks, [
        'href' => $discordLink,
        'title' => 'Canal reservado',
        'text' => 'Abra o Discord da comunidade com o atalho direto desta área exclusiva.',
        'tone' => 'highlight',
        'external' => true,
    ]);
}

$roleTrack = $isModerator
    ? [
        [
            'step' => '01',
            'title' => 'Entrar com conta protegida',
            'text' => 'Mantenha 2FA ativo e evite operar sem autenticação reforçada quando o cargo tiver acesso a informações sensíveis.',
        ],
        [
            'step' => '02',
            'title' => 'Centralizar pedidos e usuários',
            'text' => 'A ideia desta faixa é operar com ordem: ver prioridade, registrar motivo e tratar cada caso com histórico e contexto.',
        ],
        [
            'step' => '03',
            'title' => 'Documentar e repassar com clareza',
            'text' => 'Toda ação importante deve ficar fácil de revisar. Isso reduz erro, acelera troca de turno e protege a equipe.',
        ],
    ]
    : [
        [
            'step' => '01',
            'title' => 'Entrar na fila premium',
            'text' => 'Use esta área para acompanhar comunicados, benefícios sazonais e tudo que chegar primeiro para contas VIP.',
        ],
        [
            'step' => '02',
            'title' => 'Aproveitar os acessos reservados',
            'text' => 'Mercado, inventário e pedidos ficam mais fáceis quando reunidos em uma área com atalhos e leitura rápida.',
        ],
        [
            'step' => '03',
            'title' => 'Manter o acesso valendo a pena',
            'text' => 'A experiência premium deve entregar vantagem recorrente: reposição, ofertas reservadas e suporte mais direto.',
        ],
    ];

$accessRules = [
    'A liberação continua protegida no backend: se o cargo sair de VIP ou Moderador, a área volta a ser bloqueada automaticamente.',
    'A segurança da conta vale ainda mais aqui. Use senha forte e 2FA sempre que possível para reduzir risco de acesso indevido.',
    'Canal privado, benefícios e comunicados devem continuar organizados, com leitura simples e sem excesso de ruído.',
];
?>

<section class="section alt user-section">
  <div class="container">
    <div class="card user-card vip-access-page">
      <?php if ($msg = flash_get('error')) : ?>
        <div class="alert error"><?= e($msg); ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('success')) : ?>
        <div class="alert success"><?= e($msg); ?></div>
      <?php endif; ?>

      <div class="user-head vip-access-head">
        <div class="vip-access-copy">
          <p class="user-kicker">Área restrita</p>
          <h1><?= $isModerator ? 'Central do moderador' : 'Central VIP'; ?> de <strong><?= e($user['username'] ?? ''); ?></strong></h1>
          <p class="user-sub">
            <?= $isModerator
                ? 'Esta página organiza o que faz diferença para uma rotina de moderação segura: comunicação reservada, fluxo claro, contexto rápido e operação com menos atrito.'
                : 'Esta página concentra o que faz o acesso premium valer a pena: benefícios mais claros, canal reservado, prioridade na comunicação e uma experiência com cara de área exclusiva.'; ?>
          </p>

          <div class="vip-access-tags">
            <?php foreach ($heroTags as $tag) : ?>
              <span class="user-badge user-badge--<?= e($isModerator ? 'moderador' : 'vip'); ?>"><?= e($tag); ?></span>
            <?php endforeach; ?>
          </div>

          <div class="vip-access-stat-grid">
            <?php foreach ($heroStats as $stat) : ?>
              <article class="vip-access-stat-card">
                <span><?= e($stat['label']); ?></span>
                <strong><?= e($stat['value']); ?></strong>
                <small><?= e($stat['hint']); ?></small>
              </article>
            <?php endforeach; ?>
          </div>
        </div>

        <aside class="vip-access-panel">
          <span class="user-status-label">Status liberado</span>
          <strong><?= e($roleLabel); ?></strong>
          <p>
            <?= $isModerator
                ? 'Seu cargo atual permite entrar nesta área reservada para alinhar operação, fluxo e comunicação interna sem depender de convite manual.'
                : 'Seu cargo atual libera esta área reservada para concentrar benefícios premium, comunicados exclusivos e acessos especiais em um só lugar.'; ?>
          </p>
          <div class="vip-access-panel-actions">
            <?php if ($discordLink !== '') : ?>
              <a class="btn" href="<?= e($discordLink); ?>" target="_blank" rel="noopener noreferrer">Abrir canal reservado</a>
            <?php endif; ?>
            <a class="btn-ghost" href="/usuario/2fa"><?= $twoFactorEnabled ? 'Revisar segurança' : 'Ativar 2FA'; ?></a>
          </div>
        </aside>
      </div>

      <div class="vip-access-layout">
        <section class="vip-access-block vip-access-block--wide">
          <div class="vip-access-block-head">
            <p>Valor do acesso</p>
            <h2><?= $isModerator ? 'O que realmente precisa existir para moderação' : 'O que realmente faz o VIP valer a pena'; ?></h2>
          </div>

          <div class="vip-value-grid">
            <?php foreach ($valueCards as $card) : ?>
              <article class="vip-value-card">
                <span><?= e($card['eyebrow']); ?></span>
                <strong><?= e($card['title']); ?></strong>
                <p><?= e($card['text']); ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="vip-access-block">
          <div class="vip-access-block-head">
            <p>Acessos rapidos</p>
            <h2>Atalhos que deixam a rotina mais leve</h2>
          </div>

          <div class="vip-link-list">
            <?php foreach ($quickLinks as $link) : ?>
              <a
                class="vip-link-card vip-link-card--<?= e($link['tone'] ?? 'default'); ?>"
                href="<?= e($link['href']); ?>"
                <?= !empty($link['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
              >
                <strong><?= e($link['title']); ?></strong>
                <span><?= e($link['text']); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="vip-access-block">
          <div class="vip-access-block-head">
            <p><?= $isModerator ? 'Playbook' : 'Fluxo recomendado'; ?></p>
            <h2><?= $isModerator ? 'Como operar com mais calma e responsabilidade' : 'Como aproveitar melhor o seu acesso'; ?></h2>
          </div>

          <div class="vip-track-list">
            <?php foreach ($roleTrack as $item) : ?>
              <article class="vip-track-step">
                <span><?= e($item['step']); ?></span>
                <strong><?= e($item['title']); ?></strong>
                <p><?= e($item['text']); ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="vip-access-block vip-access-block--wide">
          <div class="vip-access-block-head">
            <p>Proteção do acesso</p>
            <h2>Regras que mantêm a área confiável</h2>
          </div>

          <ul class="vip-rule-list">
            <?php foreach ($accessRules as $rule) : ?>
              <li><?= e($rule); ?></li>
            <?php endforeach; ?>
          </ul>

          <div class="user-footer vip-access-actions">
            <a class="btn-ghost" href="/usuario">Voltar para minha conta</a>
            <a class="btn-ghost" href="/usuario/inventario">Abrir inventário</a>
            <a class="btn-ghost" href="/usuario/mercado">Abrir mercado</a>
          </div>
        </section>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
