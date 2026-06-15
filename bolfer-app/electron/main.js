import { Menu, app, BrowserWindow, ipcMain, shell } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { DISCORD_APP_ID } from '../config/discordApp.js';

const require = createRequire(import.meta.url);
const DiscordRPC = require('discord-rpc');
const { autoUpdater } = require('electron-updater');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const APP_ICON_PATH = path.join(__dirname, '..', 'img', 'logo.ico');
const LOCAL_API_BASE_URL = 'http://localhost:8000/api/desktop';
function normalizeApiBaseUrl(value) {
  return String(value ?? '').trim().replace(/\/+$/, '');
}

const DEFAULT_API_BASE_URL =
  normalizeApiBaseUrl(process.env.BOLFER_DESKTOP_API_BASE_URL || process.env.VITE_API_BASE_URL) ||
  'https://example.com/api/desktop';
const DEFAULT_CONFIG = {
  apiBaseUrl: DEFAULT_API_BASE_URL,
  discordClientId: DISCORD_APP_ID,
  presenceEnabled: true,
  token: '',
  admin: null,
};

function normalizeConfig(input = {}) {
  const normalizedApiBaseUrl = String(input.apiBaseUrl ?? '').trim() || DEFAULT_API_BASE_URL;

  return {
    apiBaseUrl: normalizedApiBaseUrl,
    discordClientId: String(input.discordClientId ?? '').trim(),
    presenceEnabled: input.presenceEnabled !== false,
    token: String(input.token ?? '').trim(),
    admin: input.admin && typeof input.admin === 'object' ? input.admin : null,
  };
}

function sanitizeActivity(activity) {
  if (!activity || typeof activity !== 'object') {
    return null;
  }

  const details = String(activity.details ?? 'Bolfer Desktop').trim().slice(0, 128);
  const state = String(activity.state ?? '').trim().slice(0, 128);
  const payload = { details };

  if (state) {
    payload.state = state;
  }

  if (activity.startTimestamp) {
    payload.startTimestamp = new Date(activity.startTimestamp);
  }

  return payload;
}

