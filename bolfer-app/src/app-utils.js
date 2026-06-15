import { DISCORD_APP_ID } from '../config/discordApp.js';

export const LOCAL_API_BASE_URL = 'http://localhost:8000/api/desktop';
const viteEnv = typeof import.meta !== 'undefined' && import.meta.env ? import.meta.env : {};

function normalizeApiBaseUrl(value) {
  return String(value ?? '').trim().replace(/\/+$/, '');
}

export const DEFAULT_API_BASE_URL =
  normalizeApiBaseUrl(viteEnv.VITE_API_BASE_URL) || 'https://example.com/api/desktop';

export const DEFAULT_CONFIG = {
  apiBaseUrl: DEFAULT_API_BASE_URL,
  discordClientId: DISCORD_APP_ID,
  presenceEnabled: true,
  token: '',
  admin: null,
};

export const NAV_ITEMS = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    eyebrow: 'Centro de comando',
    description: 'Resumo rápido da operação, prioridades e atalhos da equipe.',
  },
  {
    id: 'orders',
    label: 'Pedidos',
    eyebrow: 'Fila operacional',
    description: 'Filtro local, triagem rápida e acompanhamento de entregas.',
  },
  {
    id: 'users',
    label: 'Usuários',
    eyebrow: 'Moderação',
    description: 'Perfil, saldo, inventário e ações do moderador no mesmo fluxo.',
  },
  {
    id: 'products',
    label: 'Produtos',
    eyebrow: 'Founder',
    description: 'Catálogo, estoque, descrição, anúncio e imagens WEBP no mesmo painel.',
  },
  {
    id: 'security',
    label: 'Banimentos',
    eyebrow: 'Segurança',
    description: 'Banimentos, tentativas, IPs e acessos reunidos para a equipe agir com mais calma.',
  },
  {
    id: 'invites',
    label: 'Convites',
    eyebrow: 'Founder',
    description: 'Convites exclusivos para trazer novos staff com link pronto e controle seguro.',
  },
  {
    id: 'logs',
    label: 'Logs',
    eyebrow: 'Auditoria',
    description: 'Histórico operacional do mercado, desbloqueios, recargas e ajustes da equipe.',
  },
  {
    id: 'settings',
    label: 'Configurações',
    eyebrow: 'Desktop',
    description: 'API, Discord e rotinas do aplicativo desktop.',
  },
];

export const LOG_FILTERS = {
  q: '',
  ip: '',
  scope: 'market',
  ban_status: '',
  market_event: '',
};

export const SECURITY_FILTERS = {
  q: '',
  ip: '',
  scope: 'security',
  ban_status: '',
  market_event: '',
};

export const ORDER_FILTERS = {
  q: '',
  status: 'all',
};

export const PRODUCT_FILTERS = {
  q: '',
  status: 'all',
  type: 'all',
  category: 'all',
};

export const USER_FILTERS = {
  q: '',
  status: 'all',
  role: 'all',
};

export const INVITE_FILTERS = {
  search: '',
  status: 'all',
};

export const STORAGE_KEYS = {
  uiPrefs: 'bolfer-desktop-ui',
  filterPresets: 'bolfer-desktop-filter-presets',
  orderOwners: 'bolfer-desktop-order-owners',
};

export const DEFAULT_UI_PREFS = {
  autoRefreshEnabled: true,
  autoRefreshInterval: 45,
  compactMode: false,
  soundAlertsEnabled: true,
  desktopNotificationsEnabled: false,
  onboardingDismissed: false,
  hideSensitiveInfo: false,
};

export const AUTO_REFRESH_OPTIONS = [15, 30, 45, 60, 90];

export const ORDER_QUICK_STATUSES = ['paid_waiting_contact', 'in_delivery', 'delivered'];

export const ORDER_NOTE_TEMPLATES = [
  {
    id: 'contact',
    label: 'Aguardando contato',
    note: 'Cliente notificado. Aguardando retorno para avançar com a entrega.',
  },
  {
    id: 'delivery',
    label: 'Entrega em andamento',
    note: 'Pedido em tratativa com a equipe. Entrega em andamento neste momento.',
  },
  {
    id: 'handover',
    label: 'Próximo moderador',
    note: 'Contexto registrado para continuidade no próximo atendimento sem perder o histórico.',
  },
];

export const USER_BAN_REASONS = [
  {
    id: 'suspicious',
    label: 'Tentativa suspeita',
    reason: 'Bloqueio preventivo por tentativa suspeita identificada pela equipe de moderação.',
  },
  {
    id: 'abuse',
    label: 'Abuso de regras',
    reason: 'Bloqueio aplicado por violação das regras operacionais e reincidência no atendimento.',
  },
  {
    id: 'security',
    label: 'Segurança da conta',
    reason: 'Bloqueio temporário para validação de segurança e revisão manual da conta.',
  },
];

export const COIN_TEMPLATES = [
  {
    id: 'bonus-100',
    label: '+100 coins',
    amount: 100,
    note: 'Bônus aplicado pela equipe.',
  },
  {
    id: 'bonus-500',
    label: '+500 coins',
    amount: 500,
    note: 'Ajuste promocional autorizado pela moderação.',
  },
  {
    id: 'reversal-100',
    label: '-100 coins',
    amount: -100,
    note: 'Reversão rápida aplicada pela equipe.',
  },
];

export const SHORTCUT_HINTS = [
  { id: 'search', keys: 'Ctrl + K', action: 'Abrir a busca global' },
  { id: 'refresh', keys: 'R', action: 'Atualizar a seção atual' },
  { id: 'dashboard', keys: '1', action: 'Ir para Dashboard' },
  { id: 'orders', keys: '2', action: 'Ir para Pedidos' },
  { id: 'users', keys: '3', action: 'Ir para Usuários' },
  { id: 'products', keys: '4', action: 'Ir para Produtos' },
  { id: 'security', keys: '5', action: 'Ir para Banimentos' },
  { id: 'invites', keys: '6', action: 'Ir para Convites' },
  { id: 'logs', keys: '7', action: 'Ir para Logs' },
  { id: 'settings', keys: '8', action: 'Ir para Configurações' },
  { id: 'escape', keys: 'Esc', action: 'Fechar busca ou modal aberto' },
];

const dtf = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const number = new Intl.NumberFormat('pt-BR');
const relative = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

export const fmtDate = (value) => (value ? dtf.format(new Date(value)) : '-');
export const fmtMoney = (value) => money.format(Number(value ?? 0));
export const fmtCount = (value) => number.format(Number(value ?? 0));

function replaceKeepingInitialCase(source, replacement) {
  if (!source) {
    return replacement;
  }

  return source[0] === source[0].toUpperCase() ? `${replacement.charAt(0).toUpperCase()}${replacement.slice(1)}` : replacement;
}

