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

const ANNOUNCEMENT_TYPES = {
  evento: {
    label: "Evento",
    icon: "\u{1F389}",
    color: "#FF8A1F",
    eyebrow: "Agenda oficial",
    summary:
      "Abertura, datas, participa\u00e7\u00e3o e encerramento de eventos do servidor.",
  },
  atualizacao: {
    label: "Atualiza\u00e7\u00e3o",
    icon: "\u{1F195}",
    color: "#45C7FF",
    eyebrow: "Atualiza\u00e7\u00f5es do servidor",
    summary: "Novidades, mudan\u00e7as e melhorias publicadas pela staff.",
  },
  manutencao: {
    label: "Manuten\u00e7\u00e3o",
    icon: "\u{1F6E0}\uFE0F",
    color: "#F4A300",
    eyebrow: "Status t\u00e9cnico",
    summary:
      "Avisos de instabilidade, pausas tempor\u00e1rias e retorno dos servi\u00e7os.",
  },
  urgente: {
    label: "Urgente",
    icon: "\u{1F6A8}",
    color: "#FF4D4F",
    eyebrow: "Aviso priorit\u00e1rio",
    summary: "Comunicado de alta prioridade que exige aten\u00e7\u00e3o imediata.",
  },
  comunicado: {
    label: "Comunicado oficial",
    icon: "\u{1F4E2}",
    color: config.branding.primaryColor,
    eyebrow: "Comunicado institucional",
    summary:
      "Decis\u00f5es da staff, alinhamentos oficiais e avisos centrais do servidor.",
  },
  especial: {
    label: "Aviso especial",
    icon: "\u2728",
    color: "#D977FF",
    eyebrow: "Destaque especial",
    summary:
      "Campanhas, marcos, mensagens sazonais e momentos de celebra\u00e7\u00e3o.",
  },
};

function getOptionString(interaction, name) {
  const value = interaction.options.getString(name);
  return typeof value === "string" ? value.trim() : null;
}

function getAnnouncementPreset(type) {
  return ANNOUNCEMENT_TYPES[type] || ANNOUNCEMENT_TYPES.comunicado;
}

function buildAnnouncementDescription(subtitle, description, preset) {
  return [
    `> ${formatDescriptionText(subtitle) || preset.summary}`,
    "",
    formatDescriptionText(description),
  ].join("\n");
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName("anuncio")
    .setDescription("Publica um an\u00fancio padronizado da staff.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addStringOption((option) =>
      option
        .setName("tipo")
        .setDescription("Categoria visual do an\u00fancio.")
        .setRequired(true)
        .addChoices(
          { name: "Evento", value: "evento" },
          { name: "Atualiza\u00e7\u00e3o", value: "atualizacao" },
          { name: "Manuten\u00e7\u00e3o", value: "manutencao" },
          { name: "Urgente", value: "urgente" },
          { name: "Comunicado oficial", value: "comunicado" },
          { name: "Aviso especial", value: "especial" },
        ),
    )
    .addStringOption((option) =>
      option
        .setName("titulo")
        .setDescription("T\u00edtulo principal do an\u00fancio.")
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("descricao")
        .setDescription("Texto principal do an\u00fancio.")
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("subtitulo")
        .setDescription("Linha auxiliar exibida acima do an\u00fancio.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("imagem_url")
        .setDescription("Imagem opcional para destacar o an\u00fancio.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("botao_label")
        .setDescription("Texto do bot\u00e3o opcional.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("botao_url")
        .setDescription("URL do bot\u00e3o opcional.")
        .setRequired(false),
    )
    .addChannelOption((option) =>
      option
        .setName("canal")
        .setDescription("Canal em que o an\u00fancio ser\u00e1 publicado.")
        .addChannelTypes(ChannelType.GuildText, ChannelType.GuildAnnouncement),
    ),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const buttonLabel = getOptionString(interaction, "botao_label");
    const buttonUrl = getOptionString(interaction, "botao_url");

    if ((buttonLabel && !buttonUrl) || (!buttonLabel && buttonUrl)) {
      await interaction.editReply(
        "Informe `botao_label` e `botao_url` juntos quando quiser adicionar um bot\u00e3o.",
      );
      return;
    }

    const announcementType = getOptionString(interaction, "tipo");
    const preset = getAnnouncementPreset(announcementType);
    const channel = await resolveTargetChannel(
      interaction,
      "canal",
      config.channels.announcements.channelId,
    );

    const embed = buildStyledEmbed({
      eyebrow: `${config.branding.serverName} \u2022 ${preset.eyebrow}`,
      title: `${preset.icon} ${getOptionString(interaction, "titulo")}`,
      description: buildAnnouncementDescription(
        getOptionString(interaction, "subtitulo"),
        getOptionString(interaction, "descricao"),
        preset,
      ),
      image:
        getOptionString(interaction, "imagem_url") ||
        config.branding.announcementBannerUrl,
      color: preset.color,
      fields: [
        {
          name: "Categoria",
          value: `${preset.icon} ${preset.label}`,
          inline: true,
        },
        {
          name: "Publicado por",
          value: `<@${interaction.user.id}>`,
          inline: true,
        },
        {
          name: "Publicado em",
          value: `<#${channel.id}>`,
          inline: true,
        },
      ],
      footer: `Central oficial de an\u00fancios \u2022 ${config.branding.serverName}`,
    });

    const components = buildLinkRow([
      createLinkButton(buttonLabel, buttonUrl),
    ]);

    const message = await channel.send({
      content: "@here",
      allowedMentions: { parse: ["everyone"] },
      embeds: [embed],
      components,
    });

    await interaction.editReply(
      `An\u00fancio publicado em <#${channel.id}> com sucesso. ID: \`${message.id}\``,
    );
  },
};
