const {
  PermissionFlagsBits,
  SlashCommandBuilder,
} = require("discord.js");
const { getPanelDefinitions, publishPanel } = require("../services/panelManager");

function formatResult(result) {
  if (result.status === "updated") {
    return `- ${result.label}: atualizado em <#${result.channelId}>`;
  }

  if (result.status === "sent") {
    return `- ${result.label}: publicado em <#${result.channelId}>`;
  }

  if (result.status === "skipped") {
    return `- ${result.label}: ignorado (${result.reason})`;
  }

  return `- ${result.label}: erro (${result.reason})`;
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName("setup")
    .setDescription("Publica ou atualiza os paineis principais do servidor.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const results = [];

    for (const panel of getPanelDefinitions()) {
      const result = await publishPanel(interaction.client, panel.key);
      results.push(result);
    }

    await interaction.editReply({
      content: ["Setup concluido.", ...results.map(formatResult)].join("\n"),
    });
  },
};
