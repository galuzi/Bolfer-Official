const {
  ChannelType,
  PermissionFlagsBits,
  SlashCommandBuilder,
} = require("discord.js");
const config = require("../config");
const { createPollMessage } = require("../services/pollService");
const { resolveTargetChannel } = require("../utils/discord");
const { formatDescriptionText } = require("../utils/textFormatter");

const POLL_OPTION_NAMES = Array.from({ length: 10 }, (_, index) => `opcao_${index + 1}`);

function getOptionString(interaction, name) {
  const value = interaction.options.getString(name);
  return typeof value === "string" ? value.trim() : null;
}

function collectPollOptions(interaction) {
  return POLL_OPTION_NAMES.map((optionName) => getOptionString(interaction, optionName)).filter(
    Boolean,
  );
}

function hasDuplicateOptions(options) {
  const normalizedOptions = options.map((option) => option.toLowerCase());
  return new Set(normalizedOptions).size !== normalizedOptions.length;
}

function buildPollCommand() {
  const builder = new SlashCommandBuilder()
    .setName("enquete")
    .setDescription("Publica uma enquete oficial com voto unico por membro.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addStringOption((option) =>
      option
        .setName("titulo")
        .setDescription("Pergunta principal da enquete.")
        .setMaxLength(120)
        .setRequired(true),
    )
    .addStringOption((option) =>
      option
        .setName("descricao")
        .setDescription("Contexto ou explicacao da enquete.")
        .setMaxLength(600)
        .setRequired(true),
    );

  for (const [index, optionName] of POLL_OPTION_NAMES.entries()) {
    builder.addStringOption((option) =>
      option
        .setName(optionName)
        .setDescription(`${index + 1}a opcao de voto.`)
        .setMaxLength(80)
        .setRequired(index < 2),
    );
  }

  builder
    .addStringOption((option) =>
      option
        .setName("imagem_url")
        .setDescription("Imagem opcional para destacar a enquete.")
        .setMaxLength(500)
        .setRequired(false),
    )
    .addChannelOption((option) =>
      option
        .setName("canal")
        .setDescription("Canal em que a enquete sera publicada.")
        .addChannelTypes(ChannelType.GuildText, ChannelType.GuildAnnouncement),
    );

  return builder;
}

module.exports = {
  data: buildPollCommand(),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const options = collectPollOptions(interaction);

    if (options.length < 2) {
      await interaction.editReply(
        "Informe pelo menos duas opcoes para criar a enquete.",
      );
      return;
    }

    if (options.length > 10) {
      await interaction.editReply(
        "A enquete aceita no maximo 10 opcoes.",
      );
      return;
    }

    if (hasDuplicateOptions(options)) {
      await interaction.editReply(
        "As opcoes da enquete precisam ser diferentes entre si.",
      );
      return;
    }

    const channel = await resolveTargetChannel(
      interaction,
      "canal",
      config.channels.announcements.channelId,
    );

    const { message } = await createPollMessage({
      channel,
      title: getOptionString(interaction, "titulo"),
      description: formatDescriptionText(getOptionString(interaction, "descricao")),
      options,
      imageUrl: getOptionString(interaction, "imagem_url"),
      createdBy: interaction.user.id,
    });

    await interaction.editReply(
      `Enquete publicada em <#${channel.id}> com sucesso. ID: \`${message.id}\``,
    );
  },
};
