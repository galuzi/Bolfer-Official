const { MessageFlags } = require("discord.js");
const config = require("../config");
const {
  buildRolesPanel,
  buildRulesPanel,
  buildSupportPanel,
  buildWelcomePanel,
} = require("../embeds/panels");
const {
  getManagedMessage,
  setManagedMessage,
} = require("../storage/messageStore");
const { fetchTextChannel } = require("../utils/discord");

const panels = {
  welcome: {
    key: "welcome",
    label: "Boas-vindas",
    channelId: config.channels.welcome.channelId,
    build: buildWelcomePanel,
  },
  rules: {
    key: "rules",
    label: "Regras",
    channelId: config.channels.rules.channelId,
    build: buildRulesPanel,
  },
  roles: {
    key: "roles",
    label: "Cargos",
    channelId: config.channels.roles.channelId,
    build: buildRolesPanel,
  },
  support: {
    key: "support",
    label: "Suporte",
    channelId: config.channels.support.channelId,
    build: buildSupportPanel,
  },
};

function isComponentsV2Payload(payload) {
  return Boolean(payload?.flags && (Number(payload.flags) & MessageFlags.IsComponentsV2));
}

async function upsertManagedMessage(channel, panel) {
  const current = getManagedMessage(panel.key);
  const payload = await panel.build({
    channel,
    client: channel.client,
    guild: channel.guild,
  });

  if (current?.messageId && current.channelId === channel.id) {
    try {
      const existingMessage = await channel.messages.fetch(current.messageId);

      if (
        isComponentsV2Payload(payload) &&
        !existingMessage.flags?.has(MessageFlags.IsComponentsV2)
      ) {
        const replacementMessage = await channel.send(payload);
        await existingMessage.delete().catch(() => null);
        setManagedMessage(panel.key, {
          channelId: channel.id,
          messageId: replacementMessage.id,
        });

        return {
          status: "updated",
          label: panel.label,
          channelId: channel.id,
          messageId: replacementMessage.id,
        };
      }

      await existingMessage.edit(payload);
      setManagedMessage(panel.key, {
        channelId: channel.id,
        messageId: existingMessage.id,
      });

      return {
        status: "updated",
        label: panel.label,
        channelId: channel.id,
        messageId: existingMessage.id,
      };
    } catch {
      // Se a mensagem nao existir mais, o bot reenviara abaixo.
    }
  }

  const sentMessage = await channel.send(payload);
  setManagedMessage(panel.key, {
    channelId: channel.id,
    messageId: sentMessage.id,
  });

  return {
    status: "sent",
    label: panel.label,
    channelId: channel.id,
    messageId: sentMessage.id,
  };
}

async function sendNewManagedMessage(channel, panel) {
  const sentMessage = await channel.send(
    await panel.build({
      channel,
      client: channel.client,
      guild: channel.guild,
    }),
  );
  setManagedMessage(panel.key, {
    channelId: channel.id,
    messageId: sentMessage.id,
  });

  return {
    status: "sent",
    label: panel.label,
    channelId: channel.id,
    messageId: sentMessage.id,
  };
}

function getPanelDefinitions() {
  return Object.values(panels);
}

async function publishPanel(client, panelKey, options = {}) {
  const panel = panels[panelKey];

  if (!panel) {
    return {
      status: "error",
      label: panelKey,
      reason: "painel nao encontrado",
    };
  }

  const channelId = options.channelId || panel.channelId;

  if (!channelId) {
    return {
      status: "skipped",
      label: panel.label,
      reason: "canal nao configurado no .env",
    };
  }

  try {
    const channel = await fetchTextChannel(client, channelId);

    if (options.forceNew) {
      return await sendNewManagedMessage(channel, panel);
    }

    return await upsertManagedMessage(channel, panel);
  } catch (error) {
    return {
      status: "error",
      label: panel.label,
      reason: error.message,
    };
  }
}

module.exports = {
  getPanelDefinitions,
  publishPanel,
};
