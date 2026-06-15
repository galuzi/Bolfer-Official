const buildEnv = typeof import.meta !== 'undefined' && import.meta.env ? import.meta.env : {};
const runtimeEnv = typeof process !== 'undefined' && process.env ? process.env : {};

export const DISCORD_APP_ID = String(
  buildEnv.VITE_DISCORD_APP_ID ?? runtimeEnv.DISCORD_APP_ID ?? '',
).trim();

export const DISCORD_PUBLIC_KEY = String(
  buildEnv.VITE_DISCORD_PUBLIC_KEY ?? runtimeEnv.DISCORD_PUBLIC_KEY ?? '',
).trim();