export function normalizeLegacyText(value) {
  const normalized = String(value ?? '').trim();
  if (!normalized) {
    return '';
  }

  return [
    ['inventario', 'inventário'],
    ['usuario', 'usuário'],
    ['nao', 'não'],
    ['possivel', 'possível'],
    ['propria', 'própria'],
    ['minimo', 'mínimo'],
    ['disponivel', 'disponível'],
    ['historico', 'histórico'],
    ['so', 'só'],
    ['ja', 'já'],
  ].reduce(
    (current, [search, replacement]) =>
      current.replace(new RegExp(`\\b${search}\\b`, 'gi'), (match) => replaceKeepingInitialCase(match, replacement)),
    normalized,
  );
}

export function formatUnlockRequirement(unlockCost, unlockUnit = 'coins') {
  const cost = Number(unlockCost ?? 0);
  if (!Number.isFinite(cost) || cost <= 0) {
    return 'Não exige';
  }

  if (unlockUnit === 'keys') {
    return `${fmtCount(cost)} ${cost === 1 ? 'chave' : 'chaves'}`;
  }

  return `${fmtCount(cost)} ${cost === 1 ? 'coin' : 'coins'}`;
}

export function safeList(value) {
  return Array.isArray(value) ? value : [];
}

export function readStoredValue(key, fallback) {
  if (typeof window === 'undefined') {
    return fallback;
  }

  try {
    const raw = window.localStorage.getItem(key);
    if (!raw) {
      return fallback;
    }

    const parsed = JSON.parse(raw);
    return parsed ?? fallback;
  } catch {
    return fallback;
  }
}

export function writeStoredValue(key, value) {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // Intencional: preferências locais não devem quebrar a UI.
  }
}

export function tone(status) {
  if (['delivered', 'active', 'sent'].includes(status)) return 'good';
  if (['cancelled', 'rejected', 'failed', 'banned'].includes(status)) return 'danger';
  if (['in_delivery', 'pending_payment', 'paid_waiting_contact', 'warn', 'skipped'].includes(status)) return 'warn';
  return 'neutral';
}

export function presenceLabel(status) {
  if (status.ok) return 'Conectado';
  if (status.reason === 'browser' || status.platform === 'web') return 'No navegador';
  if (!status.enabled || !status.clientId) return 'Parado';
  if (status.toneValue === 'danger') return 'Erro';
  return 'Aguardando';
}

export function normalizePresenceStatus(status, config = DEFAULT_CONFIG, appInfo = { platform: 'unknown' }) {
  const raw = status && typeof status === 'object' ? status : {};
  const enabled = raw.enabled ?? config.presenceEnabled;
  const clientId = String(raw.configuredClientId ?? raw.clientId ?? config.discordClientId ?? '').trim();
  const platform = appInfo?.platform ?? 'unknown';
  let toneValue = 'neutral';
  let text = raw.message || '';

  if (raw.reason === 'browser' || platform === 'web') {
    toneValue = 'warn';
    text = 'O Rich Presence só funciona no app desktop aberto pelo Electron.';
  } else if (!enabled) {
    toneValue = 'neutral';
    text = 'Rich Presence desativado nas preferências.';
  } else if (!clientId) {
    toneValue = 'neutral';
    text = 'Informe o Discord Client ID para ativar o Rich Presence.';
  } else if (raw.ok || raw.ready) {
    toneValue = 'good';
    text = raw.message || 'Discord conectado e exibindo o status do desktop.';
  } else if (raw.reason === 'not_ready') {
    toneValue = 'warn';
    text = raw.message || 'Aguardando o Discord responder.';
  } else if (raw.lastError || raw.message) {
    toneValue = 'danger';
    text = raw.message || raw.lastError;
  } else {
    toneValue = 'warn';
    text = 'Aguardando atualização do Discord.';
  }

  return {
    ...raw,
    ok: Boolean(raw.ok || raw.ready),
    enabled,
    clientId,
    platform,
    toneValue,
    text,
  };
}

export function getViewMeta(view) {
  return NAV_ITEMS.find((item) => item.id === view) ?? NAV_ITEMS[0];
}

export function getStatusLabel(statusLabels, status) {
  return statusLabels?.[status] ?? status ?? '-';
}

function normalizeSearch(value) {
  return String(value ?? '').trim().toLowerCase();
}

function toTimestamp(value) {
  const time = new Date(value ?? 0).getTime();
  return Number.isNaN(time) ? 0 : time;
}

function toMinutes(diffMs) {
  return Math.max(0, Math.round(diffMs / 60000));
}

export function fmtRelativeDate(value) {
  const timestamp = toTimestamp(value);
  if (!timestamp) {
    return 'Sem horário';
  }

  const diffMs = timestamp - Date.now();
  const absMs = Math.abs(diffMs);

  if (absMs < 60000) {
    return 'agora';
  }

  if (absMs < 3600000) {
    return relative.format(Math.round(diffMs / 60000), 'minute');
  }

  if (absMs < 86400000) {
    return relative.format(Math.round(diffMs / 3600000), 'hour');
  }

  return relative.format(Math.round(diffMs / 86400000), 'day');
}

export function resolveOrderTimestamp(order) {
  return order?.updatedAt || order?.paidAt || order?.createdAt || order?.approvedAt || order?.submittedAt || null;
}

export function getOrderSla(order) {
  if (!order) {
    return {
      toneValue: 'neutral',
      label: 'Sem SLA',
      description: 'Sem pedido selecionado.',
      minutes: 0,
      relative: 'Sem horário',
      timestamp: null,
    };
  }

  if (order.status === 'delivered') {
    return {
      toneValue: 'good',
      label: 'Concluído',
      description: 'Pedido encerrado com sucesso.',
      minutes: 0,
      relative: 'Concluído',
      timestamp: resolveOrderTimestamp(order),
    };
  }

  const timestamp = resolveOrderTimestamp(order);
  if (!timestamp) {
    return {
      toneValue: 'neutral',
      label: 'Sem horário',
      description: 'O pedido não trouxe um horário base para SLA.',
      minutes: 0,
      relative: 'Sem horário',
      timestamp: null,
    };
  }

  const minutes = toMinutes(Date.now() - toTimestamp(timestamp));
  if (minutes >= 180) {
    return {
      toneValue: 'danger',
      label: 'Crítico',
      description: `Pedido parado desde ${fmtRelativeDate(timestamp)}.`,
      minutes,
      relative: fmtRelativeDate(timestamp),
      timestamp,
    };
  }

  if (minutes >= 60) {
    return {
      toneValue: 'warn',
      label: 'Atenção',
      description: `Último movimento ${fmtRelativeDate(timestamp)}.`,
      minutes,
      relative: fmtRelativeDate(timestamp),
      timestamp,
    };
  }

  return {
    toneValue: 'good',
    label: 'No prazo',
    description: `Último movimento ${fmtRelativeDate(timestamp)}.`,
    minutes,
    relative: fmtRelativeDate(timestamp),
    timestamp,
  };
}

