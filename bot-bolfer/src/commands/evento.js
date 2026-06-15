const {
  ChannelType,
  PermissionFlagsBits,
  SlashCommandBuilder,
} = require("discord.js");
const config = require("../config");
const { resolveTargetChannel } = require("../utils/discord");
const {
  buildLinkRow,
  buildStyledEmbed,
  createLinkButton,
} = require("../utils/embedFactory");
const { formatDescriptionText } = require("../utils/textFormatter");

function getOptionString(interaction, name) {
  const value = interaction.options.getString(name);
  return typeof value === "string" ? value.trim() : null;
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName("evento")
    .setDescription("Publica um card visual de evento.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addStringOption((option) =>
      option
        .setName("titulo")
        .setDescription("Nome do evento.")
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("data")
        .setDescription("Data do evento. Ex.: 28/03/2026.")
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("hora")
        .setDescription("Horario do evento. Ex.: 20:00 BRT.")
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("descricao")
        .setDescription("Descricao do evento.")
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("observacoes")
        .setDescription("Informacoes extras ou requisitos.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("imagem_url")
        .setDescription("Imagem opcional para o evento.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("link")
        .setDescription("Link opcional para inscricao ou canal relacionado.")
        .setRequired(false),
    )
    .addChannelOption((option) =>
      option
        .setName("canal")
        .setDescription("Canal onde o evento sera publicado.")
        .addChannelTypes(ChannelType.GuildText, ChannelType.GuildAnnouncement),
    ),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const channel = await resolveTargetChannel(interaction);
    const link = getOptionString(interaction, "link");

    const embed = buildStyledEmbed({
      eyebrow: `${config.branding.serverName} • Agenda`,
      title: getOptionString(interaction, "titulo"),
      description: formatDescriptionText(getOptionString(interaction, "descricao")),
      image:
        getOptionString(interaction, "imagem_url") || config.branding.eventBannerUrl,
      color: config.branding.primaryColor,
      fields: [
        {
          name: "Data",
          value: `\`${getOptionString(interaction, "data")}\``,
          inline: true,
        },
        {
          name: "Horario",
          value: `\`${getOptionString(interaction, "hora")}\``,
          inline: true,
        },
        {
          name: "Observacoes",
          value:
            formatDescriptionText(getOptionString(interaction, "observacoes")) ||
            "Chegue alguns minutos antes para nao perder o inicio.",
          inline: false,
        },
      ],
      footer: "Eventos oficiais do servidor",
    });

    const components = buildLinkRow([
      createLinkButton("Tenho interesse", link),
    ]);

    const message = await channel.send({
      embeds: [embed],
      components,
    });

    await interaction.editReply(
      `Evento publicado em <#${channel.id}> com sucesso. ID: \`${message.id}\``,
    );
  },
};
