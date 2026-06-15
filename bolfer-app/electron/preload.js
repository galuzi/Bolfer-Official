import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('bolferDesktop', {
  requestApi: (request) => ipcRenderer.invoke('api:request', request),
  getConfig: () => ipcRenderer.invoke('config:get'),
  saveConfig: (patch) => ipcRenderer.invoke('config:set', patch),
  setPresence: (activity) => ipcRenderer.invoke('presence:set', activity),
  clearPresence: () => ipcRenderer.invoke('presence:clear'),
  getPresenceStatus: () => ipcRenderer.invoke('presence:status'),
  getAppInfo: () => ipcRenderer.invoke('app:info'),
  getUpdateState: () => ipcRenderer.invoke('app:update:getState'),
  checkForUpdates: () => ipcRenderer.invoke('app:update:check'),
  installUpdate: () => ipcRenderer.invoke('app:update:install'),
  onUpdateState: (listener) => {
    if (typeof listener !== 'function') {
      return () => {};
    }

    const handler = (_, payload) => listener(payload);
    ipcRenderer.on('app:update-state', handler);
    return () => {
      ipcRenderer.removeListener('app:update-state', handler);
    };
  },
  openExternal: (url) => ipcRenderer.invoke('shell:openExternal', url),
});