export function filterOrders(orderList, filters) {
  const query = normalizeSearch(filters.q);
  return safeList(orderList).filter((order) => {
    const matchesQuery =
      !query ||
      [order.publicId, order.productName, order.inGameNick, order.contactValue]
        .filter(Boolean)
        .some((value) => normalizeSearch(value).includes(query));
    const matchesStatus = filters.status === 'all' || order.status === filters.status;
    return matchesQuery && matchesStatus;
  });
}

export function buildOrderStats(orderList) {
  return safeList(orderList).reduce(
    (acc, order) => {
      const sla = getOrderSla(order);
      acc.total += 1;
      acc.revenue += Number(order.totalAmount ?? 0);
      if (['pending_payment', 'paid_waiting_contact'].includes(order.status)) acc.pending += 1;
      if (order.status === 'in_delivery') acc.inDelivery += 1;
      if (order.status === 'delivered') acc.delivered += 1;
      if (sla.toneValue === 'warn') acc.slaWarning += 1;
      if (sla.toneValue === 'danger') acc.slaCritical += 1;
      return acc;
    },
    {
      total: 0,
      pending: 0,
      inDelivery: 0,
      delivered: 0,
      slaWarning: 0,
      slaCritical: 0,
      revenue: 0,
    },
  );
}

export function filterUsers(userList, filters) {
  const query = normalizeSearch(filters.q);
  return safeList(userList).filter((user) => {
    const matchesQuery =
      !query ||
      [user.username, user.email, user.role]
        .filter(Boolean)
        .some((value) => normalizeSearch(value).includes(query));
    const matchesStatus =
      filters.status === 'all' ||
      (filters.status === 'banned' && user.isBanned) ||
      (filters.status === 'active' && !user.isBanned);
    const matchesRole = filters.role === 'all' || String(user.role ?? '') === filters.role;
    return matchesQuery && matchesStatus && matchesRole;
  });
}

export function buildUserStats(userList) {
  const roles = new Set();
  return safeList(userList).reduce(
    (acc, user) => {
      acc.total += 1;
      acc.coins += Number(user.marketCoins ?? 0);
      if (user.isBanned) acc.banned += 1;
      if (!user.isBanned) acc.active += 1;
      if (user.role) roles.add(user.role);
      acc.roles = roles.size;
      return acc;
    },
    {
      total: 0,
      active: 0,
      banned: 0,
      coins: 0,
      roles: 0,
    },
  );
}

export function buildInviteStats(inviteList) {
  const now = Date.now();
  const dayMs = 24 * 60 * 60 * 1000;

  return safeList(inviteList).reduce(
    (acc, invite) => {
      acc.total += 1;

      if (invite?.status === 'used') {
        acc.used += 1;
      } else {
        acc.available += 1;
      }

      if (toTimestamp(invite?.createdAt) >= now - dayMs) {
        acc.createdToday += 1;
      }

      return acc;
    },
    {
      total: 0,
      available: 0,
      used: 0,
      createdToday: 0,
    },
  );
}