function formatPresenceError(error, fallback) {
  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

async function performApiRequest({
  baseUrl,
  path = '',
  method = 'GET',
  token,
  body,
}) {
  const normalizedBaseUrl = normalizeApiBaseUrl(baseUrl);
  const normalizedPath = String(path).replace(/^\/+/, '');
  const url = normalizedPath ? `${normalizedBaseUrl}/${normalizedPath}` : normalizedBaseUrl;

  const headers = {
    Accept: 'application/json',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  let response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (error) {
    return {
      ok: false,
      error: {
        message:
          `Não foi possível conectar com a API. Em produção, use ${DEFAULT_API_BASE_URL}. Se você estiver rodando localmente, use ${LOCAL_API_BASE_URL} e confirme que o servidor do site está ativo.`,
        cause: formatPresenceError(error, 'Falha de rede no processo principal.'),
      },
    };
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = {
      ok: false,
      message: 'A API retornou uma resposta inválida.',
    };
  }

  if (!response.ok) {
    return {
      ok: false,
      error: {
        message: payload?.message || `Erro ${response.status}`,
        status: response.status,
        payload,
      },
    };
  }

  if (payload?.ok === false) {
    return {
      ok: false,
      error: {
        message: payload?.message || 'A API retornou uma falha.',
        status: response.status,
        payload,
      },
    };
  }

  return {
    ok: true,
    payload,
  };
}

class ConfigStore {
  constructor(filePath) {
    this.filePath = filePath;
  }

  read() {
    try {
      if (!fs.existsSync(this.filePath)) {
        return { ...DEFAULT_CONFIG };
      }

      const raw = fs.readFileSync(this.filePath, 'utf8');
      const parsed = JSON.parse(raw);
      return normalizeConfig({ ...DEFAULT_CONFIG, ...parsed });
    } catch {
      return { ...DEFAULT_CONFIG };
    }
  }

  write(nextValue) {
    const normalized = normalizeConfig({ ...DEFAULT_CONFIG, ...nextValue });
    fs.mkdirSync(path.dirname(this.filePath), { recursive: true });
    fs.writeFileSync(this.filePath, JSON.stringify(normalized, null, 2), 'utf8');
    return normalized;
  }

  merge(patch) {
    return this.write({ ...this.read(), ...patch });
  }
}

class DiscordPresenceManager {
  constructor() {
    this.clientId = '';
    this.client = null;
    this.ready = false;
    this.lastActivity = null;
    this.lastError = null;
    this.connectPromise = null;
    this.readyWaiters = [];
  }

  async setClientId(clientId) {
    const normalized = String(clientId ?? '').trim();
    if (normalized === this.clientId && this.client) {
      if (this.ready) {
        return this.getStatus();
      }

      if (this.connectPromise) {
        return this.connectPromise;
      }

      await this.disconnect();
    } else {
      await this.disconnect();
    }

    this.clientId = normalized;

    if (!normalized) {
      return this.getStatus();
    }

    DiscordRPC.register(normalized);
    const client = new DiscordRPC.Client({ transport: 'ipc' });
    this.client = client;
    this.ready = false;
    this.lastError = null;

    client.on('ready', async () => {
      if (this.client !== client) {
        return;
      }

      this.ready = true;
      this.lastError = null;
      this.resolveReadyWaiters(true);

      if (this.lastActivity) {
        try {
          await client.setActivity(this.lastActivity);
        } catch (error) {
          this.lastError = formatPresenceError(error, 'Falha ao definir a atividade.');
        }
      }
    });
    client.on('disconnected', () => {
      if (this.client !== client) {
        return;
      }

      this.ready = false;
      this.resolveReadyWaiters(false);
      this.lastError = this.lastError || 'A conexão com o Discord foi encerrada.';
    });
    client.on('error', (error) => {
      if (this.client !== client) {
        return;
      }

      this.lastError = formatPresenceError(error, 'Discord indisponível.');
      this.resolveReadyWaiters(false);
    });

    const connectPromise = (async () => {
      try {
        await client.login({ clientId: normalized });
        const ready = await this.waitUntilReady();

        if (!ready) {
          this.lastError = this.lastError || 'Discord não respondeu a tempo.';
        }
      } catch (error) {
        this.lastError = formatPresenceError(error, 'Discord indisponível.');
        this.resolveReadyWaiters(false);
      }

      return this.getStatus();
    })();

    this.connectPromise = connectPromise;
    return connectPromise.finally(() => {
      if (this.connectPromise === connectPromise) {
        this.connectPromise = null;
      }
    });
  }

  async setActivity(activity) {
    this.lastActivity = sanitizeActivity(activity);

    if (!this.clientId) {
      return {
        ok: false,
        reason: 'missing_client_id',
        message: 'Informe o Discord Client ID para ativar o Rich Presence.',
        ...this.getStatus(),
      };
    }

    const connectionStatus = await this.setClientId(this.clientId);
    if (!connectionStatus.ready) {
      return {
        ok: false,
        reason: 'not_ready',
        message: this.lastError || 'Ainda aguardando o Discord aceitar a conexão.',
        ...this.getStatus(),
      };
    }

    if (!this.lastActivity) {
      return {
        ok: false,
        reason: 'missing_activity',
        message: 'Nenhuma atividade foi enviada para o Discord.',
        ...this.getStatus(),
      };
    }

    return this.applyActivity();
  }

  async applyActivity() {
    if (!this.client || !this.ready || !this.lastActivity) {
      return {
        ok: false,
        reason: 'not_ready',
        message: this.lastError || 'Ainda aguardando o Discord aceitar a conexão.',
        ...this.getStatus(),
      };
    }

    try {
      await this.client.setActivity(this.lastActivity);
      this.lastError = null;
      return {
        ok: true,
        message: 'Discord conectado e atividade enviada.',
        ...this.getStatus(),
      };
    } catch (error) {
      this.lastError = formatPresenceError(error, 'Não foi possível atualizar a atividade.');
      return {
        ok: false,
        reason: 'set_failed',
        message: this.lastError,
        ...this.getStatus(),
      };
    }
  }

  async clearActivity() {
    this.lastActivity = null;

    if (!this.client || !this.ready) {
      return {
        ok: true,
        message: 'Nenhuma atividade ativa no Discord.',
        ...this.getStatus(),
      };
    }

    try {
      await this.client.clearActivity();
      this.lastError = null;
      return {
        ok: true,
        message: 'Atividade removida do Discord.',
        ...this.getStatus(),
      };
    } catch (error) {
      this.lastError = formatPresenceError(error, 'Não foi possível limpar a atividade.');
      return {
        ok: false,
        reason: 'clear_failed',
        message: this.lastError,
        ...this.getStatus(),
      };
    }
  }

  async disconnect() {
    if (!this.client) {
      this.ready = false;
      this.resolveReadyWaiters(false);
      this.connectPromise = null;
      return;
    }

    try {
      if (this.ready) {
        await this.client.clearActivity();
      }
    } catch {
      // Intencional: o app não deve falhar se o Discord não responder.
    }

    try {
      this.client.destroy();
    } catch {
      // Intencional.
    }

    this.client = null;
    this.ready = false;
    this.lastError = null;
    this.connectPromise = null;
    this.resolveReadyWaiters(false);
  }

  waitUntilReady(timeoutMs = 6000) {
    if (this.ready) {
      return Promise.resolve(true);
    }

    if (!this.client) {
      return Promise.resolve(false);
    }

    return new Promise((resolve) => {
      const waiter = {
        resolve: (value) => {
          clearTimeout(timeoutId);
          resolve(value);
        },
      };

      const timeoutId = setTimeout(() => {
        this.readyWaiters = this.readyWaiters.filter((item) => item !== waiter);
        resolve(false);
      }, timeoutMs);

      this.readyWaiters.push(waiter);
    });
  }

  resolveReadyWaiters(value) {
    const waiters = [...this.readyWaiters];
    this.readyWaiters = [];
    waiters.forEach((waiter) => waiter.resolve(value));
  }

  getStatus() {
    return {
      clientId: this.clientId,
      connected: Boolean(this.client),
      ready: this.ready,
      hasActivity: Boolean(this.lastActivity),
      lastError: this.lastError,
    };
  }
}

const configStore = new ConfigStore(path.join(app.getPath('userData'), 'bolfer-desktop.json'));
const presenceManager = new DiscordPresenceManager();
let mainWindowRef = null;
let updateListenersAttached = false;

function buildInitialUpdateState() {
  const isPortable = Boolean(process.env.PORTABLE_EXECUTABLE_FILE);
  const supported = process.platform === 'win32' && app.isPackaged && !isPortable;

  return {
    supported,
    status: supported ? 'idle' : 'unsupported',
    message: supported
      ? 'Pronto para verificar atualizações.'
      : isPortable
        ? 'As atualizações automáticas funcionam apenas na versão instalada via Setup.'
        : 'As atualizações automáticas ficam disponíveis apenas na versão instalada do desktop.',
    currentVersion: app.getVersion(),
    availableVersion: '',
    downloadedVersion: '',
    progress: 0,
    lastCheckedAt: null,
    lastError: null,
  };
}

let updateState = buildInitialUpdateState();

function broadcastUpdateState() {
  const windows = BrowserWindow.getAllWindows();
  windows.forEach((window) => {
    if (!window.isDestroyed()) {
      window.webContents.send('app:update-state', updateState);
    }
  });
}

function setUpdateState(patch = {}) {
  updateState = {
    ...updateState,
    ...patch,
    currentVersion: patch.currentVersion ?? updateState.currentVersion ?? app.getVersion(),
  };
  broadcastUpdateState();
  return updateState;
}

function supportsAutoUpdate() {
  return Boolean(updateState.supported);
}

async function checkForAppUpdates() {
  if (!supportsAutoUpdate()) {
    return updateState;
  }

  setUpdateState({
    status: 'checking',
    message: 'Verificando atualizações do app...',
    lastCheckedAt: new Date().toISOString(),
    lastError: null,
  });

  try {
    await autoUpdater.checkForUpdates();
  } catch (error) {
    setUpdateState({
      status: 'error',
      message: 'Não foi possível verificar atualizações.',
      lastError: formatPresenceError(error, 'Falha ao consultar o servidor de atualização.'),
    });
  }

  return updateState;
}

function initializeAutoUpdater() {
  updateState = buildInitialUpdateState();
  broadcastUpdateState();

  if (!supportsAutoUpdate() || updateListenersAttached) {
    return;
  }

  updateListenersAttached = true;
  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  autoUpdater.on('checking-for-update', () => {
    setUpdateState({
      status: 'checking',
      message: 'Verificando atualizações do app...',
      lastCheckedAt: new Date().toISOString(),
      lastError: null,
    });
  });

  autoUpdater.on('update-available', (info = {}) => {
    setUpdateState({
      status: 'available',
      message: `Nova versão encontrada: ${info.version || 'disponível'}.`,
      availableVersion: info.version || '',
      progress: 0,
      lastError: null,
    });
  });

  autoUpdater.on('update-not-available', (info = {}) => {
    setUpdateState({
      status: 'not-available',
      message: info.version ? `Este desktop já está na versão ${info.version}.` : 'Este desktop já está atualizado.',
      availableVersion: info.version || '',
      downloadedVersion: '',
      progress: 100,
      lastError: null,
    });
  });

  autoUpdater.on('download-progress', (progress = {}) => {
    setUpdateState({
      status: 'downloading',
      message: 'Baixando atualização em segundo plano...',
      progress: Math.max(0, Math.min(100, Math.round(Number(progress.percent ?? 0)))),
      lastError: null,
    });
  });

  autoUpdater.on('update-downloaded', (info = {}) => {
    setUpdateState({
      status: 'downloaded',
      message: `Atualização ${info.version || 'nova'} pronta para instalar.`,
      availableVersion: info.version || updateState.availableVersion || '',
      downloadedVersion: info.version || updateState.availableVersion || '',
      progress: 100,
      lastError: null,
    });
  });

  autoUpdater.on('error', (error) => {
    setUpdateState({
      status: 'error',
      message: 'Falha na atualização automática.',
      lastError: formatPresenceError(error, 'Falha ao baixar ou validar a atualização.'),
    });
  });
}

async function installDownloadedUpdate() {
  if (!supportsAutoUpdate()) {
    return updateState;
  }

  if (updateState.status !== 'downloaded') {
    return setUpdateState({
      message: 'Ainda não existe atualização pronta para instalar.',
    });
  }

  setUpdateState({
    status: 'installing',
    message: 'Fechando o app para instalar a nova versão...',
  });

  setImmediate(() => {
    autoUpdater.quitAndInstall();
  });

  return updateState;
}

async function syncPresenceFromConfig(config) {
  if (!config.presenceEnabled || !config.discordClientId) {
    await presenceManager.clearActivity();
    await presenceManager.disconnect();
    presenceManager.clientId = String(config.discordClientId ?? '').trim();
    return {
      enabled: config.presenceEnabled,
      configuredClientId: config.discordClientId,
      ...presenceManager.getStatus(),
    };
  }

  const status = await presenceManager.setClientId(config.discordClientId);
  return {
    enabled: config.presenceEnabled,
    configuredClientId: config.discordClientId,
    ...status,
  };
}

function createMainWindow() {
  const mainWindow = new BrowserWindow({
    width: 1460,
    height: 940,
    minWidth: 1180,
    minHeight: 760,
    show: false,
    autoHideMenuBar: true,
    icon: APP_ICON_PATH,
    backgroundColor: '#09110b',
    titleBarStyle: 'hiddenInset',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
  });

  mainWindow.once('ready-to-show', () => {
    mainWindow.setMenuBarVisibility(false);
    mainWindow.show();
  });
  mainWindow.on('closed', () => {
    if (mainWindowRef === mainWindow) {
      mainWindowRef = null;
    }
  });

  mainWindowRef = mainWindow;

  if (process.env.VITE_DEV_SERVER_URL) {
    mainWindow.loadURL(process.env.VITE_DEV_SERVER_URL);
    return mainWindow;
  }

  mainWindow.loadFile(path.join(__dirname, '..', 'dist', 'index.html'));
  return mainWindow;
}

app.whenReady().then(async () => {
  Menu.setApplicationMenu(null);
  await syncPresenceFromConfig(configStore.read());
  createMainWindow();
  initializeAutoUpdater();
  if (supportsAutoUpdate()) {
    void checkForAppUpdates();
  }

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createMainWindow();
    }
  });
});

