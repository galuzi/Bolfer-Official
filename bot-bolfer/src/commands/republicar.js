const {
  ChannelType,
  PermissionFlagsBits,
  SlashCommandBuilder,
} = require("discord.js");
const { publishPanel } = require("../services/panelManager");

module.exports = {
  data: new SlashCommandBuilder()
    .setName("republicar")
    .setDescription("Reenvia um painel pronto e atualiza o ID salvo.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addStringOption((option) =>
      option
        .setName("painel")
        .setDescription("Qual painel deve ser reenviado.")
        .setRequired(true)
        .addChoices(
          { name: "Boas-vindas", value: "welcome" },
          { name: "Regras", value: "rules" },
          { name: "Cargos", value: "roles" },
          { name: "Suporte", value: "support" },
        ),
    )
    .addChannelOption((option) =>
      option
        .setName("canal")
        .setDescription("Canal de destino. Se vazio, usa o canal configurado.")
        .addChannelTypes(ChannelType.GuildText, ChannelType.GuildAnnouncement),
    ),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const panelKey = interaction.options.getString("painel", true);
    const channel = interaction.options.getChannel("canal");
    const result = await publishPanel(interaction.client, panelKey, {
      channelId: channel?.id,
      forceNew: true,
    });

    if (result.status === "sent") {
      await interaction.editReply(
        `Painel ${result.label.toLowerCase()} reenviado em <#${result.channelId}>.`,
      );
      return;
    }

    const reason = result.reason || "nao foi possivel republicar o painel.";
    await interaction.editReply(`Falha ao republicar ${result.label}: ${reason}`);
  },
};
