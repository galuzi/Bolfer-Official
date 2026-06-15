const { handlePollVoteInteraction } = require("../services/pollService");

module.exports = {
  async execute(interaction, client) {
    if (interaction.isButton()) {
      const handled = await handlePollVoteInteraction(interaction);

      if (handled) {
        return;
      }
    }

    if (!interaction.isChatInputCommand()) {
      return;
    }

    const command = client.commands.get(interaction.commandName);

    if (!command) {
      return;
    }

    try {
      await command.execute(interaction);
    } catch (error) {
      console.error(`[bot] Erro ao executar /${interaction.commandName}:`, error);

      const message = "Nao consegui concluir este comando. Verifique o console para mais detalhes.";

      if (interaction.deferred || interaction.replied) {
        await interaction.editReply(message).catch(() => null);
        return;
      }

      await interaction.reply({ content: message, ephemeral: true }).catch(() => null);
    }
  },
};
