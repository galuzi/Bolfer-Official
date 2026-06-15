const fs = require("node:fs");
const dotenv = require("dotenv");
const bundledEnv = require("../generated/bundledEnv");
const { resolveRuntimePath } = require("../utils/runtimePaths");

for (const [key, value] of Object.entries(bundledEnv)) {
  if (process.env[key] === undefined) {
    process.env[key] = String(value);
  }
}

const envPathCandidates = [
  resolveRuntimePath(".env"),
  resolveRuntimePath("..", ".env"),
];

const envPath = envPathCandidates.find((candidate) => fs.existsSync(candidate));

if (envPath) {
  dotenv.config({ path: envPath, quiet: true, override: true });
}

function hasPlaceholder(value) {
  if (!value) {
    return false;
  }

  return /SEU_SERVER_ID|ID_CANAL|SEU_CANAL|YOUR_|EXAMPLE/i.test(value);
}

function normalizeSnowflake(value) {
  if (!value) {
    return null;
  }

  const trimmed = value.trim();

  if (!/^\d{15,25}$/.test(trimmed)) {
    return null;
  }

  return trimmed;
}

function normalizeHttpUrl(value) {
  if (!value || hasPlaceholder(value)) {
    return null;
  }

  try {
    const parsed = new URL(value);
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
      return null;
    }

    return parsed.toString();
  } catch {
    return null;
  }
}

function parseDiscordChannelUrl(value) {
  const safeValue = normalizeHttpUrl(value);

  if (!safeValue) {
    return null;
  }

  const match = safeValue.match(
    /^https?:\/\/(?:canary\.)?discord\.com\/channels\/(\d+)\/(\d+)\/?$/i,
  );

  if (!match) {
    return null;
  }

  return {
    guildId: match[1],
    channelId: match[2],
    url: safeValue,
  };
}

function pickFirst(...values) {
  return values.find((value) => typeof value === "string" && value.trim()) || null;
}

function pickFirstId(...values) {
  for (const value of values) {
    const normalized = normalizeSnowflake(value);
    if (normalized) {
      return normalized;
    }
  }

  return null;
}

const welcomeRef = parseDiscordChannelUrl(process.env.BEM_VINDO_URL);
const rulesRef = parseDiscordChannelUrl(process.env.REGRAS_URL);
const rolesRef = parseDiscordChannelUrl(process.env.CARGOS_URL);
const supportRef = parseDiscordChannelUrl(process.env.SUPORTE_URL);
const announcementsRef = parseDiscordChannelUrl(process.env.ANUNCIOS_URL);

const discoveredGuildIds = [
  welcomeRef?.guildId,
  rulesRef?.guildId,
  rolesRef?.guildId,
  supportRef?.guildId,
  announcementsRef?.guildId,
].filter(Boolean);

const explicitGuildId = normalizeSnowflake(process.env.GUILD_ID);

if (
  explicitGuildId &&
  discoveredGuildIds.length &&
  !discoveredGuildIds.includes(explicitGuildId)
) {
  throw new Error(
    `GUILD_ID (${explicitGuildId}) difere do servidor encontrado nos links configurados (${discoveredGuildIds[0]}). Corrija o .env antes de iniciar o bot.`,
  );
}

const guildId = pickFirstId(
  explicitGuildId,
  welcomeRef?.guildId,
  rulesRef?.guildId,
  rolesRef?.guildId,
  supportRef?.guildId,
  announcementsRef?.guildId,
);

const branding = {
  serverName: pickFirst(process.env.SERVER_NAME, "Bolfer"),
  primaryColor: pickFirst(process.env.PRIMARY_COLOR, "#A63D2A"),
  footerText: pickFirst(
    process.env.FOOTER_TEXT,
    "Paineis oficiais do servidor",
  ),
  siteUrl: normalizeHttpUrl(process.env.SITE_URL),
  thumbnailUrl: normalizeHttpUrl(process.env.THUMBNAIL_URL),
  welcomeBannerUrl: normalizeHttpUrl(process.env.WELCOME_BANNER_URL),
  rulesBannerUrl: normalizeHttpUrl(process.env.RULES_BANNER_URL),
  rolesBannerUrl: normalizeHttpUrl(process.env.ROLES_BANNER_URL),
  announcementBannerUrl: normalizeHttpUrl(process.env.ANNOUNCEMENT_BANNER_URL),
  eventBannerUrl: normalizeHttpUrl(process.env.EVENT_BANNER_URL),
};

const channels = {
  welcome: {
    channelId: pickFirstId(process.env.CHANNEL_ID, welcomeRef?.channelId),
    url: welcomeRef?.url || null,
  },
  rules: {
    channelId: rulesRef?.channelId || null,
    url: rulesRef?.url || null,
  },
  roles: {
    channelId: rolesRef?.channelId || null,
    url: rolesRef?.url || null,
  },
  support: {
    channelId: supportRef?.channelId || null,
    url: supportRef?.url || null,
  },
  announcements: {
    channelId: pickFirstId(
      process.env.ANUNCIOS_CHANNEL_ID,
      announcementsRef?.channelId,
    ),
    url: announcementsRef?.url || null,
  },
};

const links = {
  welcome: channels.welcome.url,
  rules: channels.rules.url,
  roles: channels.roles.url,
  support: channels.support.url,
  announcements: channels.announcements.url,
  site: branding.siteUrl,
};

module.exports = {
  token: process.env.DISCORD_TOKEN,
  guildId,
  branding,
  channels,
  links,
  roleIds: {
    primordial: pickFirstId(process.env.PRIMORDIAL_ROLE_ID),
  },
};
