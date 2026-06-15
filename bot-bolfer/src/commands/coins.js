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

const COINS_COLOR = "#F4C542";

function getOptionString(interaction, name) {
  const value = interaction.options.getString(name);
  return typeof value === "string" ? value.trim() : null;
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName("coins")
    .setDescription("Publica o comunicado oficial sobre coins, loja e eventos.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addStringOption((option) =>
      option
        .setName("imagem_url")
        .setDescription("Imagem opcional para destacar o comunicado.")
        .setMaxLength(500)
        .setRequired(false),
    )
    .addChannelOption((option) =>
      option
        .setName("canal")
        .setDescription("Canal em que o comunicado sera publicado.")
        .addChannelTypes(ChannelType.GuildText, ChannelType.GuildAnnouncement),
    ),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    const channel = await resolveTargetChannel(
      interaction,
      "canal",
      config.channels.announcements.channelId,
    );

    const embed = buildStyledEmbed({
      eyebrow: `${config.branding.serverName} \u2022 Economia do servidor`,
      title: "\u{1F4B0} Coins, Loja & Eventos",
      description: [
        "> As coins que voc\u00ea ganha participando dos eventos n\u00e3o s\u00e3o apenas pontos. Elas representam o seu progresso dentro da comunidade.",
        "",
        "Cada participa\u00e7\u00e3o, cada vit\u00f3ria e cada esfor\u00e7o se transforma em valor real dentro do servidor. Quanto mais voc\u00ea joga, mais acumula e mais vantagens desbloqueia ao longo do tempo.",
      ].join("\n"),
      color: COINS_COLOR,
      image:
        getOptionString(interaction, "imagem_url") ||
        config.branding.announcementBannerUrl,
      fields: [
        {
          name: "\u2726 O que as coins representam",
          value: [
            "Elas mostram a sua presen\u00e7a, o seu desempenho e a sua const\u00e2ncia dentro do servidor.",
            "N\u00e3o s\u00e3o apenas um contador: s\u00e3o a base das recompensas que vir\u00e3o com a evolu\u00e7\u00e3o da comunidade.",
          ].join("\n\n"),
          inline: false,
        },
        {
          name: "\u{1F3AF} Para que elas v\u00e3o servir",
          value: [
            "\u{1F3AE} Resgatar jogos completos",
            "\u{1F511} Adquirir keys exclusivas",
            "\u{1F381} Ganhar recompensas especiais e limitadas",
            "\u{1F48E} Acessar conte\u00fados e vantagens \u00fanicas",
            "\u{1F525} Participar de eventos VIP e sorteios diferenciados",
          ].join("\n"),
          inline: false,
        },
        {
          name: "\u26A0\uFE0F Por que come\u00e7ar agora importa",
          value: [
            "As coins ter\u00e3o cada vez mais utilidade conforme o servidor cresce.",
            "",
            "\u{1F449} Quem come\u00e7a agora constr\u00f3i vantagem antes dos demais",
            "\u{1F449} Quem acumula mais se posiciona melhor para as recompensas futuras",
          ].join("\n"),
          inline: false,
        },
        {
          name: "\u{1F680} Ciclo das coins",
          value: [
            "`Voc\u00ea participa` \u2192 `ganha coins` \u2192 `acumula` \u2192 `troca por recompensas reais`",
          ].join("\n"),
          inline: false,
        },
        {
          name: "\u{1F4A1} Vis\u00e3o de longo prazo",
          value:
            "N\u00e3o subestime suas coins. Conforme o servidor evoluir, elas podem valer muito mais do que parecem hoje.",
          inline: false,
        },
        {
          name: "\u{1F4CC} Registro do comunicado",
          value: [
            "**Categoria:** \u{1F4E2} Comunicado oficial",
            `**Publicado por:** <@${interaction.user.id}>`,
            `**Publicado em:** <#${channel.id}>`,
          ].join("\n"),
          inline: false,
        },
      ],
      footer: `Central oficial de an\u00fancios \u2022 ${config.branding.serverName}`,
    });

    const components = buildLinkRow([
      createLinkButton("Acompanhar o site oficial", config.links.site),
    ]);

    const message = await channel.send({
      content: "@here",
      allowedMentions: { parse: ["everyone"] },
      embeds: [embed],
      components,
    });

    await interaction.editReply(
      `Comunicado sobre coins publicado em <#${channel.id}> com sucesso. ID: \`${message.id}\``,
    );
  },
};
