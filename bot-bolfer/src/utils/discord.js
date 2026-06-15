function isSupportedTextChannel(channel) {
  return Boolean(channel?.isTextBased?.()) && !channel?.isDMBased?.();
}

async function fetchTextChannel(client, channelId) {
  const channel = await client.channels.fetch(channelId);

  if (!isSupportedTextChannel(channel)) {
    throw new Error("o canal configurado nao aceita mensagens de texto");
  }

  return channel;
}

async function resolveTargetChannel(
  interaction,
  optionName = "canal",
  fallbackChannelId = null,
) {
  const selectedChannel = interaction.options.getChannel(optionName);

  if (selectedChannel) {
    if (!isSupportedTextChannel(selectedChannel)) {
      throw new Error("selecione um canal de texto valido para continuar");
    }

    return selectedChannel;
  }

  if (fallbackChannelId) {
    return fetchTextChannel(interaction.client, fallbackChannelId);
  }

  const defaultChannel = interaction.channel;

  if (!isSupportedTextChannel(defaultChannel)) {
    throw new Error("selecione um canal de texto valido para continuar");
  }

  return defaultChannel;
}

module.exports = {
  fetchTextChannel,
  resolveTargetChannel,
};