ipcMain.handle('config:get', () => configStore.read());

ipcMain.handle('api:request', async (_, request = {}) => performApiRequest(request));

ipcMain.handle('config:set', async (_, patch = {}) => {
  const nextConfig = configStore.merge(patch);
  await syncPresenceFromConfig(nextConfig);
  return nextConfig;
});

ipcMain.handle('presence:set', async (_, activity = {}) => {
  const config = configStore.read();
  if (!config.presenceEnabled || !config.discordClientId) {
    const clearStatus = await presenceManager.clearActivity();
    return {
      ok: false,
      enabled: false,
      reason: config.presenceEnabled ? 'missing_client_id' : 'disabled',
      message: config.presenceEnabled ? 'Informe o Discord Client ID para ativar o Rich Presence.' : 'Rich Presence desativado nas preferências.',
      configuredClientId: config.discordClientId,
      ...clearStatus,
    };
  }

  const result = await presenceManager.setActivity(activity);
  return {
    enabled: true,
    configuredClientId: config.discordClientId,
    ...result,
  };
});

ipcMain.handle('presence:clear', async () => presenceManager.clearActivity());

ipcMain.handle('presence:status', async () => {
  const config = configStore.read();
  return {
    enabled: config.presenceEnabled,
    configuredClientId: config.discordClientId,
    ...presenceManager.getStatus(),
  };
});

ipcMain.handle('app:info', () => ({
  version: app.getVersion(),
  platform: process.platform,
}));

ipcMain.handle('app:update:getState', () => updateState);

ipcMain.handle('app:update:check', async () => checkForAppUpdates());

ipcMain.handle('app:update:install', async () => installDownloadedUpdate());

ipcMain.handle('shell:openExternal', async (_, url) => {
  if (typeof url === 'string' && /^https?:\/\//i.test(url)) {
    await shell.openExternal(url);
  }

  return true;
});

app.on('window-all-closed', async () => {
  await presenceManager.clearActivity();

  if (process.platform !== 'darwin') {
    app.quit();
  }
});
