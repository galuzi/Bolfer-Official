(() => {
  const roots = Array.from(document.querySelectorAll('[data-leaderboard-root]'));
  if (!roots.length) {
    return;
  }

  const boardMeta = {
    coins: {
      title: 'Top Coins',
      intro: 'Quem est\u00e1 acumulando mais saldo no mercado interno agora.',
      leaderLabel: 'Rei do cofre',
      pageKicker: 'Saldo em destaque',
      pageTag: 'Coins acumuladas',
      restTitle: 'Posi\u00e7\u00f5es 4 a 10',
      restIntro: 'Quem segue encostado no p\u00f3dio.',
      emptyTitle: 'O Top Coins ainda est\u00e1 vazio',
      emptyMessage: 'Assim que os membros acumularem saldo no mercado interno, o p\u00f3dio aparece aqui.',
    },
    donates: {
      title: 'Top Donates',
      intro: 'Quem mais fortalece a Bolfer com apoio real.',
      leaderLabel: 'Coroa do apoio',
      pageKicker: 'Apoio confirmado',
      pageTag: 'Doa\u00e7\u00f5es aprovadas',
      restTitle: 'Posi\u00e7\u00f5es 4 a 10',
      restIntro: 'Quem segue puxando a comunidade para cima.',
      emptyTitle: 'O Top Donates ainda est\u00e1 vazio',
      emptyMessage: 'As primeiras doa\u00e7\u00f5es aprovadas de contas logadas entram aqui automaticamente.',
    },
  };

  const podiumOrder = [1, 0, 2];

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const placeLabel = (rank) => {
    switch (rank) {
      case 1:
        return 'Primeiro lugar';
      case 2:
        return 'Segundo lugar';
      case 3:
        return 'Terceiro lugar';
      default:
        return 'No p\u00f3dio';
    }
  };

  const medalChip = (entry) => `<span class="leaderboard-medal leaderboard-medal--${escapeHtml(entry.medal || 'neon')}">${escapeHtml(entry.medal_label || 'Top')}</span>`;

  const emptyState = (title, message) => `
    <div class="leaderboard-empty animate__animated animate__fadeIn">
      <strong>${escapeHtml(title)}</strong>
      <p>${escapeHtml(message)}</p>
    </div>
  `;

  const buildCrown = () => `
    <div class="leaderboard-crown leaderboard-crown--emblem" aria-hidden="true">
      <span class="leaderboard-crown-ring leaderboard-crown-ring--outer"></span>
      <span class="leaderboard-crown-ring leaderboard-crown-ring--inner"></span>
      <svg class="leaderboard-crown-emblem" viewBox="0 0 220 150" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="crownGoldMain" x1="110" y1="18" x2="110" y2="132" gradientUnits="userSpaceOnUse">
            <stop stop-color="#FFF8D5"/>
            <stop offset="0.34" stop-color="#FFD95C"/>
            <stop offset="0.72" stop-color="#E0A61A"/>
            <stop offset="1" stop-color="#9C5C00"/>
          </linearGradient>
          <linearGradient id="crownGoldSoft" x1="41" y1="52" x2="179" y2="117" gradientUnits="userSpaceOnUse">
            <stop stop-color="#FFF6CC"/>
            <stop offset="0.45" stop-color="#F7C845"/>
            <stop offset="1" stop-color="#A96500"/>
          </linearGradient>
          <linearGradient id="crownBlueGem" x1="110" y1="66" x2="110" y2="108" gradientUnits="userSpaceOnUse">
            <stop stop-color="#F5FFFF"/>
            <stop offset="0.36" stop-color="#90F0FF"/>
            <stop offset="0.72" stop-color="#2DB9FF"/>
            <stop offset="1" stop-color="#154DFF"/>
          </linearGradient>
        </defs>
        <path d="M30 98L66 59L88 98L108 24L132 98L154 58L190 98L176 124Q110 148 44 124L30 98Z" fill="url(#crownGoldMain)" stroke="#FFF0B3" stroke-width="3" stroke-linejoin="round"/>
        <path d="M40 103Q110 126 180 103L174 122Q110 141 46 122L40 103Z" fill="url(#crownGoldSoft)" stroke="#FFE39C" stroke-width="2"/>
        <path d="M63 61L78 79" stroke="#FFF0B3" stroke-width="4" stroke-linecap="round" opacity="0.7"/>
        <path d="M157 61L142 79" stroke="#FFF0B3" stroke-width="4" stroke-linecap="round" opacity="0.7"/>
        <path d="M109 28L109 74" stroke="#FFF0B3" stroke-width="4" stroke-linecap="round" opacity="0.75"/>
        <path d="M58 88Q110 58 162 88" stroke="#FFF2BE" stroke-width="3" stroke-linecap="round" opacity="0.34"/>
        <rect x="95" y="78" width="30" height="30" rx="9" transform="rotate(45 95 78)" fill="url(#crownBlueGem)" stroke="#E5FAFF" stroke-width="3"/>
        <path d="M98 91L110 79L122 91L110 103L98 91Z" fill="rgba(255,255,255,0.28)"/>
      </svg>
    </div>
  `;

  const buildPodiumCard = (entry, board, context) => {
    const rank = Number(entry.rank || 0);
    const isLeader = rank === 1;
    const delay = Math.max(0, rank - 1) * 120;

    return `
      <article class="leaderboard-podium-card leaderboard-podium-card--${context} leaderboard-podium-card--rank${rank} animate__animated animate__fadeInUp" style="animation-delay:${delay}ms">
        ${isLeader ? buildCrown() : ''}
        <div class="leaderboard-podium-shell">
          <div class="leaderboard-podium-top">
            <span class="leaderboard-rank-pill">#${rank}</span>
            ${medalChip(entry)}
          </div>

          <div class="leaderboard-avatar ${isLeader ? 'leaderboard-avatar--leader' : ''}">${escapeHtml(entry.avatar || 'BO')}</div>

          <div class="leaderboard-podium-copy">
            <span class="leaderboard-podium-label">${escapeHtml(isLeader ? boardMeta[board].leaderLabel : placeLabel(rank))}</span>
            <h3>${escapeHtml(entry.display_name || entry.username || 'Membro')}</h3>
            <strong class="leaderboard-score">${escapeHtml(entry.value_display || '0')}</strong>
          </div>
        </div>
      </article>
    `;
  };

  const buildPodiumStage = (entries, board, context) => {
    const topThree = Array.isArray(entries) ? entries.slice(0, 3) : [];
    const ordered = podiumOrder.map((index) => topThree[index]).filter(Boolean);

    if (!ordered.length) {
      return '';
    }

    return `
      <div class="leaderboard-stage leaderboard-stage--${context} leaderboard-stage--count${ordered.length}">
        ${ordered.map((entry) => buildPodiumCard(entry, board, context)).join('')}
      </div>
    `;
  };

  const buildPageRow = (entry) => `
    <article class="leaderboard-page-row animate__animated animate__fadeInUp" style="animation-delay:${Number(entry.rank || 0) * 70}ms">
      <div class="leaderboard-page-row-left">
        <span class="leaderboard-page-row-rank">${String(entry.rank || '').padStart(2, '0')}</span>
        <div class="leaderboard-avatar leaderboard-avatar--sm">${escapeHtml(entry.avatar || 'BO')}</div>
        <div class="leaderboard-page-row-copy">
          <strong>${escapeHtml(entry.display_name || entry.username || 'Membro')}</strong>
          <div class="leaderboard-chip-row">${medalChip(entry)}</div>
        </div>
      </div>
      <div class="leaderboard-page-row-right">
        <strong class="leaderboard-page-row-score">${escapeHtml(entry.value_display || '0')}</strong>
      </div>
    </article>
  `;

  const buildPageRest = (entries, board) => {
    if (!entries.length) {
      return '<p class="leaderboard-rest-empty">S\u00f3 o p\u00f3dio j\u00e1 est\u00e1 preenchido por enquanto.</p>';
    }

    return `
      <div class="leaderboard-page-rest-head">
        <span>${escapeHtml(boardMeta[board].restTitle)}</span>
        <strong>${escapeHtml(boardMeta[board].restIntro)}</strong>
      </div>
      <div class="leaderboard-page-list">
        ${entries.map((entry) => buildPageRow(entry)).join('')}
      </div>
    `;
  };

  const buildHomeBoard = (entries, board) => {
    if (!Array.isArray(entries) || !entries.length) {
      return emptyState(boardMeta[board].emptyTitle, boardMeta[board].emptyMessage);
    }

    const remaining = Math.max(0, entries.length - 3);
    const label = remaining === 1 ? 'nome continua' : 'nomes continuam';

    return `
      <div class="leaderboard-home-board-shell animate__animated animate__fadeIn">
        ${buildPodiumStage(entries, board, 'home')}
        ${remaining > 0 ? `<p class="leaderboard-home-inline-note">Mais ${remaining} ${label} na disputa. O restante aparece no ranking completo.</p>` : ''}
      </div>
    `;
  };

  const buildPageBoard = (entries, board) => {
    if (!Array.isArray(entries) || !entries.length) {
      return `
        <div class="leaderboard-page-board-head">
          <div>
            <p class="section-kicker">${escapeHtml(boardMeta[board].pageKicker)}</p>
            <h2 class="section-title">${escapeHtml(boardMeta[board].title)}</h2>
          </div>
          <span class="leaderboard-page-tag">${escapeHtml(boardMeta[board].pageTag)}</span>
        </div>
        ${emptyState(boardMeta[board].emptyTitle, boardMeta[board].emptyMessage)}
      `;
    }

    return `
      <div class="leaderboard-page-board-head">
        <div>
          <p class="section-kicker">${escapeHtml(boardMeta[board].pageKicker)}</p>
          <h2 class="section-title">${escapeHtml(boardMeta[board].title)}</h2>
        </div>
        <span class="leaderboard-page-tag">${escapeHtml(boardMeta[board].pageTag)}</span>
      </div>
      ${buildPodiumStage(entries, board, 'page')}
      <div class="leaderboard-page-rest">
        ${buildPageRest(entries.slice(3, 10), board)}
      </div>
    `;
  };

  const updateText = (root, selector, value) => {
    const node = root.querySelector(selector);
    if (node) {
      node.textContent = value;
    }
  };

  const renderHome = (root, payload) => {
    const coins = Array.isArray(payload.top_coins) ? payload.top_coins : [];
    const donates = Array.isArray(payload.top_donates) ? payload.top_donates : [];

    root.querySelectorAll('[data-leaderboard-home-board]').forEach((boardNode) => {
      const board = boardNode.getAttribute('data-leaderboard-home-board') || 'coins';
      boardNode.innerHTML = buildHomeBoard(board === 'coins' ? coins : donates, board);
    });

    updateText(root, '[data-leaderboard-updated]', String(payload.generated_label || 'agora'));
  };

  const renderPage = (root, payload) => {
    const coins = Array.isArray(payload.top_coins) ? payload.top_coins : [];
    const donates = Array.isArray(payload.top_donates) ? payload.top_donates : [];

    root.querySelectorAll('[data-leaderboard-page-board]').forEach((node) => {
      const board = node.getAttribute('data-leaderboard-page-board') || 'coins';
      node.innerHTML = buildPageBoard(board === 'coins' ? coins : donates, board);
    });

    updateText(root, '[data-leaderboard-updated]', String(payload.generated_label || 'agora'));
  };

  const applyHomeToggle = (root) => {
    const buttons = Array.from(root.querySelectorAll('[data-leaderboard-toggle]'));
    const boards = Array.from(root.querySelectorAll('[data-leaderboard-home-board]'));
    if (!buttons.length || !boards.length) {
      return;
    }

    const activate = (board) => {
      buttons.forEach((button) => {
        button.classList.toggle('is-active', button.getAttribute('data-leaderboard-toggle') === board);
      });

      boards.forEach((panel) => {
        panel.classList.toggle('is-active', panel.getAttribute('data-leaderboard-home-board') === board);
      });

      root.dataset.leaderboardActiveBoard = board;
    };

    if (root.dataset.leaderboardToggleBound !== '1') {
      buttons.forEach((button) => {
        button.addEventListener('click', () => activate(button.getAttribute('data-leaderboard-toggle') || 'coins'));
      });
      root.dataset.leaderboardToggleBound = '1';
    }

    activate(root.dataset.leaderboardActiveBoard || 'coins');
  };

  const parsePayload = (root) => {
    const script = root.querySelector('script[data-leaderboard-json]');
    if (!script) {
      return {};
    }

    try {
      return JSON.parse(script.textContent || '{}');
    } catch (error) {
      return {};
    }
  };

  const renderRoot = (root, payload) => {
    const context = root.getAttribute('data-leaderboard-context') || 'home';

    if (context === 'page') {
      renderPage(root, payload);
    } else {
      renderHome(root, payload);
      applyHomeToggle(root);
    }

    root.dataset.leaderboardGeneratedAt = String(payload.generated_at || '');
  };

  const refreshRoot = async (root) => {
    const endpoint = root.getAttribute('data-leaderboard-endpoint');
    if (!endpoint) {
      return;
    }

    try {
      const response = await fetch(endpoint, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        return;
      }

      const payload = await response.json();
      if (!payload || payload.ok === false) {
        return;
      }

      if (String(payload.generated_at || '') === String(root.dataset.leaderboardGeneratedAt || '')) {
        return;
      }

      renderRoot(root, payload);
    } catch (error) {
      // silencioso por design
    }
  };

  roots.forEach((root) => {
    renderRoot(root, parsePayload(root));
    window.setInterval(() => refreshRoot(root), 60000);
  });
})();