export function buildRoleOptions(userList) {
  return [...new Set(safeList(userList).map((user) => String(user.role ?? '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
}

export function buildDashboardTasks(data) {
  const paidWaitingContact = Number(data?.stats?.orders?.paidWaitingContact ?? 0);
  const inDelivery = Number(data?.stats?.orders?.inDelivery ?? 0);
  const delivered = Number(data?.stats?.orders?.delivered ?? 0);
  const bannedUsers = Number(data?.stats?.users?.banned ?? 0);
  const tasks = [];

  if (paidWaitingContact > 0) {
    tasks.push({
      id: 'orders-contact',
      title: `${fmtCount(paidWaitingContact)} pedidos aguardando contato`,
      text: 'Vale priorizar a fila paga para reduzir atrasos e iniciar a entrega mais rápido.',
      toneValue: 'warn',
      target: 'orders',
    });
  }

  if (inDelivery > 0) {
    tasks.push({
      id: 'orders-delivery',
      title: `${fmtCount(inDelivery)} entregas em andamento`,
      text: 'Os moderadores podem acompanhar status, adicionar notas e encurtar repasses.',
      toneValue: 'good',
      target: 'orders',
    });
  }

  if (bannedUsers > 0) {
    tasks.push({
      id: 'security-bans',
      title: `${fmtCount(bannedUsers)} usuários banidos em observação`,
      text: 'Abra Banimentos para revisar contexto, tentativas e IPs antes de tomar uma nova ação.',
      toneValue: 'danger',
      target: 'security',
    });
  }

  if (delivered > 0) {
    tasks.push({
      id: 'orders-delivered',
      title: `${fmtCount(delivered)} pedidos já concluídos`,
      text: 'A operação já fechou entregas. Aproveite para limpar pendências menores.',
      toneValue: 'neutral',
      target: 'dashboard',
    });
  }

  if (!tasks.length) {
    tasks.push({
      id: 'steady-state',
      title: 'Fluxo estabilizado',
      text: 'Sem filas criticas agora. Use o painel para revisar contexto e manter a equipe alinhada.',
      toneValue: 'good',
      target: 'dashboard',
    });
  }

  return tasks.slice(0, 4);
}

export function buildLogFeed(data) {
  const marketEventLabels = data?.marketEventLabels ?? {};
  const banLogs = safeList(data?.banLogs).map((entry) => ({
    id: `ban-${entry.id}`,
    title: `Banimento: ${entry.targetUsername || 'usuário'}`,
    text: entry.reason || 'Sem motivo informado.',
    badge: entry.status || 'ban',
    badgeTone: tone(entry.status || 'banned'),
    createdAt: entry.createdAt,
    meta: fmtDate(entry.createdAt),
  }));

  const marketToneValue = (eventType) => {
    if (eventType === 'listing_sold' || eventType === 'topup_paid') return 'good';
    if (eventType === 'listing_cancelled') return 'danger';
    if (eventType === 'inventory_unlocked' || eventType === 'admin_coin_adjust') return 'warn';
    return 'neutral';
  };

  const marketNarrative = (entry) => {
    const note = normalizeLegacyText(entry.note);
    const buyer = entry.buyerUsername || entry.actorUsername || '';
    const seller = entry.sellerUsername || '';
    const target = entry.targetUsername || buyer || '';

    if (entry.eventType === 'listing_sold') {
      return [buyer && `Comprador: ${buyer}`, seller && `Vendedor: ${seller}`, target && `Destino: ${target}`]
        .filter(Boolean)
        .join(' | ') || note || 'Venda concluída no mercado.';
    }

    if (entry.eventType === 'listing_created') {
      return seller ? `Oferta publicada por ${seller}.` : note || 'Oferta publicada no mercado.';
    }

    if (entry.eventType === 'listing_cancelled') {
      return seller ? `Oferta cancelada por ${seller}.` : note || 'Oferta cancelada no mercado.';
    }

    if (entry.eventType === 'inventory_unlocked') {
      const unlockText = formatUnlockRequirement(entry.unlockCost, entry.unlockUnit);
      return target ? `Item liberado para ${target}${unlockText !== 'Não exige' ? ` com ${unlockText}` : ''}.` : note || 'Item desbloqueado.';
    }

    if (entry.eventType === 'admin_coin_adjust') {
      return target ? `Saldo ajustado para ${target}.` : note || 'Ajuste manual de coins.';
    }

    if (entry.eventType === 'topup_paid' || entry.eventType === 'topup_created') {
      return target ? `Recarga vinculada a ${target}.` : note || 'Recarga registrada.';
    }

    return note || target || 'Sem detalhes adicionais.';
  };

  const marketLogs = safeList(data?.marketLogs).map((entry) => ({
    id: `market-${entry.id}`,
    title: `${marketEventLabels?.[entry.eventType] || entry.eventType || 'Evento de mercado'}${entry.itemName ? `: ${entry.itemName}` : ''}`,
    text: marketNarrative(entry),
    badge: 'mercado',
    badgeTone: marketToneValue(entry.eventType),
    createdAt: entry.createdAt,
    meta: fmtDate(entry.createdAt),
  }));

  const accessLogs = safeList(data?.accessLogs).map((entry) => ({
    id: `access-${entry.id}`,
    title: `${entry.targetUsername || 'Conta'} | ${entry.action || 'Acesso'}`,
    text: [entry.route, entry.ipAddress].filter(Boolean).join(' | ') || 'Sem rota registrada.',
    badge: 'acesso',
    badgeTone: 'neutral',
    createdAt: entry.createdAt,
    meta: fmtDate(entry.createdAt),
  }));

  return [...banLogs, ...marketLogs, ...accessLogs]
    .sort((a, b) => toTimestamp(b.createdAt) - toTimestamp(a.createdAt))
    .slice(0, 12);
}

export function buildLogHighlights(data) {
  const summary = data?.summary ?? {};
  const attemptsToday = Number(summary.attempts_today ?? 0);
  const activeBans = Number(summary.active_bans ?? 0);
  const uniqueIps = Number(summary.unique_ips ?? 0);
  const marketSales = Number(summary.market_sales ?? 0);

  const highlights = [
    {
      id: 'attempts',
      title: attemptsToday > 20 ? 'Pico de tentativas hoje' : 'Acessos sob controle',
      text:
        attemptsToday > 20
          ? `${fmtCount(attemptsToday)} tentativas foram registradas. Vale cruzar IP e histórico recente.`
          : `${fmtCount(attemptsToday)} tentativas registradas hoje.`,
      toneValue: attemptsToday > 20 ? 'danger' : attemptsToday > 0 ? 'warn' : 'good',
    },
    {
      id: 'bans',
      title: activeBans > 0 ? 'Casos ativos de moderação' : 'Sem bans ativos',
      text:
        activeBans > 0
          ? `${fmtCount(activeBans)} bans seguem ativos e merecem revisão contextual.`
          : 'Não há bans ativos no recorte atual.',
      toneValue: activeBans > 0 ? 'danger' : 'good',
    },
    {
      id: 'ips',
      title: 'Distribuição de IPs',
      text: `${fmtCount(uniqueIps)} IPs únicos apareceram nos logs carregados.`,
      toneValue: uniqueIps > 15 ? 'warn' : 'neutral',
    },
    {
      id: 'market',
      title: 'Movimento de mercado',
      text: `${fmtCount(marketSales)} eventos de venda no recorte atual.`,
      toneValue: marketSales > 0 ? 'good' : 'neutral',
    },
  ];

  return highlights;
}

export function buildMarketHighlights(data) {
  const marketLogs = safeList(data?.marketLogs);
  const stats = marketLogs.reduce(
    (acc, entry) => {
      acc.total += 1;
      if (entry.eventType === 'listing_sold') acc.sales += 1;
      if (entry.eventType === 'topup_created' || entry.eventType === 'topup_paid') acc.topups += 1;
      if (entry.eventType === 'inventory_unlocked') acc.unlocks += 1;
      if (entry.eventType === 'admin_coin_adjust') acc.adjustments += 1;
      return acc;
    },
    { total: 0, sales: 0, topups: 0, unlocks: 0, adjustments: 0 },
  );

  return [
    {
      id: 'market-total',
      title: `${fmtCount(stats.total)} movimentos no recorte`,
      text: stats.total ? 'Use a lista abaixo para abrir cada venda, recarga ou ajuste com mais contexto.' : 'Nenhum movimento de mercado carregado neste momento.',
      toneValue: stats.total ? 'neutral' : 'good',
    },
    {
      id: 'market-sales',
      title: `${fmtCount(stats.sales)} vendas concluídas`,
      text: stats.sales ? 'Comprador, vendedor e destino aparecem organizados em cada cartão.' : 'Ainda não houve venda neste recorte.',
      toneValue: stats.sales ? 'good' : 'neutral',
    },
    {
      id: 'market-topups',
      title: `${fmtCount(stats.topups)} recargas registradas`,
      text: stats.topups ? 'Recargas criadas e aprovadas continuam centralizadas aqui.' : 'Nenhuma recarga apareceu nos filtros atuais.',
      toneValue: stats.topups ? 'neutral' : 'good',
    },
    {
      id: 'market-unlocks',
      title: `${fmtCount(stats.unlocks + stats.adjustments)} desbloqueios e ajustes`,
      text: stats.unlocks + stats.adjustments ? 'Bom ponto para revisar desbloqueios por chave e ajustes manuais de saldo.' : 'Sem desbloqueios ou ajustes neste recorte.',
      toneValue: stats.unlocks + stats.adjustments ? 'warn' : 'good',
    },
  ];
}

export function buildSecurityHighlights(data) {
  const summary = data?.summary ?? {};
  const activeBans = Number(summary.active_bans ?? 0);
  const attemptsToday = Number(summary.attempts_today ?? 0);
  const uniqueIps = Number(summary.unique_ips ?? 0);
  const accessLogs = safeList(data?.accessLogs);

  return [
    {
      id: 'security-bans',
      title: activeBans ? `${fmtCount(activeBans)} bans ativos` : 'Sem bans ativos',
      text: activeBans ? 'Revise motivos, IPs e tentativas antes de agir em novos casos.' : 'Nenhum ban ativo apareceu no panorama atual.',
      toneValue: activeBans ? 'danger' : 'good',
    },
    {
      id: 'security-attempts',
      title: attemptsToday ? `${fmtCount(attemptsToday)} tentativas hoje` : 'Tentativas sob controle',
      text: attemptsToday ? 'As tentativas recentes ficam separadas para facilitar leitura com mais calma.' : 'Nenhuma tentativa crítica foi registrada hoje.',
      toneValue: attemptsToday > 20 ? 'danger' : attemptsToday > 0 ? 'warn' : 'good',
    },
    {
      id: 'security-ips',
      title: `${fmtCount(uniqueIps)} IPs únicos`,
      text: uniqueIps ? 'Use o resumo de IPs para cruzar contas e horários de acesso.' : 'Sem IPs relevantes carregados neste recorte.',
      toneValue: uniqueIps > 15 ? 'warn' : 'neutral',
    },
    {
      id: 'security-access',
      title: `${fmtCount(accessLogs.length)} acessos recentes`,
      text: accessLogs.length ? 'Os últimos acessos já ficam prontos para auditoria rápida da equipe.' : 'Nenhum acesso recente apareceu com os filtros atuais.',
      toneValue: accessLogs.length ? 'neutral' : 'good',
    },
  ];
}

export function latestTimestamp(values) {
  const valid = values.filter((value) => Number.isFinite(value) && value > 0);
  return valid.length ? Math.max(...valid) : null;
}

export function formatEndpoint(value) {
  return String(value ?? '')
    .trim()
    .replace(/^https?:\/\//, '')
    .replace(/\/+$/, '');
}

function maskMiddle(value, visibleStart = 2, visibleEnd = 2, replacement = '•') {
  const normalized = String(value ?? '');
  if (!normalized) {
    return '';
  }

  if (normalized.length <= visibleStart + visibleEnd) {
    return replacement.repeat(Math.max(normalized.length, 4));
  }

  return `${normalized.slice(0, visibleStart)}${replacement.repeat(Math.max(normalized.length - visibleStart - visibleEnd, 4))}${normalized.slice(-visibleEnd)}`;
}

export function maskEmail(value) {
  const normalized = String(value ?? '').trim();
  if (!normalized) {
    return '';
  }

  const [localPart, domain = ''] = normalized.split('@');
  if (!domain) {
    return maskMiddle(normalized, 2, 1);
  }

  const maskedLocal = localPart.length <= 2 ? `${localPart[0] ?? ''}•••` : `${localPart.slice(0, 2)}${'•'.repeat(Math.max(localPart.length - 2, 3))}`;
  const [domainName = '', tld = ''] = domain.split('.');
  const maskedDomain = domainName ? `${domainName.slice(0, 1)}${'•'.repeat(Math.max(domainName.length - 1, 3))}` : '•••';

  return `${maskedLocal}@${maskedDomain}${tld ? `.${tld}` : ''}`;
}

export function maskEndpointValue(value) {
  const normalized = formatEndpoint(value);
  if (!normalized) {
    return '';
  }

  const [host, ...pathParts] = normalized.split('/');
  const hostParts = host.split(':');
  const hostname = hostParts[0] || '';
  const port = hostParts[1] || '';
  const maskedHost = port ? `${hostname}:${maskMiddle(port, 0, 0)}` : maskMiddle(hostname, 2, 2);
  const path = pathParts.join('/');
  return path ? `${maskedHost}/${maskMiddle(path, 2, 2)}` : maskedHost;
}

export function maskClientId(value) {
  return maskMiddle(String(value ?? '').trim(), 3, 3);
}

export function maskSensitiveValue(value, kind = 'generic') {
  if (!value) {
    return value;
  }

  if (kind === 'email') {
    return maskEmail(value);
  }

  if (kind === 'endpoint') {
    return maskEndpointValue(value);
  }

  if (kind === 'clientId') {
    return maskClientId(value);
  }

  return maskMiddle(String(value ?? '').trim(), 2, 2);
}

export function createActivityEntry({
  id,
  title,
  text,
  badge = '',
  badgeTone = 'neutral',
  createdAt = Date.now(),
  targetView = 'dashboard',
  entityType = '',
  entityId = null,
  keywords = '',
}) {
  return {
    id: id || `${targetView}-${entityType || 'entry'}-${entityId ?? 'global'}-${toTimestamp(createdAt)}`,
    title,
    text,
    badge,
    badgeTone,
    createdAt,
    meta: fmtDate(createdAt),
    targetView,
    entityType,
    entityId,
    keywords,
  };
}

export function buildDashboardAlerts(previousData, nextData) {
  if (!previousData) {
    return [];
  }

  const previousWaiting = Number(previousData?.stats?.orders?.paidWaitingContact ?? 0);
  const nextWaiting = Number(nextData?.stats?.orders?.paidWaitingContact ?? 0);
  const previousBans = Number(previousData?.stats?.users?.banned ?? 0);
  const nextBans = Number(nextData?.stats?.users?.banned ?? 0);
  const alerts = [];

  if (nextWaiting > previousWaiting) {
    alerts.push(
      createActivityEntry({
        title: 'Fila de pedidos aumentou',
        text: `${fmtCount(nextWaiting)} pedidos aguardam contato no momento.`,
        badge: 'pedidos',
        badgeTone: 'warn',
        targetView: 'orders',
        keywords: 'pedido fila contato',
      }),
    );
  }

  if (nextBans > previousBans) {
    alerts.push(
      createActivityEntry({
        title: 'Moderação com novos casos',
        text: `${fmtCount(nextBans)} usuários banidos aparecem no panorama atual.`,
        badge: 'moderação',
        badgeTone: 'danger',
        targetView: 'users',
        keywords: 'ban moderacao usuários',
      }),
    );
  }

  return alerts;
}

export function buildOrderAlerts(previousOrders, nextOrders, statusLabels) {
  const previousMap = new Map(safeList(previousOrders).map((order) => [order.id, order]));
  const nextList = safeList(nextOrders);
  const newOrders = nextList.filter((order) => !previousMap.has(order.id));
  const changedOrders = nextList.filter((order) => {
    const previous = previousMap.get(order.id);
    return previous && previous.status !== order.status;
  });
  const alerts = [];

  if (newOrders.length) {
    const firstOrder = newOrders[0];
    alerts.push(
      createActivityEntry({
        title: newOrders.length === 1 ? 'Novo pedido na fila' : `${fmtCount(newOrders.length)} novos pedidos`,
        text:
          newOrders.length === 1
            ? `${firstOrder.publicId} entrou na operação com ${firstOrder.productName}.`
            : 'A fila recebeu novos pedidos e merece uma triagem rápida.',
        badge: 'pedido',
        badgeTone: 'warn',
        targetView: 'orders',
        entityType: 'order',
        entityId: firstOrder?.id ?? null,
        keywords: [firstOrder?.publicId, firstOrder?.productName].filter(Boolean).join(' '),
      }),
    );
  }

  if (changedOrders.length) {
    const firstOrder = changedOrders[0];
    alerts.push(
      createActivityEntry({
        title: changedOrders.length === 1 ? 'Status de pedido atualizado' : `${fmtCount(changedOrders.length)} pedidos mudaram de status`,
        text:
          changedOrders.length === 1
            ? `${firstOrder.publicId} agora está em "${getStatusLabel(statusLabels, firstOrder.status)}".`
            : 'A fila mudou de estado e pode exigir revisão do moderador.',
        badge: 'status',
        badgeTone: tone(firstOrder?.status),
        targetView: 'orders',
        entityType: 'order',
        entityId: firstOrder?.id ?? null,
        keywords: [firstOrder?.publicId, firstOrder?.productName].filter(Boolean).join(' '),
      }),
    );
  }

  return alerts;
}

export function buildUserAlerts(previousUsers, nextUsers) {
  const previousMap = new Map(safeList(previousUsers).map((user) => [user.id, user]));
  const nextList = safeList(nextUsers);
  const newUsers = nextList.filter((user) => !previousMap.has(user.id));
  const moderationChanges = nextList.filter((user) => {
    const previous = previousMap.get(user.id);
    return previous && previous.isBanned !== user.isBanned;
  });
  const alerts = [];

  if (newUsers.length) {
    const firstUser = newUsers[0];
    alerts.push(
      createActivityEntry({
        title: newUsers.length === 1 ? 'Novo usuário carregado' : `${fmtCount(newUsers.length)} novos usuários carregados`,
        text:
          newUsers.length === 1
            ? `${firstUser.username} apareceu na base atual do desktop.`
            : 'A base de usuários foi atualizada com novas entradas.',
        badge: 'usuário',
        badgeTone: 'neutral',
        targetView: 'users',
        entityType: 'user',
        entityId: firstUser?.id ?? null,
        keywords: [firstUser?.username, firstUser?.email].filter(Boolean).join(' '),
      }),
    );
  }

  if (moderationChanges.length) {
    const firstUser = moderationChanges[0];
    alerts.push(
      createActivityEntry({
        title: firstUser.isBanned ? 'Usuário banido detectado' : 'Usuário liberado novamente',
        text: `${firstUser.username} agora está ${firstUser.isBanned ? 'banido' : 'ativo'} no recorte atual.`,
        badge: 'moderação',
        badgeTone: firstUser.isBanned ? 'danger' : 'good',
        targetView: 'users',
        entityType: 'user',
        entityId: firstUser?.id ?? null,
        keywords: [firstUser?.username, firstUser?.email].filter(Boolean).join(' '),
      }),
    );
  }

  return alerts;
}

export function buildLogAlerts(previousLogs, nextLogs, targetView = 'logs') {
  if (!previousLogs) {
    return [];
  }

  const previousSummary = previousLogs.summary ?? {};
  const nextSummary = nextLogs?.summary ?? {};
  const alerts = [];
  const previousAttempts = Number(previousSummary.attempts_today ?? 0);
  const nextAttempts = Number(nextSummary.attempts_today ?? 0);
  const previousBans = Number(previousSummary.active_bans ?? 0);
  const nextBans = Number(nextSummary.active_bans ?? 0);
  const previousMarketSales = Number(previousSummary.market_sales ?? 0);
  const nextMarketSales = Number(nextSummary.market_sales ?? 0);

  if (targetView === 'security') {
    if (nextAttempts > previousAttempts) {
      alerts.push(
        createActivityEntry({
          title: 'Segurança registrou novas tentativas',
          text: `${fmtCount(nextAttempts)} tentativas foram registradas hoje.`,
          badge: 'segurança',
          badgeTone: nextAttempts > 20 ? 'danger' : 'warn',
          targetView,
          keywords: 'segurança tentativas acesso ip',
        }),
      );
    }

    if (nextBans > previousBans) {
      alerts.push(
        createActivityEntry({
          title: 'Banimentos ativos cresceram',
          text: `${fmtCount(nextBans)} bans ativos aparecem no recorte atual de segurança.`,
          badge: 'ban',
          badgeTone: 'danger',
          targetView,
          keywords: 'ban segurança moderação',
        }),
      );
    }

    return alerts;
  }

  if (nextMarketSales > previousMarketSales) {
    alerts.push(
      createActivityEntry({
        title: 'Mercado registrou novas vendas',
        text: `${fmtCount(nextMarketSales)} vendas aparecem no panorama operacional atual.`,
        badge: 'mercado',
        badgeTone: 'good',
        targetView,
        keywords: 'mercado venda logs operacional',
      }),
    );
  }

  return alerts;
}

export function buildUserHistory(detail, activityFeed, logFeed) {
  const user = detail?.user;
  if (!user) {
    return [];
  }

  const identityTerms = [user.username, user.email].filter(Boolean).map((item) => normalizeSearch(item));
  const inventory = safeList(detail?.inventory);
  const localActivity = safeList(activityFeed).filter(
    (entry) =>
      (entry.entityType === 'user' && entry.entityId === user.id) ||
      identityTerms.some((term) => normalizeSearch(`${entry.title} ${entry.text} ${entry.keywords || ''}`).includes(term)),
  );
  const relatedLogs = safeList(logFeed).filter((entry) =>
    identityTerms.some((term) => normalizeSearch(`${entry.title} ${entry.text}`).includes(term)),
  );

  const timeline = [
    createActivityEntry({
      id: `user-last-login-${user.id}`,
      title: 'Último login conhecido',
      text: user.lastLoginAt ? `A conta acessou o sistema em ${fmtDate(user.lastLoginAt)}.` : 'Ainda não há registro de último login disponível.',
      badge: 'perfil',
      badgeTone: 'neutral',
      createdAt: user.lastLoginAt || Date.now(),
      targetView: 'users',
      entityType: 'user',
      entityId: user.id,
      keywords: `${user.username} ${user.email || ''}`,
    }),
    createActivityEntry({
      id: `user-moderation-${user.id}`,
      title: user.isBanned ? 'Status de moderação ativo' : 'Conta liberada',
      text: user.isBanned ? 'A conta está marcada como banida no momento.' : 'A conta está liberada para uso.',
      badge: 'moderação',
      badgeTone: user.isBanned ? 'danger' : 'good',
      targetView: 'users',
      entityType: 'user',
      entityId: user.id,
      keywords: `${user.username} ${user.email || ''}`,
    }),
    createActivityEntry({
      id: `user-inventory-${user.id}`,
      title: 'Resumo do inventário',
      text: `${fmtCount(inventory.length)} itens carregados e ${fmtCount(inventory.filter((item) => item.isUnlocked).length)} liberados.`,
      badge: 'inventário',
      badgeTone: 'warn',
      targetView: 'users',
      entityType: 'user',
      entityId: user.id,
      keywords: `${user.username} inventário`,
    }),
    ...localActivity,
    ...relatedLogs.map((entry) => ({
      ...entry,
      badge: entry.badge || 'log',
      badgeTone: entry.badgeTone || 'neutral',
      targetView: entry.targetView || 'logs',
      entityType: 'user',
      entityId: user.id,
    })),
  ];

  return timeline
    .sort((a, b) => toTimestamp(b.createdAt) - toTimestamp(a.createdAt))
    .slice(0, 10);
}

export function buildOrderTimeline(detail, activityFeed, ownerEntry) {
  const order = detail?.order;
  if (!order) {
    return [];
  }

  const baseTerms = [order.publicId, order.productName, order.contactValue, order.inGameNick].filter(Boolean).map((item) => normalizeSearch(item));
  const internalHistory = safeList(activityFeed).filter(
    (entry) =>
      (entry.entityType === 'order' && entry.entityId === order.id) ||
      baseTerms.some((term) => normalizeSearch(`${entry.title} ${entry.text} ${entry.keywords || ''}`).includes(term)),
  );
  const apiHistory = safeList(detail?.logs).map((entry) =>
    createActivityEntry({
      id: `order-log-${entry.id}`,
      title: entry.action || 'Movimento do pedido',
      text: entry.message || 'Sem descrição adicional.',
      badge: entry.adminUsername || 'Sistema',
      badgeTone: 'neutral',
      createdAt: entry.createdAt,
      targetView: 'orders',
      entityType: 'order',
      entityId: order.id,
      keywords: `${order.publicId} ${order.productName}`,
    }),
  );
  const summaryEntries = [
    createActivityEntry({
      id: `order-origin-${order.id}`,
      title: 'Pedido carregado no desktop',
      text: `${order.publicId} está em "${getStatusLabel(detail?.statusLabels, order.status)}".`,
      badge: 'pedido',
      badgeTone: tone(order.status),
      createdAt: resolveOrderTimestamp(order) || Date.now(),
      targetView: 'orders',
      entityType: 'order',
      entityId: order.id,
      keywords: `${order.publicId} ${order.productName}`,
    }),
  ];

  if (ownerEntry?.username) {
    summaryEntries.push(
      createActivityEntry({
        id: `order-owner-${order.id}`,
        title: ownerEntry.shared ? 'Responsável compartilhado definido' : 'Responsável definido no desktop',
        text: ownerEntry.shared
          ? `${ownerEntry.username} assumiu o acompanhamento deste pedido para toda a equipe.`
          : `${ownerEntry.username} assumiu o acompanhamento deste pedido.`,
        badge: 'responsável',
        badgeTone: 'good',
        createdAt: ownerEntry.assignedAt || Date.now(),
        targetView: 'orders',
        entityType: 'order',
        entityId: order.id,
        keywords: `${order.publicId} ${ownerEntry.username}`,
      }),
    );
  }

  return [...summaryEntries, ...internalHistory, ...apiHistory]
    .sort((a, b) => toTimestamp(b.createdAt) - toTimestamp(a.createdAt))
    .slice(0, 12);
}

export function buildOrderSummaryText(order, statusLabels, ownerEntry) {
  if (!order) {
    return '';
  }

  const sla = getOrderSla(order);
  const lines = [
    `Pedido: ${order.publicId || '-'}`,
    `Produto: ${order.productName || '-'}`,
    `Status: ${getStatusLabel(statusLabels, order.status)}`,
    `Quantidade: ${order.quantity ?? '-'}`,
    `Valor: ${fmtMoney(order.totalAmount)}`,
    `Contato: ${order.contactValue || '-'}`,
    `Servidor: ${order.inGameServer || '-'}`,
    `Nick: ${order.inGameNick || '-'}`,
    `SLA: ${sla.label} (${sla.description})`,
    `Responsável: ${ownerEntry?.username || 'Não definido'}`,
  ];

  return lines.join('\n');
}

export function buildUserSummaryText(detail) {
  const user = detail?.user;
  if (!user) {
    return '';
  }

  const inventory = safeList(detail?.inventory);
  const unlocked = inventory.filter((item) => item.isUnlocked).length;
  const lines = [
    `Usuário: ${user.username || '-'}`,
    `E-mail: ${user.email || '-'}`,
    `Cargo: ${user.role || 'Sem cargo'}`,
    `Status: ${user.isBanned ? 'Banido' : 'Ativo'}`,
    `Coins: ${fmtCount(user.marketCoins ?? 0)}`,
    `Último login: ${fmtDate(user.lastLoginAt)}`,
    `Inventário: ${fmtCount(inventory.length)} itens (${fmtCount(unlocked)} liberados)`,
  ];

  return lines.join('\n');
}

export function buildInviteShareText(invite) {
  const link = String(invite?.registrationUrl ?? '').trim();
  const code = String(invite?.inviteKey ?? '').trim();

  if (!link && !code) {
    return '';
  }

  return [
    'Convite Bolfer para novo staff:',
    link || `Código: ${code}`,
    '',
    'Este convite cria apenas conta staff.',
    'Não existe criação de founder por convite.',
  ].join('\n');
}

export function buildSearchResults({ query, orders, users, products, logs, activityFeed, statusLabels, hideSensitiveInfo = false }) {
  const normalizedQuery = normalizeSearch(query);
  if (!normalizedQuery) {
    return [];
  }

  const orderResults = safeList(orders)
    .filter((order) =>
      [order.publicId, order.productName, order.inGameNick, order.contactValue]
        .filter(Boolean)
        .some((value) => normalizeSearch(value).includes(normalizedQuery)),
    )
    .slice(0, 4)
    .map((order) => ({
      id: `search-order-${order.id}`,
      title: `Pedido ${order.publicId}`,
      text: `${order.productName} • ${getStatusLabel(statusLabels, order.status)}`,
      meta: fmtMoney(order.totalAmount),
      toneValue: tone(order.status),
      targetView: 'orders',
      entityId: order.id,
      entityType: 'order',
    }));

  const userResults = safeList(users)
    .filter((user) =>
      [user.username, user.email, user.role]
        .filter(Boolean)
        .some((value) => normalizeSearch(value).includes(normalizedQuery)),
    )
    .slice(0, 4)
    .map((user) => ({
      id: `search-user-${user.id}`,
      title: user.username ? (hideSensitiveInfo ? maskSensitiveValue(user.username, user.username.includes('@') ? 'email' : 'generic') : user.username) : 'Usuário',
      text: `${user.email ? (hideSensitiveInfo ? maskSensitiveValue(user.email, 'email') : user.email) : 'Sem e-mail'} • ${user.isBanned ? 'Banido' : 'Ativo'}`,
      meta: user.role || 'Sem cargo',
      toneValue: user.isBanned ? 'danger' : 'good',
      targetView: 'users',
      entityId: user.id,
      entityType: 'user',
    }));

  const productResults = safeList(products)
    .filter((product) =>
      [product.name, product.slug, product.categoryName, product.productType]
        .filter(Boolean)
        .some((value) => normalizeSearch(value).includes(normalizedQuery)),
    )
    .slice(0, 4)
    .map((product) => ({
      id: `search-product-${product.id}`,
      title: product.name || 'Produto',
      text: `${product.categoryName || 'Sem categoria'} • ${product.productType === 'conta' ? 'Conta' : 'Item'}`,
      meta: product.isActive ? 'Ativo' : 'Oculto',
      toneValue: product.isActive ? 'good' : 'warn',
      targetView: 'products',
      entityId: product.id,
      entityType: 'product',
    }));

  const logResults = safeList(logs)
    .filter((entry) => normalizeSearch(`${entry.title} ${entry.text} ${entry.meta || ''}`).includes(normalizedQuery))
    .slice(0, 3)
    .map((entry, index) => ({
      id: `search-log-${index}-${entry.id}`,
      title: entry.title,
      text: entry.text,
      meta: entry.meta,
      toneValue: entry.badgeTone || 'neutral',
      targetView: entry.targetView || 'logs',
      entityId: null,
      entityType: 'log',
    }));

  const activityResults = safeList(activityFeed)
    .filter((entry) => normalizeSearch(`${entry.title} ${entry.text} ${entry.keywords || ''}`).includes(normalizedQuery))
    .slice(0, 3)
    .map((entry) => ({
      id: `search-activity-${entry.id}`,
      title: entry.title,
      text: entry.text,
      meta: entry.meta,
      toneValue: entry.badgeTone || 'neutral',
      targetView: entry.targetView || 'dashboard',
      entityId: entry.entityId,
      entityType: entry.entityType,
    }));

  return [...orderResults, ...userResults, ...productResults, ...logResults, ...activityResults].slice(0, 12);
}

function collectPermissionTokens(permissions) {
  if (Array.isArray(permissions)) {
    return permissions.flatMap((value) => collectPermissionTokens(value));
  }

  if (permissions && typeof permissions === 'object') {
    return Object.entries(permissions).flatMap(([key, value]) => [key, ...collectPermissionTokens(value)]);
  }

  return permissions ? [String(permissions)] : [];
}

export function buildPermissionFlags(permissions, role) {
  const normalizedRole = normalizeSearch(role);
  const permissionMap = permissions && typeof permissions === 'object' && !Array.isArray(permissions) ? permissions : {};
  const roleLevel = Number(permissionMap.roleLevel ?? permissionMap.role_level ?? 0);
  const isFounder = normalizedRole.includes('founder') || roleLevel >= 40 || permissionMap.manageAdmins === true;
  const isAdmin = normalizedRole.includes('admin') || roleLevel >= 30;

  if (isFounder) {
    return {
      dashboard: true,
      orders: true,
      users: true,
      products: true,
      security: true,
      logs: true,
      settings: true,
      invites: true,
      updateOrderStatus: true,
      addOrderNote: true,
      moderateUsers: true,
      adjustCoins: true,
      manageOrderOwnership: true,
      resolveOrderConflicts: true,
      viewSensitiveInfo: true,
      saveSettings: true,
    };
  }

  if (isAdmin) {
    return {
      dashboard: true,
      orders: true,
      users: true,
      products: false,
      security: true,
      logs: true,
      settings: true,
      invites: permissionMap.manageInvites === true,
      updateOrderStatus: true,
      addOrderNote: true,
      moderateUsers: true,
      adjustCoins: true,
      manageOrderOwnership: true,
      resolveOrderConflicts: true,
      viewSensitiveInfo: true,
      saveSettings: true,
    };
  }

  const tokens = collectPermissionTokens(permissions).map((item) => normalizeSearch(item)).filter(Boolean);
  if (!tokens.length) {
    return {
      dashboard: true,
      orders: true,
      users: true,
      products: false,
      security: true,
      logs: true,
      settings: true,
      invites: false,
      updateOrderStatus: true,
      addOrderNote: true,
      moderateUsers: true,
      adjustCoins: true,
      manageOrderOwnership: true,
      resolveOrderConflicts: true,
      viewSensitiveInfo: true,
      saveSettings: true,
    };
  }

  const hasAny = (patterns) => tokens.some((token) => patterns.some((pattern) => token.includes(pattern)));
  const isStaffLike = ['staff', 'moder', 'mod', 'suporte', 'support'].some((pattern) => normalizedRole.includes(pattern));
  const orders = hasAny(['order', 'pedido', 'entrega']);
  const users = hasAny(['user', 'usuario', 'moder', 'ban', 'coin', 'saldo']);
  const products = permissionMap.manageProducts === true || hasAny(['product', 'produto', 'catalog', 'catalogo', 'catálogo']);
  const logs = hasAny(['log', 'audit', 'auditoria', 'access', 'mercado', 'market']);
  const security = hasAny(['ban', 'access', 'ip', 'seguranca', 'segurança', 'risco', 'audit', 'auditoria', 'tentativa']);
  const settings = hasAny(['config', 'setting', 'desktop']);
  const invites = permissionMap.manageInvites === true || hasAny(['invite', 'convite']);
  const updateOrderStatus = permissionMap.updateOrderStatus === true || permissionMap.manageOrders === true || hasAny(['order.status', 'pedido.status', 'entrega', 'pedido']);
  const addOrderNote = permissionMap.addOrderNote === true || permissionMap.manageOrders === true || hasAny(['note', 'nota', 'pedido']);
  const moderateUsers = permissionMap.moderateUsers === true || permissionMap.manageUsers === true || hasAny(['ban', 'moder', 'usuario']);
  const adjustCoins = permissionMap.adjustCoins === true || hasAny(['coin', 'saldo', 'market']);
  const manageOrderOwnership = hasAny(['owner', 'responsavel', 'responsável', 'assignee', 'pedido', 'order.claim', 'order.assign']);
  const resolveOrderConflicts = hasAny(['owner', 'responsavel', 'responsável', 'conflict', 'workspace', 'pedido']);
  const viewSensitiveInfo =
    permissionMap.viewSensitiveInfo === true || permissionMap.manageSettings === true || permissionMap.manageAdmins === true || hasAny(['sensitive', 'pii', 'security', 'admin', 'config', 'setting']);
  const saveSettings = permissionMap.saveSettings === true || permissionMap.manageSettings === true || hasAny(['config', 'setting', 'desktop.write', 'desktop.manage']);
  const canManageOrderOwnership = permissionMap.manageOrderOwnership === true || permissionMap.manageOrders === true || manageOrderOwnership;
  const canResolveOrderConflicts = permissionMap.resolveOrderConflicts === true || permissionMap.manageOrders === true || resolveOrderConflicts;

  return {
    dashboard: true,
    orders: orders || updateOrderStatus || addOrderNote,
    users: users || moderateUsers || adjustCoins,
    products,
    security: security || logs || moderateUsers,
    logs,
    settings: settings || saveSettings || viewSensitiveInfo,
    invites,
    updateOrderStatus,
    addOrderNote,
    moderateUsers,
    adjustCoins,
    manageOrderOwnership: canManageOrderOwnership || orders || isStaffLike,
    resolveOrderConflicts: canResolveOrderConflicts || canManageOrderOwnership || isStaffLike,
    viewSensitiveInfo,
    saveSettings: saveSettings || settings,
  };
}

export function filterNavItemsByPermissions(items, flags) {
  return safeList(items).filter((item) => flags[item.id] !== false);
}
