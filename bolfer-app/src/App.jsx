import { useEffect, useRef, useState } from 'react';
import { normalizeApiBaseUrl, requestApi, requestOptionalApi } from './api.js';
import {
  AUTO_REFRESH_OPTIONS,
  COIN_TEMPLATES,
  DEFAULT_API_BASE_URL,
  DEFAULT_CONFIG,
  DEFAULT_UI_PREFS,
  INVITE_FILTERS,
  LOG_FILTERS,
  NAV_ITEMS,
  ORDER_FILTERS,
  ORDER_NOTE_TEMPLATES,
  ORDER_QUICK_STATUSES,
  PRODUCT_FILTERS,
  SECURITY_FILTERS,
  SHORTCUT_HINTS,
  STORAGE_KEYS,
  USER_FILTERS,
  USER_BAN_REASONS,
  buildDashboardTasks,
  buildDashboardAlerts,
  buildInviteShareText,
  buildInviteStats,
  buildLogFeed,
  buildLogAlerts,
  buildMarketHighlights,
  buildOrderStats,
  buildOrderAlerts,
  buildPermissionFlags,
  buildOrderSummaryText,
  buildOrderTimeline,
  buildSearchResults,
  buildSecurityHighlights,
  buildUserSummaryText,
  buildUserStats,
  buildUserAlerts,
  buildUserHistory,
  createActivityEntry,
  filterOrders,
  filterNavItemsByPermissions,
  filterUsers,
  fmtDate,
  fmtRelativeDate,
  formatEndpoint,
  getStatusLabel,
  getOrderSla,
  getViewMeta,
  latestTimestamp,
  maskSensitiveValue,
  normalizePresenceStatus,
  presenceLabel,
  readStoredValue,
  safeList,
  writeStoredValue,
} from './app-utils.js';
import { CommandBar, Modal, Notice, SearchResults } from './ui.jsx';
import { DashboardView, InvitesWorkspaceView, LoginScreen, LogsWorkspaceView, OrdersView, PresenceStrip, ProductsWorkspaceView, SecurityWorkspaceView, SettingsView, Sidebar, Topbar, UsersView } from './views.jsx';

const bridge = window.bolferDesktop ?? {
  getConfig: async () => ({ ...DEFAULT_CONFIG }),
  saveConfig: async (patch) => patch,
  setPresence: async () => ({ ok: false }),
  clearPresence: async () => ({ ok: true }),
  getPresenceStatus: async () => ({ ok: false, reason: 'browser', message: 'O Rich Presence só funciona no app desktop aberto pelo Electron.' }),
  getAppInfo: async () => ({ version: 'dev', platform: 'web' }),
  openExternal: async (url) => window.open(url, '_blank', 'noopener,noreferrer'),
  getUpdateState: async () => ({
    supported: false,
    status: 'unsupported',
    message: 'As atualizações automáticas ficam disponíveis apenas no desktop instalado no Windows.',
    currentVersion: 'dev',
    availableVersion: '',
    downloadedVersion: '',
    progress: 0,
    lastCheckedAt: null,
    lastError: null,
  }),
  checkForUpdates: async () => ({
    supported: false,
    status: 'unsupported',
    message: 'As atualizações automáticas ficam disponíveis apenas no desktop instalado no Windows.',
    currentVersion: 'dev',
    availableVersion: '',
    downloadedVersion: '',
    progress: 0,
    lastCheckedAt: null,
    lastError: null,
  }),
  installUpdate: async () => ({
    supported: false,
    status: 'unsupported',
    message: 'As atualizações automáticas ficam disponíveis apenas no desktop instalado no Windows.',
    currentVersion: 'dev',
    availableVersion: '',
    downloadedVersion: '',
    progress: 0,
    lastCheckedAt: null,
    lastError: null,
  }),
  onUpdateState: () => () => {},
};

function createLocalWorkspaceState(message = 'Sincronização compartilhada indisponível nesta API.') {
  return {
    supported: false,
    mode: 'local',
    owner: null,
    viewers: [],
    otherViewers: [],
    conflict: false,
    message,
    updatedAt: null,
  };
}

function resolveAdminPanelUrl(apiBaseUrl) {
  const normalized = normalizeApiBaseUrl(apiBaseUrl);
  if (!normalized) {
    return '';
  }

  if (normalized.endsWith('/api/desktop')) {
    return `${normalized.slice(0, -'/api/desktop'.length)}/admin/login`;
  }

  return normalized;
}

function resolveSiteBaseUrl(apiBaseUrl) {
  const normalized = normalizeApiBaseUrl(apiBaseUrl);
  if (!normalized) {
    return '';
  }

  return normalized.endsWith('/api/desktop') ? normalized.slice(0, -'/api/desktop'.length) : normalized;
}

function createDefaultUpdateState(version = '0.0.0') {
  return {
    supported: false,
    status: 'unsupported',
    message: 'As atualizações automáticas ficam disponíveis apenas no desktop instalado no Windows.',
    currentVersion: version,
    availableVersion: '',
    downloadedVersion: '',
    progress: 0,
    lastCheckedAt: null,
    lastError: null,
  };
}

function slugifyProductName(value) {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 120);
}

function createEmptyCategoryDraft(category = null) {
  return {
    id: category?.id ?? null,
    name: category?.name ?? '',
    slug: category?.slug ?? '',
    isActive: category?.isActive ?? true,
    sortOrder: category?.sortOrder !== undefined && category?.sortOrder !== null ? String(category.sortOrder) : '0',
  };
}

function createEmptyProductDraft(defaults = {}, product = null) {
  return {
    id: product?.id ?? null,
    categoryId: String(product?.categoryId ?? ''),
    name: product?.name ?? '',
    slug: product?.slug ?? '',
    unitPrice: product?.unitPrice !== undefined && product?.unitPrice !== null ? String(product.unitPrice) : '',
    stock: product?.stock !== undefined && product?.stock !== null ? String(product.stock) : '',
    minimumQuantity: product?.minimumQuantity !== undefined && product?.minimumQuantity !== null ? String(product.minimumQuantity) : '1',
    serverLabel: product?.serverLabel ?? defaults.serverLabel ?? 'LDMO Omegamon',
    deliveryEta: product?.deliveryEta ?? defaults.deliveryEta ?? '5min-1h',
    deliveryMethod: product?.deliveryMethod ?? '',
    productType: product?.productType ?? 'item',
    productDescription: product?.productDescription ?? '',
    accountInfo: product?.accountInfo ?? '',
    description: product?.description ?? defaults.description ?? '',
    notes: product?.notes ?? defaults.notes ?? '',
    isActive: product?.isActive ?? true,
    existingAccountImages: safeList(product?.accountImages),
    removeAccountImages: [],
    newImages: [],
  };
}

function createProductStats(productList) {
  return safeList(productList).reduce(
    (acc, product) => {
      acc.total += 1;
      if (product.isActive) acc.active += 1;
      if (product.productType === 'conta') acc.accounts += 1;
      if (safeList(product.accountImages).length) acc.withImages += 1;
      return acc;
    },
    { total: 0, active: 0, accounts: 0, withImages: 0 },
  );
}

async function readFileAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result ?? ''));
    reader.onerror = () => reject(new Error(`Não foi possível ler o arquivo ${file.name}.`));
    reader.readAsDataURL(file);
  });
}

