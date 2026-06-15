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

function createChannelOption(option) {
  return option
    .setName("canal")
    .setDescription("Canal alvo da mensagem.")
    .addChannelTypes(ChannelType.GuildText, ChannelType.GuildAnnouncement);
}

function addSharedOptionalOptions(command) {
  return command
    .addStringOption((option) =>
      option
        .setName("subtitulo")
        .setDescription("Texto curto acima do titulo.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("cor")
        .setDescription("Cor em hexadecimal. Ex.: #A63D2A")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("thumbnail_url")
        .setDescription("Thumbnail opcional.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("imagem_url")
        .setDescription("Imagem opcional.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("rodape")
        .setDescription("Texto do rodape.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("botao_label")
        .setDescription("Texto do botao opcional.")
        .setRequired(false),
    )
    .addStringOption((option) =>
      option
        .setName("botao_url")
        .setDescription("URL do botao opcional.")
        .setRequired(false),
    );
}

function buildCustomEmbed(input, baseData = {}) {
  return buildStyledEmbed({
    eyebrow:
      input.subtitle ??
      baseData.author?.name ??
      `${config.branding.serverName} • Mensagem customizada`,
    title: input.title ?? baseData.title ?? "Mensagem personalizada",
    description:
      formatDescriptionText(input.description) ??
      formatDescriptionText(baseData.description) ??
      "Preencha a descricao para completar o embed.",
    color: input.color ?? baseData.color ?? config.branding.primaryColor,
    thumbnail:
      input.thumbnailUrl ?? baseData.thumbnail?.url ?? config.branding.thumbnailUrl,
    image: input.imageUrl ?? baseData.image?.url ?? null,
    footer: input.footer ?? baseData.footer?.text ?? config.branding.footerText,
    fields: baseData.fields || [],
  });
}

function extractButtonState(interaction) {
  const label = getOptionString(interaction, "botao_label");
  const url = getOptionString(interaction, "botao_url");

  if ((label && !url) || (!label && url)) {
    throw new Error(
      "Informe botao_label e botao_url juntos quando quiser adicionar um botao.",
    );
  }

  return { label, url };
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName("embed")
    .setDescription("Cria ou edita embeds customizados.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addSubcommand((subcommand) =>
      addSharedOptionalOptions(
        subcommand
          .setName("criar")
          .setDescription("Cria e envia um embed customizado.")
          .addStringOption((option) =>
            option
              .setName("titulo")
              .setDescription("Titulo do embed.")
              .setRequired(true),
          )
          .addStringOption((option) =>
            option
              .setName("descricao")
              .setDescription("Descricao principal do embed.")
              .setRequired(true),
          )
          .addChannelOption(createChannelOption),
      ),
    )
    .addSubcommand((subcommand) =>
      addSharedOptionalOptions(
        subcommand
          .setName("editar")
          .setDescription("Edita uma mensagem do proprio bot pelo ID.")
          .addStringOption((option) =>
            option
              .setName("mensagem_id")
              .setDescription("ID da mensagem que sera editada.")
              .setRequired(true),
          )
          .addStringOption((option) =>
            option
              .setName("titulo")
              .setDescription("Titulo do embed.")
              .setRequired(false),
          )
          .addStringOption((option) =>
            option
              .setName("descricao")
              .setDescription("Descricao principal do embed.")
              .setRequired(false),
          )
          .addChannelOption(createChannelOption),
      ),
    ),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    let button;

    try {
      button = extractButtonState(interaction);
    } catch (error) {
      await interaction.editReply(error.message);
      return;
    }

    const subcommand = interaction.options.getSubcommand();

    if (subcommand === "criar") {
      const channel = await resolveTargetChannel(interaction);
      const embed = buildCustomEmbed({
        title: getOptionString(interaction, "titulo"),
        description: getOptionString(interaction, "descricao"),
        subtitle: getOptionString(interaction, "subtitulo"),
        color: getOptionString(interaction, "cor"),
        thumbnailUrl: getOptionString(interaction, "thumbnail_url"),
        imageUrl: getOptionString(interaction, "imagem_url"),
        footer: getOptionString(interaction, "rodape"),
      });

      const message = await channel.send({
        embeds: [embed],
        components: buildLinkRow([
          createLinkButton(button.label, button.url),
        ]),
      });

      await interaction.editReply(
        `Embed enviado em <#${channel.id}> com sucesso. ID: \`${message.id}\``,
      );
      return;
    }

    const channel = await resolveTargetChannel(interaction);
    const messageId = getOptionString(interaction, "mensagem_id");
    const message = await channel.messages.fetch(messageId);

    if (message.author.id !== interaction.client.user.id) {
      await interaction.editReply(
        "Eu so posso editar mensagens enviadas por este proprio bot.",
      );
      return;
    }

    const baseData = message.embeds[0] ? message.embeds[0].toJSON() : {};
    const embed = buildCustomEmbed(
      {
        title: getOptionString(interaction, "titulo"),
        description: getOptionString(interaction, "descricao"),
        subtitle: getOptionString(interaction, "subtitulo"),
        color: getOptionString(interaction, "cor"),
        thumbnailUrl: getOptionString(interaction, "thumbnail_url"),
        imageUrl: getOptionString(interaction, "imagem_url"),
        footer: getOptionString(interaction, "rodape"),
      },
      baseData,
    );

    const components =
      button.label && button.url
        ? buildLinkRow([createLinkButton(button.label, button.url)])
        : message.components.map((row) => row.toJSON());

    await message.edit({
      embeds: [embed],
      components,
    });

    await interaction.editReply(
      `Mensagem \`${message.id}\` atualizada com sucesso em <#${channel.id}>.`,
    );
  },
};