function App() {
  const startedAt = useRef(Date.now());
  const hasLoadedRef = useRef({ dashboard: false, orders: false, users: false, products: false, security: false, logs: false, invites: false });
  const activityFeedRef = useRef([]);
  const [booting, setBooting] = useState(true);
  const [config, setConfig] = useState(DEFAULT_CONFIG);
  const [uiPrefs, setUiPrefs] = useState(() => ({
    ...DEFAULT_UI_PREFS,
    ...readStoredValue(STORAGE_KEYS.uiPrefs, DEFAULT_UI_PREFS),
  }));
  const [filterPresets, setFilterPresets] = useState(() => {
    const stored = readStoredValue(STORAGE_KEYS.filterPresets, { orders: [], users: [], products: [], security: [], logs: [] });
    return {
      orders: safeList(stored?.orders),
      users: safeList(stored?.users),
      products: safeList(stored?.products),
      security: safeList(stored?.security),
      logs: safeList(stored?.logs),
    };
  });
  const [orderOwners, setOrderOwners] = useState(() => readStoredValue(STORAGE_KEYS.orderOwners, {}));
  const [sharedOrderOwners, setSharedOrderOwners] = useState({});
  const [session, setSession] = useState({ token: '', admin: null, permissions: null });
  const [appInfo, setAppInfo] = useState({ version: '0.0.0', platform: 'unknown' });
  const [updateState, setUpdateState] = useState(() => createDefaultUpdateState());
  const [view, setView] = useState('dashboard');
  const [busy, setBusy] = useState('');
  const [notice, setNotice] = useState(null);
  const [activityFeed, setActivityFeed] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [confirmState, setConfirmState] = useState(null);
  const [confirmBusy, setConfirmBusy] = useState(false);
  const [apiInfo, setApiInfo] = useState(null);
  const [presenceStatus, setPresenceStatus] = useState(() =>
    normalizePresenceStatus(
      { reason: 'loading', message: 'Carregando status do Discord...' },
      DEFAULT_CONFIG,
      { platform: 'unknown' },
    ),
  );
  const [login, setLogin] = useState({
    apiBaseUrl: DEFAULT_API_BASE_URL,
    username: '',
    password: '',
    twoFactorCode: '',
    twoFactorRequired: false,
    twoFactorSetupRequired: false,
    twoFactorMessage: '',
    discordClientId: DEFAULT_CONFIG.discordClientId,
    presenceEnabled: true,
  });
  const [dashboard, setDashboard] = useState(null);
  const [orders, setOrders] = useState({ orders: [], statusLabels: {}, products: [] });
  const [productsData, setProductsData] = useState({ products: [], categories: [], defaults: {}, policy: null, typeOptions: [] });
  const [productFilters, setProductFilters] = useState({ ...PRODUCT_FILTERS });
  const [productId, setProductId] = useState(null);
  const [productDraft, setProductDraft] = useState(() => createEmptyProductDraft());
  const [categoryDraft, setCategoryDraft] = useState(() => createEmptyCategoryDraft());
  const [orderId, setOrderId] = useState(null);
  const [orderDetail, setOrderDetail] = useState(null);
  const [orderWorkspace, setOrderWorkspace] = useState(() => createLocalWorkspaceState());
  const [orderStatus, setOrderStatus] = useState('');
  const [orderAuditReason, setOrderAuditReason] = useState('');
  const [orderNote, setOrderNote] = useState('');
  const [orderFilters, setOrderFilters] = useState({ ...ORDER_FILTERS });
  const [users, setUsers] = useState([]);
  const [userId, setUserId] = useState(null);
  const [userDetail, setUserDetail] = useState(null);
  const [userFilters, setUserFilters] = useState({ ...USER_FILTERS });
  const [moderationReason, setModerationReason] = useState('');
  const [coinForm, setCoinForm] = useState({ amount: '', note: '' });
  const [logs, setLogs] = useState(null);
  const [logFilters, setLogFilters] = useState({ ...LOG_FILTERS });
  const [securityLogs, setSecurityLogs] = useState(null);
  const [securityFilters, setSecurityFilters] = useState({ ...SECURITY_FILTERS });
  const [invites, setInvites] = useState({ invites: [], summary: null, policy: null, filters: { ...INVITE_FILTERS } });
  const [inviteFilters, setInviteFilters] = useState({ ...INVITE_FILTERS });
  const [lastUpdated, setLastUpdated] = useState({});
  const permissionFlags = buildPermissionFlags(session.permissions, session.admin?.role);
  const navItems = filterNavItemsByPermissions(NAV_ITEMS, permissionFlags);

  function touchUpdated(key) {
    setLastUpdated((current) => ({ ...current, [key]: Date.now() }));
  }

  async function notifyAboutActivity(entries) {
    const primaryEntry = safeList(entries)[0];
    if (!primaryEntry) {
      return;
    }

    if (uiPrefs.soundAlertsEnabled && typeof window !== 'undefined') {
      const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
      if (AudioContextCtor) {
        try {
          const context = new AudioContextCtor();
          const oscillator = context.createOscillator();
          const gain = context.createGain();
          oscillator.type = primaryEntry.badgeTone === 'danger' ? 'sawtooth' : 'sine';
          oscillator.frequency.value = primaryEntry.badgeTone === 'danger' ? 280 : primaryEntry.badgeTone === 'warn' ? 420 : 520;
          gain.gain.value = primaryEntry.badgeTone === 'danger' ? 0.04 : 0.03;
          oscillator.connect(gain);
          gain.connect(context.destination);
          oscillator.start();
          oscillator.stop(context.currentTime + 0.16);
          oscillator.onended = () => {
            void context.close();
          };
        } catch {
          // Intencional: falha de áudio local não deve travar o painel.
        }
      }
    }

    if (!uiPrefs.desktopNotificationsEnabled || typeof Notification === 'undefined') {
      return;
    }

    try {
      let permission = Notification.permission;
      if (permission === 'default') {
        permission = await Notification.requestPermission();
      }

      if (permission === 'granted') {
        new Notification(`Bolfer Desktop • ${primaryEntry.title}`, {
          body: primaryEntry.text,
          silent: true,
        });
      }
    } catch {
      // Intencional: a notificação local é opcional.
    }
  }

  function pushActivities(entries) {
    const normalizedEntries = safeList(entries)
      .filter((entry) => entry?.title)
      .map((entry) => (entry.meta ? entry : createActivityEntry(entry)));

    if (!normalizedEntries.length) {
      return;
    }

    const knownIds = new Set(activityFeedRef.current.map((entry) => entry.id));
    const freshEntries = normalizedEntries.filter((entry) => !knownIds.has(entry.id));

    setActivityFeed((current) => {
      const dedupedCurrent = current.filter((existing) => !normalizedEntries.some((entry) => entry.id === existing.id));
      return [...normalizedEntries, ...dedupedCurrent].slice(0, 18);
    });

    if (freshEntries.length) {
      void notifyAboutActivity(freshEntries);
    }
  }

  function pushActivity(entry) {
    pushActivities([entry]);
  }

  function clearActivityFeed() {
    setActivityFeed([]);
  }

  async function copyText(value, successText) {
    const nextValue = String(value ?? '').trim();
    if (!nextValue) {
      setNotice({ type: 'error', text: 'Nada para copiar neste momento.' });
      return;
    }

    try {
      if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(nextValue);
      } else {
        const helper = document.createElement('textarea');
        helper.value = nextValue;
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();
        document.execCommand('copy');
        document.body.removeChild(helper);
      }

      setNotice({ type: 'success', text: successText });
    } catch {
      setNotice({ type: 'error', text: 'Não foi possível copiar o resumo para a área de transferência.' });
    }
  }

  function promptFilterName(scopeLabel) {
    if (typeof window === 'undefined') {
      return '';
    }

    return window.prompt(`Nome para o filtro salvo de ${scopeLabel}:`, '')?.trim() ?? '';
  }

  function saveFilterPreset(scope, filters, scopeLabel) {
    const name = promptFilterName(scopeLabel);
    if (!name) {
      return;
    }

    setFilterPresets((current) => {
      const nextPreset = {
        id: `${scope}-${Date.now()}`,
        name,
        values: { ...filters },
      };
      const next = {
        ...current,
        [scope]: [nextPreset, ...safeList(current[scope]).filter((preset) => preset.name !== name)].slice(0, 6),
      };
      return next;
    });
    setNotice({ type: 'success', text: `Filtro "${name}" salvo para ${scopeLabel}.` });
  }

  function removeFilterPreset(scope, preset, scopeLabel) {
    setConfirmState({
      title: 'Remover filtro salvo',
      text: `Deseja remover o filtro "${preset.name}" de ${scopeLabel}?`,
      confirmLabel: 'Remover filtro',
      toneValue: 'danger',
      action: async () => {
        setFilterPresets((current) => ({
          ...current,
          [scope]: safeList(current[scope]).filter((item) => item.id !== preset.id),
        }));
        setNotice({ type: 'success', text: `Filtro "${preset.name}" removido.` });
      },
    });
  }

  function applyFilterPreset(scope, preset) {
    if (!preset?.values) {
      return;
    }

    if (scope === 'orders') {
      setOrderFilters({ ...ORDER_FILTERS, ...preset.values });
      setNotice({ type: 'success', text: `Filtro "${preset.name}" aplicado em pedidos.` });
      return;
    }

    if (scope === 'users') {
      setUserFilters({ ...USER_FILTERS, ...preset.values });
      setNotice({ type: 'success', text: `Filtro "${preset.name}" aplicado em usuários.` });
      return;
    }

    if (scope === 'products') {
      setProductFilters({ ...PRODUCT_FILTERS, ...preset.values });
      setNotice({ type: 'success', text: `Filtro "${preset.name}" aplicado em produtos.` });
      return;
    }

    if (scope === 'security') {
      const next = { ...SECURITY_FILTERS, ...preset.values, scope: SECURITY_FILTERS.scope };
      setSecurityFilters(next);
      void loadSecurityLogs(next);
      setNotice({ type: 'success', text: `Filtro "${preset.name}" aplicado em banimentos.` });
      return;
    }

    const next = { ...LOG_FILTERS, ...preset.values, scope: LOG_FILTERS.scope };
    setLogFilters(next);
    void loadLogs(next);
    setNotice({ type: 'success', text: `Filtro "${preset.name}" aplicado em logs.` });
  }

  async function toggleDesktopNotifications() {
    const nextEnabled = !uiPrefs.desktopNotificationsEnabled;
    if (nextEnabled && typeof Notification === 'undefined') {
      setNotice({ type: 'error', text: 'Este ambiente não oferece suporte para notificações do sistema.' });
      return;
    }

    if (nextEnabled && typeof Notification !== 'undefined' && Notification.permission === 'default') {
      try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          setNotice({ type: 'error', text: 'As notificações do sistema não foram liberadas pelo Windows.' });
          setUiPrefs((current) => ({ ...current, desktopNotificationsEnabled: false }));
          return;
        }
      } catch {
        setNotice({ type: 'error', text: 'Não foi possível solicitar permissão para notificações.' });
        return;
      }
    }

    setUiPrefs((current) => ({ ...current, desktopNotificationsEnabled: nextEnabled }));
  }

  function toggleCompactMode() {
    setUiPrefs((current) => ({ ...current, compactMode: !current.compactMode }));
  }

  function toggleSoundAlerts() {
    setUiPrefs((current) => ({ ...current, soundAlertsEnabled: !current.soundAlertsEnabled }));
  }

  function toggleSensitiveInfo() {
    if (!permissionFlags.viewSensitiveInfo) {
      return;
    }

    setUiPrefs((current) => ({ ...current, hideSensitiveInfo: !current.hideSensitiveInfo }));
  }

  function dismissOnboarding() {
    setUiPrefs((current) => ({ ...current, onboardingDismissed: true }));
  }

  function restoreOnboarding() {
    setUiPrefs((current) => ({ ...current, onboardingDismissed: false }));
    setNotice({ type: 'success', text: 'O guia rápido voltou a aparecer no dashboard.' });
  }

  async function confirmAction() {
    if (!confirmState?.action) {
      return;
    }

    setConfirmBusy(true);
    try {
      await confirmState.action();
      setConfirmState(null);
    } finally {
      setConfirmBusy(false);
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function boot() {
      try {
        const [stored, info, rawPresenceStatus, rawUpdateState] = await Promise.all([
          bridge.getConfig(),
          bridge.getAppInfo(),
          bridge.getPresenceStatus(),
          bridge.getUpdateState(),
        ]);
        if (cancelled) return;

        const merged = { ...DEFAULT_CONFIG, ...stored };
        setConfig(merged);
        setAppInfo(info);
        setUpdateState({
          ...createDefaultUpdateState(info.version),
          ...(rawUpdateState ?? {}),
          currentVersion: rawUpdateState?.currentVersion || info.version,
        });
        setPresenceStatus(normalizePresenceStatus(rawPresenceStatus, merged, info));
        setLogin((current) => ({
          ...current,
          apiBaseUrl: merged.apiBaseUrl,
          discordClientId: merged.discordClientId,
          presenceEnabled: merged.presenceEnabled,
        }));

        if (merged.token && merged.apiBaseUrl) {
          try {
            const response = await requestApi({ baseUrl: merged.apiBaseUrl, path: 'auth/me', token: merged.token });
            if (!cancelled) {
              setSession({ token: merged.token, admin: response.data.admin, permissions: response.data.permissions });
            }
          } catch {
            await bridge.saveConfig({ token: '', admin: null });
          }
        }
      } catch (error) {
        if (!cancelled) {
          setNotice({
            type: 'error',
            text: error instanceof Error ? error.message : 'Falha ao carregar as configurações locais do desktop.',
          });
        }
      } finally {
        if (!cancelled) setBooting(false);
      }
    }

    void boot();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    writeStoredValue(STORAGE_KEYS.uiPrefs, uiPrefs);
  }, [uiPrefs]);

  useEffect(() => {
    writeStoredValue(STORAGE_KEYS.filterPresets, filterPresets);
  }, [filterPresets]);

  useEffect(() => {
    writeStoredValue(STORAGE_KEYS.orderOwners, orderOwners);
  }, [orderOwners]);

  useEffect(() => {
    activityFeedRef.current = activityFeed;
  }, [activityFeed]);

  useEffect(() => {
    let active = true;
    const unsubscribe = bridge.onUpdateState?.((nextState) => {
      if (!active) {
        return;
      }

      setUpdateState((current) => ({
        ...current,
        ...(nextState ?? {}),
        currentVersion: nextState?.currentVersion || current.currentVersion || appInfo.version,
      }));
    });

    return () => {
      active = false;
      unsubscribe?.();
    };
  }, [appInfo.version]);

  const updateNoticeRef = useRef('');

  useEffect(() => {
    const signature = `${updateState.status}:${updateState.availableVersion}:${updateState.lastError || ''}`;
    if (signature === updateNoticeRef.current) {
      return;
    }

    updateNoticeRef.current = signature;

    if (updateState.status === 'downloaded') {
      pushActivity(
        createActivityEntry({
          id: `update-ready-${updateState.availableVersion || Date.now()}`,
          title: 'Atualização pronta',
          text: `A versão ${updateState.availableVersion || updateState.downloadedVersion || 'nova'} já foi baixada e pode ser instalada no reinício do app.`,
          badge: 'update',
          badgeTone: 'good',
          targetView: 'settings',
          keywords: `${updateState.availableVersion || ''} atualizacao update`,
        }),
      );
      setNotice({ type: 'success', text: 'Nova versão pronta. Abra Configurações e clique em "Reiniciar e atualizar".' });
      return;
    }

    if (updateState.status === 'error' && updateState.lastError) {
      pushActivity(
        createActivityEntry({
          id: `update-error-${Date.now()}`,
          title: 'Falha ao verificar atualizações',
          text: updateState.lastError,
          badge: 'update',
          badgeTone: 'danger',
          targetView: 'settings',
          keywords: `${updateState.lastError} atualizacao update`,
        }),
      );
    }
  }, [updateState.availableVersion, updateState.downloadedVersion, updateState.lastError, updateState.status]);

  useEffect(() => {
    document.body.classList.toggle('compact-mode', uiPrefs.compactMode);

    return () => {
      document.body.classList.remove('compact-mode');
    };
  }, [uiPrefs.compactMode]);

  useEffect(() => {
    if (!permissionFlags.viewSensitiveInfo && !uiPrefs.hideSensitiveInfo) {
      setUiPrefs((current) => ({ ...current, hideSensitiveInfo: true }));
    }
  }, [permissionFlags.viewSensitiveInfo, uiPrefs.hideSensitiveInfo]);

  useEffect(() => {
    if (!session.admin || navItems.some((item) => item.id === view)) {
      return;
    }

    setView(navItems[0]?.id || 'dashboard');
  }, [navItems, session.admin, view]);

  useEffect(() => {
    let cancelled = false;

    async function syncPresence() {
      const currentConfig = config;

      if (!session.admin) {
        const result = await bridge.clearPresence();
        if (!cancelled) {
          setPresenceStatus(
            normalizePresenceStatus(
              {
                ...result,
                enabled: currentConfig.presenceEnabled,
                configuredClientId: currentConfig.discordClientId,
                reason: 'logged_out',
              },
              currentConfig,
              appInfo,
            ),
          );
        }
        return;
      }

      if (!currentConfig.presenceEnabled || !currentConfig.discordClientId) {
        const result = await bridge.clearPresence();
        if (!cancelled) {
          setPresenceStatus(
            normalizePresenceStatus(
              {
                ...result,
                enabled: currentConfig.presenceEnabled,
                configuredClientId: currentConfig.discordClientId,
                reason: currentConfig.presenceEnabled ? 'missing_client_id' : 'disabled',
              },
              currentConfig,
              appInfo,
            ),
          );
        }
        return;
      }

      const state =
        view === 'orders'
          ? orderDetail?.order?.publicId
            ? `Pedido ${orderDetail.order.publicId}`
            : 'Gerenciando pedidos'
          : view === 'products'
            ? productDraft?.name
              ? `Produto ${productDraft.name}`
              : 'Gerenciando produtos'
          : view === 'users'
            ? userDetail?.user?.username
              ? `Usuário ${userDetail.user.username}`
              : 'Gerenciando usuários'
            : view === 'security'
              ? 'Auditando banimentos'
            : view === 'logs'
              ? 'Auditando logs'
              : view === 'invites'
                ? 'Gerenciando convites'
              : view === 'settings'
                ? 'Ajustando preferências'
                : 'Painel da equipe';

      try {
        const result = await bridge.setPresence({ details: 'Bolfer Desktop', state, startTimestamp: startedAt.current });
        if (!cancelled) {
          setPresenceStatus(normalizePresenceStatus(result, currentConfig, appInfo));
        }
      } catch (error) {
        if (!cancelled) {
          setPresenceStatus(
            normalizePresenceStatus(
              {
                ok: false,
                reason: 'renderer_error',
                message: error instanceof Error ? error.message : 'Falha ao sincronizar o Discord.',
              },
              currentConfig,
              appInfo,
            ),
          );
        }
      }
    }

    void syncPresence();
    return () => {
      cancelled = true;
    };
  }, [appInfo, config, orderDetail, productDraft.name, session.admin, userDetail, view]);

  useEffect(() => {
    if (!session.token || !config.apiBaseUrl) return;
    if (view === 'dashboard') void loadDashboard();
    if (view === 'orders') void loadOrders();
    if (view === 'users') void loadUsers();
    if (view === 'products') void loadProducts();
    if (view === 'security') void loadSecurityLogs();
    if (view === 'logs') void loadLogs();
    if (view === 'invites') void loadInvites();
  }, [config.apiBaseUrl, permissionFlags.products, session.token, view]);

  useEffect(() => {
    if (view === 'orders' && session.token && orderId) void loadOrder(orderId, { preserveDrafts: false });
  }, [config.apiBaseUrl, orderId, session.token, view]);

  useEffect(() => {
    if (view !== 'orders' || !session.token || !orderId) {
      setOrderWorkspace(createLocalWorkspaceState());
      return undefined;
    }

    let disposed = false;

    void updateOrderWorkspace(orderId, 'open').catch(() => {
      if (!disposed) {
        setOrderWorkspace(createLocalWorkspaceState());
      }
    });

    return () => {
      disposed = true;
      void updateOrderWorkspace(orderId, 'close', {}, { syncState: false }).catch(() => {
        // Sem efeito colateral local quando a API ainda não suporta o workspace.
      });
    };
  }, [config.apiBaseUrl, orderId, session.token, view]);

  useEffect(() => {
    if (view === 'users' && session.token && userId) void loadUser(userId, { preserveDrafts: false });
  }, [config.apiBaseUrl, session.token, userId, view]);

  useEffect(() => {
    if (!session.token || !config.apiBaseUrl || !uiPrefs.autoRefreshEnabled) {
      return undefined;
    }

    const intervalMs = Math.max(10, Number(uiPrefs.autoRefreshInterval) || DEFAULT_UI_PREFS.autoRefreshInterval) * 1000;
    const timerId = window.setInterval(() => {
      if (busy) {
        return;
      }

      void refreshCurrentView({ preserveDrafts: true });
    }, intervalMs);

    return () => {
      window.clearInterval(timerId);
    };
  }, [busy, config.apiBaseUrl, session.token, uiPrefs.autoRefreshEnabled, uiPrefs.autoRefreshInterval, view, logFilters, securityFilters, inviteFilters, orderId, userId]);

  useEffect(() => {
    if (!session.admin) {
      return undefined;
    }

    function isTypingTarget(target) {
      const element = target instanceof HTMLElement ? target : null;
      if (!element) {
        return false;
      }

      const tagName = element.tagName.toLowerCase();
      return tagName === 'input' || tagName === 'textarea' || tagName === 'select' || element.isContentEditable;
    }

    function handleKeydown(event) {
      const key = String(event.key || '').toLowerCase();

      if ((event.ctrlKey || event.metaKey) && key === 'k') {
        event.preventDefault();
        document.getElementById('global-search-input')?.focus();
        return;
      }

      if (key === 'escape') {
        if (confirmState) {
          event.preventDefault();
          setConfirmState(null);
          return;
        }

        if (searchQuery) {
          event.preventDefault();
          setSearchQuery('');
        }
        return;
      }

      if (isTypingTarget(event.target) || event.ctrlKey || event.metaKey || event.altKey) {
        return;
      }

      if (key === 'r') {
        event.preventDefault();
        void refreshCurrentView({ preserveDrafts: true });
        return;
      }

      const index = Number(key);
      if (Number.isInteger(index) && index >= 1 && index <= NAV_ITEMS.length) {
        const targetView = NAV_ITEMS[index - 1]?.id;
        if (targetView && navItems.some((item) => item.id === targetView)) {
          event.preventDefault();
          setView(targetView);
        }
      }
    }

    window.addEventListener('keydown', handleKeydown);
    return () => {
      window.removeEventListener('keydown', handleKeydown);
    };
  }, [confirmState, navItems, searchQuery, session.admin]);

  async function refreshPresenceStatus(nextConfig = config, nextAppInfo = appInfo) {
    const status = await bridge.getPresenceStatus();
    const normalized = normalizePresenceStatus(status, nextConfig, nextAppInfo);
    setPresenceStatus(normalized);
    return normalized;
  }

  async function saveConfigPatch(patch) {
    const next = await bridge.saveConfig(patch);
    const mergedConfig = { ...config, ...next };
    setConfig((current) => ({ ...current, ...next }));
    setLogin((current) => ({
      ...current,
      apiBaseUrl: next.apiBaseUrl ?? current.apiBaseUrl,
      discordClientId: next.discordClientId ?? current.discordClientId,
      presenceEnabled: typeof next.presenceEnabled === 'boolean' ? next.presenceEnabled : current.presenceEnabled,
    }));
    await refreshPresenceStatus(mergedConfig);
    return next;
  }

  async function api(path, options = {}) {
    try {
      return await requestApi({ baseUrl: config.apiBaseUrl, path, token: session.token, ...options });
    } catch (error) {
      if (error.status === 401) await localLogout('Sessão expirada. Faça login novamente.');
      throw error;
    }
  }

  async function apiOptional(path, options = {}, fallbackOptions = {}) {
    try {
      return await requestOptionalApi({ baseUrl: config.apiBaseUrl, path, token: session.token, ...options }, fallbackOptions);
    } catch (error) {
      if (error.status === 401) {
        await localLogout('Sessão expirada. Faça login novamente.');
      }
      throw error;
    }
  }

  function updateLoginTwoFactorState(payload = null, fallbackMessage = '') {
    const data = payload?.data ?? payload ?? {};
    const twoFactorRequired = Boolean(data.twoFactorRequired ?? data.two_factor_required);
    const twoFactorSetupRequired = Boolean(data.twoFactorSetupRequired ?? data.two_factor_setup_required);

    setLogin((current) => ({
      ...current,
      twoFactorRequired,
      twoFactorSetupRequired,
      twoFactorMessage: twoFactorRequired || twoFactorSetupRequired ? String(payload?.message ?? data.message ?? fallbackMessage).trim() : '',
      twoFactorCode: twoFactorRequired && !twoFactorSetupRequired ? current.twoFactorCode : '',
    }));
  }

  function syncSharedOrderOwner(orderKey, ownerEntry) {
    setSharedOrderOwners((current) => {
      if (!orderKey) {
        return current;
      }

      if (ownerEntry?.username) {
        return {
          ...current,
          [orderKey]: ownerEntry,
        };
      }

      const next = { ...current };
      delete next[orderKey];
      return next;
    });
  }

  function normalizeWorkspaceResponse(response, selectedOrderId = orderId) {
    const payload = response?.data ?? response ?? {};
    const ownerPayload = payload.owner ?? payload.assignee ?? null;
    const owner =
      ownerPayload?.username || ownerPayload?.email
        ? {
            username: ownerPayload.username || ownerPayload.email,
            assignedAt: ownerPayload.assignedAt || ownerPayload.createdAt || ownerPayload.updatedAt || Date.now(),
            shared: true,
          }
        : null;
    const viewers = safeList(payload.viewers).map((viewer, index) => ({
      id: viewer.id ?? `${selectedOrderId ?? 'workspace'}-${index}`,
      username: viewer.username || viewer.email || 'Moderador',
      openedAt: viewer.openedAt || viewer.createdAt || viewer.updatedAt || Date.now(),
      isCurrentUser: String(viewer.username || viewer.email || '').toLowerCase() === String(session.admin?.username || '').toLowerCase(),
    }));
    const otherViewers = viewers.filter((viewer) => !viewer.isCurrentUser);
    const supported =
      payload.supported !== false &&
      (Boolean(response) || Boolean(owner) || viewers.length > 0 || typeof payload.conflict === 'boolean' || payload.mode === 'shared');

    if (!supported) {
      return createLocalWorkspaceState(payload.message || 'Sincronização compartilhada indisponível nesta API.');
    }

    return {
      supported: true,
      mode: 'shared',
      owner,
      viewers,
      otherViewers,
      conflict: Boolean(payload.conflict) || otherViewers.length > 0,
      message: payload.message || 'Sincronizado com a API para toda a equipe.',
      updatedAt: Date.now(),
    };
  }

  async function readOrderWorkspace(selectedOrderId) {
    if (!selectedOrderId || !session.token) {
      const localState = createLocalWorkspaceState();
      setOrderWorkspace(localState);
      return localState;
    }

    const response = await apiOptional(`orders/${selectedOrderId}/workspace`, {}, { ignoredStatuses: [404, 405, 422, 501] });
    const nextWorkspace = normalizeWorkspaceResponse(response, selectedOrderId);
    setOrderWorkspace(nextWorkspace);
    syncSharedOrderOwner(selectedOrderId, nextWorkspace.owner);
    return nextWorkspace;
  }

  async function updateOrderWorkspace(selectedOrderId, action, body = {}, options = {}) {
    if (!selectedOrderId || !session.token) {
      return createLocalWorkspaceState();
    }

    const syncState = options.syncState ?? true;
    const response = await apiOptional(
      `orders/${selectedOrderId}/workspace`,
      {
        method: 'POST',
        body: { action, ...body },
      },
      { ignoredStatuses: [404, 405, 422, 501] },
    );
    const nextWorkspace = normalizeWorkspaceResponse(response, selectedOrderId);
    if (syncState) {
      setOrderWorkspace(nextWorkspace);
      syncSharedOrderOwner(selectedOrderId, nextWorkspace.owner);
    }
    return nextWorkspace;
  }

  async function testDiscordPresence() {
    const nextConfig = {
      ...config,
      discordClientId: String(login.discordClientId ?? '').trim(),
      presenceEnabled: login.presenceEnabled,
    };

    if (appInfo.platform === 'web') {
      const normalized = normalizePresenceStatus({ ok: false, reason: 'browser' }, nextConfig, appInfo);
      setPresenceStatus(normalized);
      setNotice({ type: 'error', text: normalized.text });
      return;
    }

    if (!nextConfig.presenceEnabled) {
      const normalized = normalizePresenceStatus({ ok: false, reason: 'disabled' }, nextConfig, appInfo);
      setPresenceStatus(normalized);
      setNotice({ type: 'error', text: normalized.text });
      return;
    }

    if (!nextConfig.discordClientId) {
      const normalized = normalizePresenceStatus({ ok: false, reason: 'missing_client_id' }, nextConfig, appInfo);
      setPresenceStatus(normalized);
      setNotice({ type: 'error', text: normalized.text });
      return;
    }

    setBusy('presence');
    try {
      if (nextConfig.discordClientId !== config.discordClientId || nextConfig.presenceEnabled !== config.presenceEnabled) {
        await saveConfigPatch({
          discordClientId: nextConfig.discordClientId,
          presenceEnabled: nextConfig.presenceEnabled,
        });
      }

      const result = await bridge.setPresence({
        details: 'Bolfer Desktop',
        state: session.admin ? `Conectado como ${session.admin.username}` : 'Teste de conexão',
        startTimestamp: startedAt.current,
      });
      const normalized = normalizePresenceStatus(result, nextConfig, appInfo);
      setPresenceStatus(normalized);
      if (normalized.ok) {
        pushActivity(
          createActivityEntry({
            id: `presence-test-${Date.now()}`,
            title: 'Discord validado no desktop',
            text: 'O Rich Presence respondeu corretamente ao teste manual.',
            badge: 'discord',
            badgeTone: 'good',
            targetView: 'settings',
            keywords: 'discord rich presence',
          }),
        );
      }
      setNotice({ type: normalized.ok ? 'success' : 'error', text: normalized.text });
    } catch (error) {
      const normalized = normalizePresenceStatus(
        {
          ok: false,
          reason: 'renderer_error',
          message: error instanceof Error ? error.message : 'Falha ao testar o Discord.',
        },
        nextConfig,
        appInfo,
      );
      setPresenceStatus(normalized);
      setNotice({ type: 'error', text: normalized.text });
    } finally {
      setBusy('');
    }
  }

  async function verifyApi() {
    const apiBaseUrl = normalizeApiBaseUrl(login.apiBaseUrl);
    if (!apiBaseUrl) {
      setNotice({ type: 'error', text: 'Informe a URL completa da API.' });
      return;
    }

    setBusy('verify');
    try {
      const response = await requestApi({ baseUrl: apiBaseUrl });
      setApiInfo(response.data);
      pushActivity(
        createActivityEntry({
          id: `api-check-${Date.now()}`,
          title: 'API validada com sucesso',
          text: `A conexão com ${formatEndpoint(apiBaseUrl)} respondeu corretamente para o desktop.`,
          badge: 'api',
          badgeTone: 'good',
          targetView: 'settings',
          keywords: apiBaseUrl,
        }),
      );
      setNotice({ type: 'success', text: 'API alcançada com sucesso.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
      setApiInfo(null);
    } finally {
      setBusy('');
    }
  }

  async function localLogout(message) {
    await saveConfigPatch({ token: '', admin: null });
    await bridge.clearPresence();
    setSession({ token: '', admin: null, permissions: null });
    setDashboard(null);
    setOrders({ orders: [], statusLabels: {}, products: [] });
    setProductsData({ products: [], categories: [], defaults: {}, policy: null, typeOptions: [] });
    setProductFilters({ ...PRODUCT_FILTERS });
    setProductId(null);
    setProductDraft(createEmptyProductDraft());
    setCategoryDraft(createEmptyCategoryDraft());
    setOrderId(null);
    setOrderDetail(null);
    setOrderWorkspace(createLocalWorkspaceState());
    setOrderAuditReason('');
    setOrderFilters({ ...ORDER_FILTERS });
    setSharedOrderOwners({});
    setUsers([]);
    setUserId(null);
    setUserDetail(null);
    setUserFilters({ ...USER_FILTERS });
    setModerationReason('');
    setCoinForm({ amount: '', note: '' });
    setSecurityLogs(null);
    setSecurityFilters({ ...SECURITY_FILTERS });
    setLogs(null);
    setLogFilters({ ...LOG_FILTERS });
    setInvites({ invites: [], summary: null, policy: null, filters: { ...INVITE_FILTERS } });
    setInviteFilters({ ...INVITE_FILTERS });
    setView('dashboard');
    setLastUpdated({});
    setActivityFeed([]);
    setSearchQuery('');
    setConfirmState(null);
    setLogin((current) => ({
      ...current,
      password: '',
      twoFactorCode: '',
      twoFactorRequired: false,
      twoFactorSetupRequired: false,
      twoFactorMessage: '',
    }));
    hasLoadedRef.current = { dashboard: false, orders: false, users: false, products: false, security: false, logs: false, invites: false };
    if (message) setNotice({ type: 'success', text: message });
  }

  async function submitLogin(event) {
    event.preventDefault();
    const apiBaseUrl = normalizeApiBaseUrl(login.apiBaseUrl);

    if (!apiBaseUrl || !login.username || !login.password) {
      setNotice({ type: 'error', text: 'Preencha a URL, o usuário e a senha.' });
      return;
    }

    if (login.twoFactorRequired && !login.twoFactorSetupRequired && !String(login.twoFactorCode ?? '').trim()) {
      setNotice({ type: 'error', text: 'Informe o código 2FA ou um código de recuperação para concluir o login.' });
      return;
    }

    setBusy('login');
    try {
      const response = await requestApi({
        baseUrl: apiBaseUrl,
        path: 'auth/login',
        method: 'POST',
        body: {
          username: login.username,
          password: login.password,
          two_factor_code: String(login.twoFactorCode ?? '').trim(),
          device_name: 'Bolfer Desktop',
        },
      });

      await saveConfigPatch({
        apiBaseUrl,
        discordClientId: login.discordClientId,
        presenceEnabled: login.presenceEnabled,
        token: response.data.token,
        admin: response.data.admin,
      });

      setSession({ token: response.data.token, admin: response.data.admin, permissions: response.data.permissions });
      setLogin((current) => ({
        ...current,
        password: '',
        twoFactorCode: '',
        twoFactorRequired: false,
        twoFactorSetupRequired: false,
        twoFactorMessage: '',
      }));
      setView('dashboard');
      pushActivity(
        createActivityEntry({
          id: `login-${Date.now()}`,
          title: 'Sessão iniciada no desktop',
          text: `${response.data.admin.username} entrou no painel para começar a operação.`,
          badge: 'sessão',
          badgeTone: 'good',
          targetView: 'dashboard',
          keywords: response.data.admin.username,
        }),
      );
      setNotice({ type: 'success', text: 'Login realizado no desktop.' });
    } catch (error) {
      updateLoginTwoFactorState(error?.payload, error instanceof Error ? error.message : '');
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function submitLogout() {
    setBusy('logout');
    try {
      await api('auth/logout', { method: 'POST' });
    } catch {
      // A limpeza local segue mesmo se a API falhar.
    } finally {
      await localLogout('Sessão encerrada.');
      setBusy('');
    }
  }

  async function loadDashboard() {
    setBusy('dashboard');
    try {
      const response = await api('dashboard');
      setDashboard((current) => {
        if (hasLoadedRef.current.dashboard) {
          pushActivities(buildDashboardAlerts(current, response.data));
        }
        return response.data;
      });
      hasLoadedRef.current.dashboard = true;
      touchUpdated('dashboard');
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function loadOrders() {
    setBusy('orders');
    try {
      const response = await api('orders');
      setOrders((current) => {
        if (hasLoadedRef.current.orders) {
          pushActivities(buildOrderAlerts(current.orders, response.data.orders, response.data.statusLabels ?? current.statusLabels));
        }
        return response.data;
      });
      hasLoadedRef.current.orders = true;
      const nextId = response.data.orders.some((item) => item.id === orderId) ? orderId : response.data.orders[0]?.id ?? null;
      setOrderId(nextId);
      if (!nextId) setOrderDetail(null);
      touchUpdated('orders');
      return nextId;
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
      return null;
    } finally {
      setBusy('');
    }
  }

  async function loadOrder(id, options = {}) {
    const { preserveDrafts = false } = options;
    try {
      const response = await api(`orders/${id}`);
      setOrderDetail(response.data);
      void readOrderWorkspace(id);
      if (!preserveDrafts) {
        setOrderStatus(response.data.order.status);
        setOrderAuditReason('');
        setOrderNote('');
      }
      touchUpdated('orderDetail');
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    }
  }

  async function refreshOrdersView(options = {}) {
    const { preserveDrafts = true } = options;
    const nextId = await loadOrders();
    if (nextId) await loadOrder(nextId, { preserveDrafts: preserveDrafts && nextId === orderId });
  }

  async function submitOrderStatus(nextStatus) {
    if (!orderId || !nextStatus || !permissionFlags.updateOrderStatus) return;
    const reason = String(orderAuditReason ?? '').trim();
    if (!reason) {
      setNotice({ type: 'error', text: 'Informe o motivo obrigatório antes de alterar o status do pedido.' });
      return;
    }

    setBusy('order-status');
    try {
      await api(`orders/${orderId}/status`, { method: 'POST', body: { status: nextStatus, reason } });
      pushActivity(
        createActivityEntry({
          id: `order-status-${orderId}-${Date.now()}`,
          title: 'Status alterado pela equipe',
          text: `${orderDetail?.order?.publicId || 'Pedido'} foi marcado como "${getStatusLabel(orderDetail?.statusLabels ?? orders.statusLabels, nextStatus)}". Motivo: ${reason}`,
          badge: 'pedido',
          badgeTone: tone(nextStatus),
          targetView: 'orders',
          entityType: 'order',
          entityId: orderId,
          keywords: `${orderDetail?.order?.publicId || ''} ${orderDetail?.order?.productName || ''}`,
        }),
      );
      setOrderAuditReason('');
      await refreshOrdersView({ preserveDrafts: false });
      setNotice({ type: 'success', text: 'Status do pedido atualizado.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function updateOrderStatus(event) {
    event.preventDefault();
    await submitOrderStatus(orderStatus);
  }

  async function submitOrderNote(noteValue) {
    if (!orderId || !String(noteValue ?? '').trim() || !permissionFlags.addOrderNote) return;

    setBusy('order-note');
    try {
      await api(`orders/${orderId}/note`, { method: 'POST', body: { note: String(noteValue).trim() } });
      pushActivity(
        createActivityEntry({
          id: `order-note-${orderId}-${Date.now()}`,
          title: 'Nota interna adicionada',
          text: `${orderDetail?.order?.publicId || 'Pedido'} recebeu um novo contexto interno para a equipe.`,
          badge: 'nota',
          badgeTone: 'neutral',
          targetView: 'orders',
          entityType: 'order',
          entityId: orderId,
          keywords: `${orderDetail?.order?.publicId || ''} ${orderDetail?.order?.productName || ''}`,
        }),
      );
      setOrderNote('');
      await loadOrder(orderId, { preserveDrafts: false });
      setNotice({ type: 'success', text: 'Nota adicionada.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function addOrderNote(event) {
    event.preventDefault();
    await submitOrderNote(orderNote);
  }

  async function loadUsers() {
    setBusy('users');
    try {
      const response = await api('users');
      setUsers((current) => {
        if (hasLoadedRef.current.users) {
          pushActivities(buildUserAlerts(current, response.data.users));
        }
        return response.data.users;
      });
      hasLoadedRef.current.users = true;
      const nextId = response.data.users.some((item) => item.id === userId) ? userId : response.data.users[0]?.id ?? null;
      setUserId(nextId);
      if (!nextId) setUserDetail(null);
      touchUpdated('users');
      return nextId;
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
      return null;
    } finally {
      setBusy('');
    }
  }

  async function loadUser(id, options = {}) {
    const { preserveDrafts = false } = options;
    try {
      const response = await api(`users/${id}`);
      setUserDetail(response.data);
      if (!preserveDrafts) {
        setModerationReason('');
        setCoinForm({ amount: '', note: '' });
      }
      touchUpdated('userDetail');
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    }
  }

  async function refreshUsersView(options = {}) {
    const { preserveDrafts = true } = options;
    const nextId = await loadUsers();
    if (nextId) await loadUser(nextId, { preserveDrafts: preserveDrafts && nextId === userId });
  }

  async function moderateUser(action, options = {}) {
    if (!userId || !permissionFlags.moderateUsers) return;
    const reason = String(options.reason ?? moderationReason ?? '').trim();
    if (!reason) {
      setNotice({ type: 'error', text: action === 'ban' ? 'Informe o motivo obrigatório do banimento.' : 'Informe o motivo obrigatório para remover o banimento.' });
      return;
    }

    setBusy(action);
    try {
      await api(`users/${userId}/${action}`, {
        method: 'POST',
        body: { reason },
      });
      pushActivity(
        createActivityEntry({
          id: `user-${action}-${userId}-${Date.now()}`,
          title: action === 'ban' ? 'Usuário bloqueado pela equipe' : 'Usuário liberado pela equipe',
          text:
            action === 'ban'
              ? `${userDetail?.user?.username || 'Usuário'} foi bloqueado com o motivo: ${reason}`
              : `${userDetail?.user?.username || 'Usuário'} voltou a ficar ativo no desktop. Motivo: ${reason}`,
          badge: 'moderação',
          badgeTone: action === 'ban' ? 'danger' : 'good',
          targetView: 'users',
          entityType: 'user',
          entityId: userId,
          keywords: `${userDetail?.user?.username || ''} ${userDetail?.user?.email || ''}`,
        }),
      );
      setModerationReason('');
      await refreshUsersView({ preserveDrafts: false });
      setNotice({ type: 'success', text: action === 'ban' ? 'Usuário banido.' : 'Usuário desbloqueado.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function submitCoinAdjustment(amountValue, noteValue) {
    if (!userId || !permissionFlags.adjustCoins) return;

    const normalizedAmount = Number(amountValue);
    const normalizedNote = String(noteValue ?? '').trim();
    if (!Number.isFinite(normalizedAmount) || normalizedAmount === 0) {
      setNotice({ type: 'error', text: 'Informe um ajuste válido de coins.' });
      return;
    }

    if (!normalizedNote) {
      setNotice({ type: 'error', text: 'Informe uma observação obrigatória para o ajuste de coins.' });
      return;
    }

    setBusy('coins');
    try {
      await api(`users/${userId}/coins`, {
        method: 'POST',
        body: { amount: normalizedAmount, note: normalizedNote },
      });
      pushActivity(
        createActivityEntry({
          id: `coins-${userId}-${Date.now()}`,
          title: 'Saldo ajustado pela equipe',
          text: `${userDetail?.user?.username || 'Usuário'} recebeu ajuste de ${normalizedAmount} coins.`,
          badge: 'coins',
          badgeTone: normalizedAmount >= 0 ? 'good' : 'warn',
          targetView: 'users',
          entityType: 'user',
          entityId: userId,
          keywords: `${userDetail?.user?.username || ''} ${userDetail?.user?.email || ''}`,
        }),
      );
      await refreshUsersView({ preserveDrafts: false });
      setCoinForm({ amount: '', note: '' });
      setNotice({ type: 'success', text: 'Saldo ajustado.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function adjustCoins(event) {
    event.preventDefault();
    await submitCoinAdjustment(coinForm.amount, coinForm.note);
  }

  function requestBan(reasonTemplate = null) {
    const username = userDetail?.user?.username || 'este usuário';
    const reason = String(reasonTemplate?.reason ?? moderationReason ?? '').trim();
    if (!reason) {
      setNotice({ type: 'error', text: 'Informe um motivo antes de confirmar o banimento.' });
      return;
    }

    setConfirmState({
      title: 'Confirmar banimento',
      text: `Deseja banir ${username}? Motivo aplicado: ${reason}`,
      confirmLabel: 'Banir usuário',
      toneValue: 'danger',
      action: async () => {
        await moderateUser('ban', { reason });
      },
    });
  }

  function requestUnban() {
    const username = userDetail?.user?.username || 'este usuário';
    const reason = String(moderationReason ?? '').trim();
    if (!reason) {
      setNotice({ type: 'error', text: 'Informe um motivo antes de remover o banimento.' });
      return;
    }

    setConfirmState({
      title: 'Confirmar liberação',
      text: `Deseja remover o banimento de ${username}? Motivo aplicado: ${reason}`,
      confirmLabel: 'Desbanir usuário',
      toneValue: 'neutral',
      action: async () => {
        await moderateUser('unban', { reason });
      },
    });
  }

  function requestQuickCoinAdjustment(template) {
    if (!template) {
      return;
    }

    const username = userDetail?.user?.username || 'este usuário';
    setConfirmState({
      title: 'Aplicar ajuste rápido',
      text: `Deseja aplicar ${template.amount} coins para ${username}?`,
      confirmLabel: 'Aplicar ajuste',
      toneValue: template.amount < 0 ? 'danger' : 'neutral',
      action: async () => {
        await submitCoinAdjustment(template.amount, template.note);
      },
    });
  }

  function requestLogout() {
    setConfirmState({
      title: 'Encerrar sessão',
      text: 'Deseja sair do desktop agora? A sessão local será encerrada.',
      confirmLabel: 'Sair do desktop',
      toneValue: 'neutral',
      action: async () => {
        await submitLogout();
      },
    });
  }

  async function assignOrderToCurrentUser() {
    if (!orderId || !session.admin || !permissionFlags.manageOrderOwnership) {
      return;
    }

    const ownerEntry = {
      username: session.admin.username,
      assignedAt: Date.now(),
      shared: false,
    };

    try {
      const workspace = await updateOrderWorkspace(orderId, 'claim');
      if (workspace.supported) {
        setOrderOwners((current) => {
          const next = { ...current };
          delete next[orderId];
          return next;
        });
        pushActivity(
          createActivityEntry({
            id: `order-owner-${orderId}-${Date.now()}`,
            title: 'Pedido assumido por um moderador',
            text: `${session.admin.username} marcou ${orderDetail?.order?.publicId || 'este pedido'} como responsabilidade compartilhada da equipe.`,
            badge: 'responsável',
            badgeTone: 'good',
            targetView: 'orders',
            entityType: 'order',
            entityId: orderId,
            keywords: `${orderDetail?.order?.publicId || ''} ${session.admin.username}`,
          }),
        );
        setNotice({ type: 'success', text: 'Responsável compartilhado definido para este pedido.' });
        return;
      }
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
      return;
    }

    setOrderOwners((current) => ({
      ...current,
      [orderId]: ownerEntry,
    }));
    pushActivity(
      createActivityEntry({
        id: `order-owner-${orderId}-${Date.now()}`,
        title: 'Pedido assumido por um moderador',
        text: `${session.admin.username} marcou ${orderDetail?.order?.publicId || 'este pedido'} como responsabilidade atual no desktop.`,
        badge: 'responsável',
        badgeTone: 'good',
        targetView: 'orders',
        entityType: 'order',
        entityId: orderId,
        keywords: `${orderDetail?.order?.publicId || ''} ${session.admin.username}`,
      }),
    );
    setNotice({ type: 'success', text: 'Responsável local definido para este pedido.' });
  }

  async function releaseOrderOwner() {
    if (!orderId || (!selectedOrderOwner && !orderOwners[orderId])) {
      return;
    }

    if (orderWorkspace.supported) {
      try {
        await updateOrderWorkspace(orderId, 'release');
        setOrderOwners((current) => {
          const next = { ...current };
          delete next[orderId];
          return next;
        });
        pushActivity(
          createActivityEntry({
            id: `order-owner-release-${orderId}-${Date.now()}`,
            title: 'Responsável compartilhado removido',
            text: `${orderDetail?.order?.publicId || 'Este pedido'} voltou a ficar sem responsável definido para a equipe.`,
            badge: 'responsável',
            badgeTone: 'warn',
            targetView: 'orders',
            entityType: 'order',
            entityId: orderId,
            keywords: `${orderDetail?.order?.publicId || ''}`,
          }),
        );
        setNotice({ type: 'success', text: 'Responsável compartilhado removido deste pedido.' });
        return;
      } catch (error) {
        setNotice({ type: 'error', text: error.message });
        return;
      }
    }

    setOrderOwners((current) => {
      const next = { ...current };
      delete next[orderId];
      return next;
    });
    pushActivity(
      createActivityEntry({
        id: `order-owner-release-${orderId}-${Date.now()}`,
        title: 'Responsável local removido',
        text: `${orderDetail?.order?.publicId || 'Este pedido'} voltou a ficar sem responsável definido no desktop.`,
        badge: 'responsável',
        badgeTone: 'warn',
        targetView: 'orders',
        entityType: 'order',
        entityId: orderId,
        keywords: `${orderDetail?.order?.publicId || ''}`,
      }),
    );
    setNotice({ type: 'success', text: 'Responsável local removido deste pedido.' });
  }

  async function copyOrderSummary() {
    await copyText(
      buildOrderSummaryText(orderDetail?.order, orderDetail?.statusLabels ?? orders.statusLabels, selectedOrderOwner),
      'Resumo do pedido copiado.',
    );
  }

  async function copyUserSummary() {
    await copyText(buildUserSummaryText(userDetail), 'Resumo do usuário copiado.');
  }

  function applyProductFilters(productList, filters) {
    const query = String(filters.q ?? '').trim().toLowerCase();

    return safeList(productList).filter((product) => {
      const matchesQuery =
        !query ||
        [product.name, product.slug, product.categoryName, product.productDescription]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(query));
      const matchesStatus =
        filters.status === 'all' ||
        (filters.status === 'active' && product.isActive) ||
        (filters.status === 'hidden' && !product.isActive);
      const matchesType = filters.type === 'all' || product.productType === filters.type;
      const matchesCategory = filters.category === 'all' || String(product.categoryId) === String(filters.category);

      return matchesQuery && matchesStatus && matchesType && matchesCategory;
    });
  }

  function syncProductDraftWithData(nextData, nextProductId = productId) {
    const selectedProduct = safeList(nextData?.products).find((product) => product.id === nextProductId) ?? null;
    if (selectedProduct) {
      setProductDraft(createEmptyProductDraft(nextData?.defaults, selectedProduct));
      return;
    }

    if (nextProductId) {
      setProductId(null);
      setProductDraft(createEmptyProductDraft(nextData?.defaults));
    }
  }

  async function loadProducts(options = {}) {
    const { preserveDraft = true } = options;

    if (!permissionFlags.products) {
      return null;
    }

    setBusy('products');
    try {
      const response = await api('products');
      let nextData = response.data;

      if (!safeList(response.data?.categories).length) {
        const categoryResponse = await requestOptionalApi(
          {
            baseUrl: config.apiBaseUrl,
            path: 'categories',
            token: session.token,
          },
          { ignoredStatuses: [404, 405, 409, 422, 501] },
        );

        if (safeList(categoryResponse?.data?.categories).length) {
          nextData = {
            ...response.data,
            categories: categoryResponse.data.categories,
          };
        }
      }

      setProductsData(nextData);
      const shouldKeepNewDraft =
        preserveDraft &&
        !productId &&
        Boolean(productDraft.name || productDraft.slug || productDraft.newImages.length || productDraft.removeAccountImages.length);

      if (!shouldKeepNewDraft) {
        if (preserveDraft && productId) {
          const selectedProduct = safeList(nextData?.products).find((product) => product.id === productId) ?? null;
          if (!selectedProduct) {
            syncProductDraftWithData(nextData);
          }
        } else {
          syncProductDraftWithData(nextData);
        }
      }
      hasLoadedRef.current.products = true;
      touchUpdated('products');
      return nextData;
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
      return null;
    } finally {
      setBusy('');
    }
  }

  function startNewProductDraft() {
    setProductId(null);
    setProductDraft(createEmptyProductDraft(productsData.defaults));
  }

  function editProduct(product) {
    setProductId(product?.id ?? null);
    setProductDraft(createEmptyProductDraft(productsData.defaults, product));
  }

  function updateProductDraft(field, value) {
    setProductDraft((current) => {
      const next = { ...current, [field]: value };
      if (field === 'name' && (!current.slug || current.slug === slugifyProductName(current.name))) {
        next.slug = slugifyProductName(value);
      }

      if (field === 'productType' && value !== 'conta') {
        next.accountInfo = '';
        next.removeAccountImages = [...current.existingAccountImages];
        next.newImages = [];
      }

      return next;
    });
  }

  function updateCategoryDraft(field, value) {
    setCategoryDraft((current) => {
      const next = { ...current, [field]: value };
      if (field === 'name' && (!current.slug || current.slug === slugifyProductName(current.name))) {
        next.slug = slugifyProductName(value);
      }
      return next;
    });
  }

  function resetCategoryDraft() {
    setCategoryDraft(createEmptyCategoryDraft());
  }

  function toggleRemoveExistingProductImage(path) {
    setProductDraft((current) => {
      const active = current.removeAccountImages.includes(path);
      return {
        ...current,
        removeAccountImages: active
          ? current.removeAccountImages.filter((item) => item !== path)
          : [...current.removeAccountImages, path],
      };
    });
  }

  function removePendingProductImage(imageId) {
    setProductDraft((current) => ({
      ...current,
      newImages: current.newImages.filter((item) => item.id !== imageId),
    }));
  }

  async function addProductImages(fileList) {
    const files = Array.from(fileList ?? []);
    if (!files.length) {
      return;
    }

    const activeExistingImages = productDraft.existingAccountImages.filter((path) => !productDraft.removeAccountImages.includes(path));
    const totalAfterUpload = activeExistingImages.length + productDraft.newImages.length + files.length;
    if (totalAfterUpload > 8) {
      setNotice({ type: 'error', text: 'Você pode manter no máximo 8 imagens WEBP por conta.' });
      return;
    }

    try {
      const preparedImages = [];

      for (const file of files) {
        const fileName = String(file.name ?? '').toLowerCase();
        const fileType = String(file.type ?? '').toLowerCase();

        if (!fileName.endsWith('.webp') || fileType !== 'image/webp') {
          throw new Error(`O arquivo ${file.name} não está em WEBP.`);
        }

        if (file.size > 5 * 1024 * 1024) {
          throw new Error(`O arquivo ${file.name} excede 5 MB.`);
        }

        preparedImages.push({
          id: `${file.name}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
          name: file.name,
          type: file.type,
          size: file.size,
          data: await readFileAsDataUrl(file),
        });
      }

      setProductDraft((current) => ({
        ...current,
        newImages: [...current.newImages, ...preparedImages],
      }));
      setNotice({ type: 'success', text: 'Imagens WEBP adicionadas ao rascunho do produto.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    }
  }

  async function saveProduct() {
    if (!permissionFlags.products) {
      setNotice({ type: 'error', text: 'Somente founder pode gerenciar produtos neste desktop.' });
      return;
    }

    const minimumQuantity = Math.max(1, Number(productDraft.minimumQuantity || 1));
    if (!Number.isFinite(minimumQuantity) || minimumQuantity < 1) {
      setNotice({ type: 'error', text: 'Defina uma compra mínima de pelo menos 1 unidade.' });
      return;
    }

    const stockValue = productDraft.stock === '' ? null : Number(productDraft.stock);
    if (stockValue !== null && Number.isFinite(stockValue) && stockValue > 0 && minimumQuantity > stockValue) {
      setNotice({ type: 'error', text: 'A compra mínima não pode ser maior que o estoque atual do produto.' });
      return;
    }

    setBusy('products-save');
    try {
      const body = {
        categoryId: Number(productDraft.categoryId || 0),
        name: productDraft.name,
        slug: productDraft.slug,
        unitPrice: Number(productDraft.unitPrice || 0),
        stock: productDraft.stock,
        minimumQuantity,
        serverLabel: productDraft.serverLabel,
        deliveryEta: productDraft.deliveryEta,
        deliveryMethod: productDraft.deliveryMethod,
        productType: productDraft.productType,
        productDescription: productDraft.productDescription,
        accountInfo: productDraft.accountInfo,
        description: productDraft.description,
        notes: productDraft.notes,
        isActive: productDraft.isActive,
        removeAccountImages: productDraft.removeAccountImages,
        newImages: productDraft.newImages.map((image) => ({
          name: image.name,
          type: image.type,
          data: image.data,
        })),
      };

      const path = productId ? `products/${productId}` : 'products';
      const response = await api(path, { method: 'POST', body });
      const reloaded = await loadProducts({ preserveDraft: false });
      const savedProduct = response.data?.product ?? safeList(reloaded?.products).find((product) => product.slug === body.slug) ?? null;

      if (savedProduct) {
        editProduct(savedProduct);
      } else {
        startNewProductDraft();
      }

      pushActivity(
        createActivityEntry({
          id: `product-save-${productId || 'new'}-${Date.now()}`,
          title: productId ? 'Produto atualizado' : 'Produto criado',
          text: `${session.admin?.username || 'Founder'} salvou ${body.name || 'um produto'} no desktop.`,
          badge: 'produtos',
          badgeTone: 'good',
          targetView: 'products',
          entityType: 'product',
          entityId: savedProduct?.id ?? productId ?? null,
          keywords: `${body.name} ${body.slug} ${body.productType}`,
        }),
      );
      setNotice({ type: 'success', text: response.message || 'Produto salvo com sucesso.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function saveCategory() {
    if (!permissionFlags.products) {
      setNotice({ type: 'error', text: 'Somente founder pode gerenciar categorias neste desktop.' });
      return;
    }

    const name = String(categoryDraft.name ?? '').trim();
    const slug = String(categoryDraft.slug ?? '').trim() || slugifyProductName(name);
    const sortOrder = Number(categoryDraft.sortOrder || 0);

    if (!name) {
      setNotice({ type: 'error', text: 'Informe o nome da categoria.' });
      return;
    }

    if (!slug) {
      setNotice({ type: 'error', text: 'Não foi possível gerar o slug da categoria.' });
      return;
    }

    setBusy('categories-save');
    try {
      const response = await api('categories', {
        method: 'POST',
        body: {
          name,
          slug,
          isActive: Boolean(categoryDraft.isActive),
          sortOrder: Number.isFinite(sortOrder) ? sortOrder : 0,
        },
      });

      const nextCategories = safeList(response.data?.categories);
      const createdCategory =
        response.data?.category ??
        nextCategories.find((category) => String(category.slug) === slug) ??
        null;

      if (nextCategories.length) {
        setProductsData((current) => ({ ...current, categories: nextCategories }));
      }

      if (createdCategory?.id) {
        setProductDraft((current) => ({
          ...current,
          categoryId: String(createdCategory.id),
        }));
      }

      resetCategoryDraft();
      pushActivity(
        createActivityEntry({
          id: `category-create-${Date.now()}`,
          title: 'Categoria criada',
          text: `${session.admin?.username || 'Founder'} criou ${name} no desktop.`,
          badge: 'catálogo',
          badgeTone: 'good',
          targetView: 'products',
          entityType: 'category',
          entityId: createdCategory?.id ?? null,
          keywords: `${name} ${slug}`,
        }),
      );
      setNotice({ type: 'success', text: response.message || 'Categoria criada com sucesso.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  function requestDeleteProduct() {
    if (!productId) {
      return;
    }

    setConfirmState({
      title: 'Remover produto',
      text: `Deseja remover ${productDraft.name || 'este produto'} do catálogo?`,
      confirmLabel: 'Remover produto',
      toneValue: 'danger',
      action: async () => {
        setBusy('products-delete');
        try {
          await api(`products/${productId}/delete`, { method: 'POST' });
          await loadProducts({ preserveDraft: false });
          pushActivity(
            createActivityEntry({
              id: `product-delete-${productId}-${Date.now()}`,
              title: 'Produto removido',
              text: `${session.admin?.username || 'Founder'} removeu ${productDraft.name || 'um produto'} do desktop.`,
              badge: 'produtos',
              badgeTone: 'warn',
              targetView: 'products',
              entityType: 'product',
              entityId: productId,
              keywords: `${productDraft.name} ${productDraft.slug}`,
            }),
          );
          startNewProductDraft();
          setNotice({ type: 'success', text: 'Produto removido.' });
        } catch (error) {
          setNotice({ type: 'error', text: error.message });
        } finally {
          setBusy('');
        }
      },
    });
  }

  async function loadLogs(filtersOverride = logFilters) {
    setBusy('logs');
    try {
      const requestFilters = { ...filtersOverride, scope: LOG_FILTERS.scope };
      const query = new URLSearchParams();
      Object.entries(requestFilters).forEach(([key, value]) => {
        if (value) query.set(key, value);
      });
      const response = await api(`logs${query.toString() ? `?${query.toString()}` : ''}`);
      setLogs((current) => {
        if (hasLoadedRef.current.logs) {
          pushActivities(buildLogAlerts(current, response.data, 'logs'));
        }
        return response.data;
      });
      hasLoadedRef.current.logs = true;
      touchUpdated('logs');
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  function resetLogsFilters() {
    const defaults = { ...LOG_FILTERS };
    setLogFilters(defaults);
    void loadLogs(defaults);
  }

  async function loadSecurityLogs(filtersOverride = securityFilters) {
    setBusy('security');
    try {
      const requestFilters = { ...filtersOverride, scope: SECURITY_FILTERS.scope };
      const query = new URLSearchParams();
      Object.entries(requestFilters).forEach(([key, value]) => {
        if (value) query.set(key, value);
      });
      const response = await api(`logs${query.toString() ? `?${query.toString()}` : ''}`);
      setSecurityLogs((current) => {
        if (hasLoadedRef.current.security) {
          pushActivities(buildLogAlerts(current, response.data, 'security'));
        }
        return response.data;
      });
      hasLoadedRef.current.security = true;
      touchUpdated('security');
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  function resetSecurityFilters() {
    const defaults = { ...SECURITY_FILTERS };
    setSecurityFilters(defaults);
    void loadSecurityLogs(defaults);
  }

  async function loadInvites(filtersOverride = inviteFilters) {
    setBusy('invites');
    try {
      const query = new URLSearchParams();
      Object.entries(filtersOverride).forEach(([key, value]) => {
        if (value) query.set(key, value);
      });
      const response = await api(`invites${query.toString() ? `?${query.toString()}` : ''}`);
      setInvites(response.data);
      hasLoadedRef.current.invites = true;
      touchUpdated('invites');
      return response.data;
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
      return null;
    } finally {
      setBusy('');
    }
  }

  function resetInviteFilters() {
    const defaults = { ...INVITE_FILTERS };
    setInviteFilters(defaults);
    void loadInvites(defaults);
  }

  async function generateInvites(quantity) {
    if (!permissionFlags.invites) {
      setNotice({ type: 'error', text: 'Apenas founder pode gerar convites para staff.' });
      return;
    }

    setBusy('invite-create');
    try {
      const response = await api('invites', {
        method: 'POST',
        body: { quantity },
      });
      const created = safeList(response.data?.created);
      const nextFilters = inviteFilters.status === 'used' ? { ...inviteFilters, status: 'all' } : inviteFilters;

      setInviteFilters(nextFilters);
      await loadInvites(nextFilters);
      pushActivity(
        createActivityEntry({
          id: `invites-create-${Date.now()}`,
          title: 'Convites staff gerados',
          text: `${session.admin?.username || 'Founder'} gerou ${created.length || quantity} convite(s) para novo staff.`,
          badge: 'convites',
          badgeTone: 'good',
          targetView: 'invites',
          keywords: `${session.admin?.username || ''} staff convites`,
        }),
      );
      setNotice({ type: 'success', text: response.message || 'Convites para staff gerados com sucesso.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function copyInviteMessage(invite) {
    await copyText(buildInviteShareText(invite), 'Convite para staff copiado.');
  }

  async function copyInviteLink(invite) {
    await copyText(invite?.registrationUrl, 'Link do convite copiado.');
  }

  function requestDeleteInvite(invite) {
    if (!invite?.id) {
      return;
    }

    const codeLabel = hideSensitiveInfo ? maskSensitiveValue(invite.inviteKey) : invite.inviteKey;
    setConfirmState({
      title: 'Remover convite',
      text: `Deseja remover o convite ${codeLabel || '#'}? Ele só cria conta staff e deixará de funcionar imediatamente.`,
      confirmLabel: 'Remover convite',
      toneValue: 'danger',
      action: async () => {
        setBusy('invite-delete');
        try {
          await api(`invites/${invite.id}/delete`, { method: 'POST' });
          await loadInvites();
          pushActivity(
            createActivityEntry({
              id: `invite-delete-${invite.id}-${Date.now()}`,
              title: 'Convite removido',
              text: `${session.admin?.username || 'Founder'} removeu um convite de staff do desktop.`,
              badge: 'convites',
              badgeTone: 'warn',
              targetView: 'invites',
              keywords: `${session.admin?.username || ''} ${invite.inviteKey || ''}`,
            }),
          );
          setNotice({ type: 'success', text: 'Convite removido.' });
        } catch (error) {
          setNotice({ type: 'error', text: error.message });
        } finally {
          setBusy('');
        }
      },
    });
  }

  async function saveSettings(event) {
    event.preventDefault();
    const apiBaseUrl = normalizeApiBaseUrl(login.apiBaseUrl);

    if (!apiBaseUrl) {
      setNotice({ type: 'error', text: 'Informe a URL da API.' });
      return;
    }

    const apiChanged = apiBaseUrl !== normalizeApiBaseUrl(config.apiBaseUrl);
    setBusy('settings');
    try {
      await saveConfigPatch({
        apiBaseUrl,
        discordClientId: login.discordClientId,
        presenceEnabled: login.presenceEnabled,
      });

      if (apiChanged && session.token) {
        await localLogout('API alterada. Faça login novamente para renovar o token.');
        return;
      }

      pushActivity(
        createActivityEntry({
          id: `settings-${Date.now()}`,
          title: 'Preferências locais atualizadas',
          text: 'As configurações do desktop foram salvas para a equipe.',
          badge: 'desktop',
          badgeTone: 'neutral',
          targetView: 'settings',
          keywords: `${apiBaseUrl} ${login.discordClientId}`,
        }),
      );
      setNotice({ type: 'success', text: 'Preferências salvas.' });
    } catch (error) {
      setNotice({ type: 'error', text: error.message });
    } finally {
      setBusy('');
    }
  }

  async function refreshCurrentView(options = {}) {
    const { preserveDrafts = true } = options;

    if (view === 'dashboard') {
      await loadDashboard();
      return;
    }
    if (view === 'orders') {
      await refreshOrdersView({ preserveDrafts });
      return;
    }
    if (view === 'users') {
      await refreshUsersView({ preserveDrafts });
      return;
    }
    if (view === 'products') {
      await loadProducts({ preserveDraft: preserveDrafts });
      return;
    }
    if (view === 'security') {
      await loadSecurityLogs();
      return;
    }
    if (view === 'logs') {
      await loadLogs();
      return;
    }
    if (view === 'invites') {
      await loadInvites();
      return;
    }
    await refreshPresenceStatus();
  }

  const filteredOrders = filterOrders(orders.orders, orderFilters);
  const filteredUsers = filterUsers(users, userFilters);
  const filteredProducts = applyProductFilters(productsData.products, productFilters);
  const orderStats = buildOrderStats(filteredOrders);
  const userStats = buildUserStats(filteredUsers);
  const productStats = createProductStats(filteredProducts);
  const inviteStats = buildInviteStats(invites.invites);
  const hideSensitiveInfo = uiPrefs.hideSensitiveInfo || !permissionFlags.viewSensitiveInfo;
  const canRevealSensitiveInfo = permissionFlags.viewSensitiveInfo;
  const siteBaseUrl = resolveSiteBaseUrl(config.apiBaseUrl);
  const allowedViewIds = new Set(navItems.map((item) => item.id));
  const dashboardTasks = buildDashboardTasks(dashboard).filter((task) => task.target === 'dashboard' || allowedViewIds.has(task.target));
  const logFeed = buildLogFeed(logs).map((entry) => ({ ...entry, targetView: 'logs' }));
  const securityFeed = buildLogFeed(securityLogs).map((entry) => ({ ...entry, targetView: 'security' }));
  const logHighlights = buildMarketHighlights(logs);
  const securityHighlights = buildSecurityHighlights(securityLogs);
  const latestActivity = activityFeed[0] ?? null;
  const selectedOrderOwner = orderId ? sharedOrderOwners[orderId] ?? orderOwners[orderId] ?? null : null;
  const selectedOrderSla = getOrderSla(orderDetail?.order);
  const orderTimeline = buildOrderTimeline(orderDetail, activityFeed, selectedOrderOwner);
  const searchableOrders = safeList(orders.orders).length ? orders.orders : safeList(dashboard?.recentOrders);
  const searchResults = buildSearchResults({
    query: searchQuery,
    orders: searchableOrders,
    users,
    products: productsData.products,
    logs: [...securityFeed, ...logFeed],
    activityFeed,
    statusLabels: orderDetail?.statusLabels ?? orders.statusLabels,
    hideSensitiveInfo,
  });
  const quickStatusOptions = ORDER_QUICK_STATUSES.map((status) => ({
    value: status,
    label: getStatusLabel(orderDetail?.statusLabels ?? orders.statusLabels, status),
  }));
  const userHistory = buildUserHistory(userDetail, activityFeed, [...securityFeed, ...logFeed]);
  const selectedOrderVisible = !orderId || filteredOrders.some((order) => order.id === orderId);
  const selectedUserVisible = !userId || filteredUsers.some((user) => user.id === userId);
  const viewMeta = getViewMeta(view);
  const viewUpdatedAt =
    view === 'orders'
      ? latestTimestamp([lastUpdated.orders, lastUpdated.orderDetail])
      : view === 'users'
        ? latestTimestamp([lastUpdated.users, lastUpdated.userDetail])
        : latestTimestamp([lastUpdated[view]]);
  const onboardingVisible = !uiPrefs.onboardingDismissed;
  const systemHealth = [
    {
      label: 'API',
      value: apiInfo?.service ? 'Validada' : config.apiBaseUrl ? 'Configurada' : 'Pendente',
      toneValue: apiInfo?.service ? 'good' : config.apiBaseUrl ? 'warn' : 'neutral',
      hint: apiInfo?.service
        ? `${apiInfo.service} respondeu nesta sessão.`
        : hideSensitiveInfo
          ? 'URL da API oculta para compartilhamento de tela.'
          : formatEndpoint(config.apiBaseUrl) || 'Defina a URL da API para validar a conexão.',
    },
    {
      label: 'Discord',
      value: presenceLabel(presenceStatus),
      toneValue: presenceStatus.toneValue,
      hint: presenceStatus.text,
    },
    {
      label: 'Sincronização',
      value: viewUpdatedAt ? fmtRelativeDate(viewUpdatedAt) : 'Sem sincronizar',
      toneValue: viewUpdatedAt ? 'good' : 'neutral',
      hint: viewUpdatedAt ? `Último refresh em ${fmtDate(viewUpdatedAt)}.` : 'Nenhuma seção foi sincronizada ainda.',
    },
    {
      label: 'Alertas',
      value: activityFeed.length ? `${activityFeed.length} recentes` : 'Estável',
      toneValue: latestActivity?.badgeTone || 'good',
      hint: latestActivity?.title || 'Nenhum alerta interno recente.',
    },
  ];
  const shortcutHints = SHORTCUT_HINTS.filter((item) => {
    const shortcutMap = {
      dashboard: 'dashboard',
      orders: 'orders',
      users: 'users',
      products: 'products',
      security: 'security',
      logs: 'logs',
      settings: 'settings',
      invites: 'invites',
    };
    const targetView = shortcutMap[item.id];
    return !targetView || navItems.some((navItem) => navItem.id === targetView);
  });
  const permissionCount = Array.isArray(session.permissions)
    ? session.permissions.length
    : session.permissions && typeof session.permissions === 'object'
      ? Object.keys(session.permissions).length
      : 0;
  const summaryCards = [
    { label: 'API', value: hideSensitiveInfo ? maskSensitiveValue(config.apiBaseUrl, 'endpoint') || 'Oculta' : formatEndpoint(config.apiBaseUrl) || '-', toneValue: 'neutral' },
    { label: 'Permissões', value: permissionCount ? `${permissionCount} ativas` : 'Padrão', toneValue: 'neutral' },
    { label: 'Discord', value: presenceLabel(presenceStatus), toneValue: presenceStatus.toneValue },
    { label: 'Última atualização', value: viewUpdatedAt ? fmtDate(viewUpdatedAt) : 'Ainda não sincronizada', toneValue: viewUpdatedAt ? 'good' : 'neutral' },
  ];
  const navBadges = {
    orders:
      orderStats.pending > 0
        ? String(orderStats.pending)
        : dashboard?.stats?.orders?.paidWaitingContact
          ? String(dashboard.stats.orders.paidWaitingContact)
          : '',
    users:
      userStats.banned > 0
        ? String(userStats.banned)
        : dashboard?.stats?.users?.banned
          ? String(dashboard.stats.users.banned)
          : '',
    products: productStats.active > 0 ? String(productStats.active) : productsData.products.length ? String(productsData.products.length) : '',
    security: securityLogs?.summary?.active_bans ? String(securityLogs.summary.active_bans) : securityLogs?.summary?.attempts_today ? String(securityLogs.summary.attempts_today) : '',
    logs: logs?.summary?.market_sales ? String(logs.summary.market_sales) : logs?.marketLogs?.length ? String(logs.marketLogs.length) : '',
    settings: updateState.status === 'downloaded' ? 'UP' : presenceStatus.toneValue === 'danger' ? '!' : '',
    invites: inviteStats.available > 0 ? String(inviteStats.available) : '',
  };

  function handleSearchSelect(result) {
    setSearchQuery('');
    setView(result.targetView || 'dashboard');

    if (result.targetView === 'orders' && result.entityId) {
      setOrderId(result.entityId);
    }

    if (result.targetView === 'users' && result.entityId) {
      setUserId(result.entityId);
    }

    if (result.targetView === 'products' && result.entityId) {
      setProductId(result.entityId);
      const selectedProduct = safeList(productsData.products).find((product) => product.id === result.entityId);
      if (selectedProduct) {
        editProduct(selectedProduct);
      }
    }
  }

  async function checkForAppUpdates() {
    try {
      const nextState = await bridge.checkForUpdates();
      setUpdateState((current) => ({
        ...current,
        ...(nextState ?? {}),
        currentVersion: nextState?.currentVersion || current.currentVersion || appInfo.version,
      }));

      if (nextState?.supported === false) {
        setNotice({ type: 'error', text: nextState.message || 'As atualizações automáticas não estão disponíveis neste ambiente.' });
        return;
      }

      if (nextState?.status === 'downloaded') {
        setNotice({ type: 'success', text: 'A nova versão já está pronta para instalar.' });
        return;
      }

      if (nextState?.status === 'not-available') {
        setNotice({ type: 'success', text: nextState.message || 'Este desktop já está na versão mais recente.' });
        return;
      }

      setNotice({ type: 'success', text: nextState?.message || 'Verificando atualizações do desktop.' });
    } catch (error) {
      setNotice({ type: 'error', text: error instanceof Error ? error.message : 'Não foi possível verificar atualizações.' });
    }
  }

  async function installDownloadedUpdate() {
    try {
      const nextState = await bridge.installUpdate();
      setUpdateState((current) => ({
        ...current,
        ...(nextState ?? {}),
        currentVersion: nextState?.currentVersion || current.currentVersion || appInfo.version,
      }));

      if (nextState?.supported === false) {
        setNotice({ type: 'error', text: nextState.message || 'As atualizações automáticas não estão disponíveis neste ambiente.' });
        return;
      }

      setNotice({ type: 'success', text: nextState?.message || 'Fechando o desktop para instalar a nova versão.' });
    } catch (error) {
      setNotice({ type: 'error', text: error instanceof Error ? error.message : 'Não foi possível iniciar a instalação da atualização.' });
    }
  }

  if (booting) {
    return (
      <div className="splash-screen">
        <div className="splash-card">
          <span className="eyebrow">Bolfer Desktop</span>
          <h1>Preparando o painel</h1>
          <p>Carregando configurações locais, status do Discord e sessão salva.</p>
        </div>
      </div>
    );
  }

  if (!session.admin) {
    return (
      <LoginScreen
        appInfo={appInfo}
        apiInfo={apiInfo}
        login={login}
        setLogin={setLogin}
        onSubmit={submitLogin}
        onVerifyApi={verifyApi}
        onOpenAdminPanel={() => bridge.openExternal(resolveAdminPanelUrl(login.apiBaseUrl))}
        presenceStatus={presenceStatus}
        onTestDiscord={testDiscordPresence}
        busy={busy}
        notice={notice}
        onDismissNotice={() => setNotice(null)}
      />
    );
  }

  return (
    <div className="app-shell">
      <Sidebar
        appInfo={appInfo}
        session={session}
        view={view}
        setView={setView}
        navBadges={navBadges}
        navItems={navItems}
        hideSensitiveInfo={hideSensitiveInfo}
      />

      <main className="content-area">
        <Topbar
          viewMeta={viewMeta}
          session={session}
          summaryCards={summaryCards}
          onRefresh={refreshCurrentView}
          onLogout={requestLogout}
          refreshing={['dashboard', 'orders', 'users', 'products', 'security', 'logs', 'invites'].includes(busy)}
          busy={busy}
          hideSensitiveInfo={hideSensitiveInfo}
        />

        <CommandBar
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          onSearchClear={() => setSearchQuery('')}
          autoRefreshEnabled={uiPrefs.autoRefreshEnabled}
          autoRefreshInterval={uiPrefs.autoRefreshInterval}
          autoRefreshOptions={AUTO_REFRESH_OPTIONS}
          onToggleAutoRefresh={() =>
            setUiPrefs((current) => ({
              ...current,
              autoRefreshEnabled: !current.autoRefreshEnabled,
            }))
          }
          onAutoRefreshIntervalChange={(value) =>
            setUiPrefs((current) => ({
              ...current,
              autoRefreshInterval: value,
            }))
          }
          activityCount={activityFeed.length}
          latestActivity={latestActivity}
          onClearActivity={clearActivityFeed}
          hideSensitiveInfo={hideSensitiveInfo}
          canRevealSensitiveInfo={canRevealSensitiveInfo}
          onToggleSensitiveInfo={toggleSensitiveInfo}
        />
        <SearchResults query={searchQuery} results={searchResults} onSelect={handleSearchSelect} onClose={() => setSearchQuery('')} />
        <PresenceStrip status={presenceStatus} onTest={testDiscordPresence} busy={busy === 'presence'} hideSensitiveInfo={hideSensitiveInfo} />
        {notice ? <Notice notice={notice} onClose={() => setNotice(null)} /> : null}

        {view === 'dashboard' ? (
          <DashboardView
            data={dashboard}
            statusLabels={orders.statusLabels}
            onRefresh={loadDashboard}
            onNavigate={setView}
            tasks={dashboardTasks}
            activityFeed={activityFeed}
            onClearActivity={clearActivityFeed}
            availableViews={navItems}
            healthItems={systemHealth}
            shortcutHints={shortcutHints}
            onboardingVisible={onboardingVisible}
            onDismissOnboarding={dismissOnboarding}
          />
        ) : null}
        {view === 'orders' ? (
          <OrdersView
            data={orders}
            detail={orderDetail}
            orderId={orderId}
            orderStatus={orderStatus}
            orderNote={orderNote}
            setOrderId={setOrderId}
            setOrderStatus={setOrderStatus}
            setOrderNote={setOrderNote}
            onRefresh={refreshOrdersView}
            onStatusSubmit={updateOrderStatus}
            onNoteSubmit={addOrderNote}
            filters={orderFilters}
            setFilters={setOrderFilters}
            filteredOrders={filteredOrders}
            stats={orderStats}
            busy={busy}
            selectedVisible={selectedOrderVisible}
            presets={filterPresets.orders}
            onSavePreset={() => saveFilterPreset('orders', orderFilters, 'pedidos')}
            onApplyPreset={(preset) => applyFilterPreset('orders', preset)}
            onDeletePreset={(preset) => removeFilterPreset('orders', preset, 'pedidos')}
            quickStatusOptions={quickStatusOptions}
            noteTemplates={ORDER_NOTE_TEMPLATES}
            onQuickStatus={submitOrderStatus}
            onQuickNote={(template) => submitOrderNote(template.note)}
            permissionFlags={permissionFlags}
            getOrderOwner={(id) => orderOwners[id] ?? null}
            currentOwner={selectedOrderOwner}
            currentSla={selectedOrderSla}
            onAssignOwner={assignOrderToCurrentUser}
            onReleaseOwner={releaseOrderOwner}
            onCopySummary={copyOrderSummary}
            timeline={orderTimeline}
            workspace={orderWorkspace}
            orderAuditReason={orderAuditReason}
            setOrderAuditReason={setOrderAuditReason}
            hideSensitiveInfo={hideSensitiveInfo}
          />
        ) : null}
        {view === 'users' ? (
          <UsersView
            users={users}
            detail={userDetail}
            userId={userId}
            coinForm={coinForm}
            setUserId={setUserId}
            setCoinForm={setCoinForm}
            onRefresh={refreshUsersView}
            onBan={() => requestBan()}
            onUnban={requestUnban}
            onCoins={adjustCoins}
            busy={busy}
            filters={userFilters}
            setFilters={setUserFilters}
            filteredUsers={filteredUsers}
            stats={userStats}
            selectedVisible={selectedUserVisible}
            presets={filterPresets.users}
            onSavePreset={() => saveFilterPreset('users', userFilters, 'usuários')}
            onApplyPreset={(preset) => applyFilterPreset('users', preset)}
            onDeletePreset={(preset) => removeFilterPreset('users', preset, 'usuários')}
            coinTemplates={COIN_TEMPLATES}
            banReasons={USER_BAN_REASONS}
            onQuickCoins={requestQuickCoinAdjustment}
            onQuickBan={requestBan}
            userHistory={userHistory}
            permissionFlags={permissionFlags}
            onCopySummary={copyUserSummary}
            moderationReason={moderationReason}
            setModerationReason={setModerationReason}
            hideSensitiveInfo={hideSensitiveInfo}
          />
        ) : null}
        {view === 'products' ? (
          <ProductsWorkspaceView
            data={productsData}
            filters={productFilters}
            setFilters={setProductFilters}
            filteredProducts={filteredProducts}
            stats={productStats}
            productId={productId}
            productDraft={productDraft}
            categoryDraft={categoryDraft}
            busy={busy}
            siteBaseUrl={siteBaseUrl}
            onRefresh={() => loadProducts({ preserveDraft: true })}
            onReset={() => setProductFilters({ ...PRODUCT_FILTERS })}
            onNewProduct={startNewProductDraft}
            onEditProduct={editProduct}
            onDraftChange={updateProductDraft}
            onCategoryDraftChange={updateCategoryDraft}
            onResetCategoryDraft={resetCategoryDraft}
            onSaveCategory={saveCategory}
            onSaveProduct={saveProduct}
            onDeleteProduct={requestDeleteProduct}
            onPickImages={addProductImages}
            onRemovePendingImage={removePendingProductImage}
            onToggleRemoveExistingImage={toggleRemoveExistingProductImage}
            presets={filterPresets.products}
            onSavePreset={() => saveFilterPreset('products', productFilters, 'produtos')}
            onApplyPreset={(preset) => applyFilterPreset('products', preset)}
            onDeletePreset={(preset) => removeFilterPreset('products', preset, 'produtos')}
          />
        ) : null}
        {view === 'security' ? (
          <SecurityWorkspaceView
            data={securityLogs}
            filters={securityFilters}
            setFilters={setSecurityFilters}
            onRefresh={() => loadSecurityLogs()}
            onReset={resetSecurityFilters}
            highlights={securityHighlights}
            feed={securityFeed}
            presets={filterPresets.security}
            onSavePreset={() => saveFilterPreset('security', securityFilters, 'banimentos')}
            onApplyPreset={(preset) => applyFilterPreset('security', preset)}
            onDeletePreset={(preset) => removeFilterPreset('security', preset, 'banimentos')}
          />
        ) : null}
        {view === 'logs' ? (
          <LogsWorkspaceView
            data={logs}
            filters={logFilters}
            setFilters={setLogFilters}
            onRefresh={() => loadLogs()}
            onReset={resetLogsFilters}
            highlights={logHighlights}
            feed={logFeed}
            presets={filterPresets.logs}
            onSavePreset={() => saveFilterPreset('logs', logFilters, 'logs')}
            onApplyPreset={(preset) => applyFilterPreset('logs', preset)}
            onDeletePreset={(preset) => removeFilterPreset('logs', preset, 'logs')}
          />
        ) : null}
        {view === 'settings' ? (
          <SettingsView
            appInfo={appInfo}
            login={login}
            setLogin={setLogin}
            saveSettings={saveSettings}
            verifyApi={verifyApi}
            openDiscord={() => bridge.openExternal('https://discord.com/developers/applications')}
            busy={busy}
            apiInfo={apiInfo}
            presenceStatus={presenceStatus}
            config={config}
            uiPrefs={uiPrefs}
            toggleCompactMode={toggleCompactMode}
            toggleSoundAlerts={toggleSoundAlerts}
            toggleDesktopNotifications={toggleDesktopNotifications}
            restoreOnboarding={restoreOnboarding}
            shortcutHints={shortcutHints}
            healthItems={systemHealth}
            updateState={updateState}
            onCheckUpdates={checkForAppUpdates}
            onInstallUpdate={installDownloadedUpdate}
            hideSensitiveInfo={hideSensitiveInfo}
            canRevealSensitiveInfo={canRevealSensitiveInfo}
            onToggleSensitiveInfo={toggleSensitiveInfo}
            permissionFlags={permissionFlags}
          />
        ) : null}
        {view === 'invites' ? (
          <InvitesWorkspaceView
            data={invites}
            filters={inviteFilters}
            setFilters={setInviteFilters}
            onRefresh={() => loadInvites()}
            onReset={resetInviteFilters}
            onGenerate={generateInvites}
            onCopyInvite={copyInviteMessage}
            onCopyLink={copyInviteLink}
            onDeleteInvite={requestDeleteInvite}
            busy={busy}
            hideSensitiveInfo={hideSensitiveInfo}
          />
        ) : null}
      </main>

      <Modal
        open={Boolean(confirmState)}
        title={confirmState?.title}
        text={confirmState?.text}
        confirmLabel={confirmState?.confirmLabel}
        toneValue={confirmState?.toneValue}
        busy={confirmBusy}
        onConfirm={confirmAction}
        onClose={() => {
          if (!confirmBusy) {
            setConfirmState(null);
          }
        }}
      />
    </div>
  );
}

export default App;
