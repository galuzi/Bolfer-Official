const {
  ContainerBuilder,
  MediaGalleryBuilder,
  MediaGalleryItemBuilder,
  MessageFlags,
  SeparatorBuilder,
  SeparatorSpacingSize,
  TextDisplayBuilder,
} = require("discord.js");
const config = require("../config");
const {
  buildLinkRow,
  buildStyledEmbed,
  createLinkButton,
  normalizeUrl,
} = require("../utils/embedFactory");

const ICONS = {
  sparkles: "\u2726",
  compass: "\u{1F9ED}",
  shield: "\u{1F6E1}\uFE0F",
  tag: "\u{1F3F7}\uFE0F",
  ring: "\u{1F6DF}",
  shine: "\u2728",
  pin: "\u{1F4CC}",
  chat: "\u{1F4AC}",
  tools: "\u{1F6E0}\uFE0F",
  stop: "\u{1F6AB}",
  lock: "\u{1F512}",
  balance: "\u2696\uFE0F",
  cabinet: "\u{1F5C2}\uFE0F",
  crown: "\u{1F451}",
  herald: "\u{1F4E3}",
  eye: "\u{1F441}\uFE0F",
  gem: "\u{1F48E}",
  flame: "\u{1F525}",
  globe: "\u{1F310}",
};

const ROLE_PRESENTATIONS = [
  {
    name: "Borfer Primordial",
    subtitle: "A origem absoluta",
    description:
      "A origem de tudo. A mente por tr\u00e1s do caos e da ordem. Nada acontece sem sua vontade \u2014 ele n\u00e3o segue regras, ele as cria.",
  },
  {
    name: "Arauto do Borfer",
    subtitle: "A voz direta do Primordial",
    description:
      "Respons\u00e1veis por manter o equil\u00edbrio do servidor e garantir que tudo funcione como deve.",
  },
  {
    name: "Fiscal do Caos",
    subtitle: "Vigil\u00e2ncia entre a ordem e o caos",
    description:
      "Observadores atentos entre a ordem e o caos. Mant\u00eam o controle, aplicam as regras e garantem que tudo permane\u00e7a sob vigil\u00e2ncia.",
  },
  {
    name: "Borfer Escolhido",
    subtitle: "Reconhecimento conquistado",
    description:
      "N\u00e3o \u00e9 dado, \u00e9 conquistado. Aqueles que apoiam o servidor e recebem reconhecimento direto do Primordial \u2014 um status acima dos demais.",
  },
  {
    name: "Membro",
    subtitle: "A base viva da comunidade",
    description:
      "Parte essencial da comunidade. Aqui \u00e9 onde tudo acontece \u2014 intera\u00e7\u00e3o, evolu\u00e7\u00e3o e presen\u00e7a.",
  },
];

function mentionChannel(channelId, fallbackText) {
  return channelId ? `<#${channelId}>` : fallbackText;
}

function toAccentColor(value) {
  if (typeof value === "number") {
    return value;
  }

  if (typeof value === "string") {
    const hex = value.replace("#", "").trim();
    if (/^[0-9a-fA-F]{6}$/.test(hex)) {
      return Number.parseInt(hex, 16);
    }
  }

  return 0xa63d2a;
}

function addRowsToContainer(container, rows) {
  if (rows.length) {
    container.addActionRowComponents(...rows);
  }
}

function addDivider(container, divider = true, spacing = SeparatorSpacingSize.Large) {
  container.addSeparatorComponents(
    new SeparatorBuilder().setDivider(divider).setSpacing(spacing),
  );
}

function addTextBlock(container, content) {
  container.addTextDisplayComponents(
    new TextDisplayBuilder().setContent(content),
  );
}

function addHeroImage(container, url, description) {
  const safeUrl = normalizeUrl(url);

  if (!safeUrl) {
    return;
  }

  container.addMediaGalleryComponents(
    new MediaGalleryBuilder().addItems(
      new MediaGalleryItemBuilder().setURL(safeUrl).setDescription(description),
    ),
  );
}

async function resolveRoleMentions(guild) {
  const mentions = new Map();

  for (const role of ROLE_PRESENTATIONS) {
    mentions.set(role.name, `**${role.name}**`);
  }

  if (!guild) {
    return mentions;
  }

  const roles = await guild.roles.fetch();

  for (const presentation of ROLE_PRESENTATIONS) {
    const resolvedRole = roles.find(
      (role) => role.name.toLowerCase() === presentation.name.toLowerCase(),
    );

    if (resolvedRole) {
      mentions.set(presentation.name, `<@&${resolvedRole.id}>`);
    }
  }

  return mentions;
}

function buildWelcomePanel() {
  const container = new ContainerBuilder().setAccentColor(
    toAccentColor(config.branding.primaryColor),
  );

  addHeroImage(
    container,
    config.branding.welcomeBannerUrl,
    `${config.branding.serverName} banner de boas-vindas`,
  );

  addTextBlock(
    container,
    [
      `# Boas-vindas ao ${config.branding.serverName} ${ICONS.sparkles}`,
      "",
      `> Voc\u00ea entrou no lugar certo. Este servidor foi pensado para ser bonito, vivo e f\u00e1cil de entender desde o primeiro clique.`,
      "",
      "Sem bagun\u00e7a, sem ru\u00eddo \u2014 s\u00f3 um espa\u00e7o onde cada coisa tem seu lugar e cada pessoa tem presen\u00e7a.",
    ].join("\n"),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.compass} Seu primeiro passo no servidor`,
      `Comece em ${mentionChannel(
        config.channels.rules.channelId,
        "regras",
      )} para entender o clima da comunidade. Depois, passe em ${mentionChannel(
        config.channels.roles.channelId,
        "cargos",
      )} para ver o que cada cargo representa dentro do servidor.`,
    ].join("\n"),
  );

  addRowsToContainer(
    container,
    buildLinkRow([
      createLinkButton(`${ICONS.shield} Ver regras`, config.links.rules),
      createLinkButton(`${ICONS.tag} Entender cargos`, config.links.roles),
    ]),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.shine} Tudo aqui foi pensado para ser agrad\u00e1vel de acompanhar`,
      "A proposta n\u00e3o \u00e9 jogar um monte de blocos na tela. \u00c9 deixar a experi\u00eancia limpa, bonita e intuitiva tanto no desktop quanto no celular.",
    ].join("\n"),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.ring} Travou em algo? A staff te orienta`,
      `Se surgir alguma d\u00favida, erro ou qualquer problema, procure ${mentionChannel(
        config.channels.support.channelId,
        "o suporte",
      )}. \u00c9 melhor explicar uma vez com calma do que ficar perdido dentro do servidor.`,
    ].join("\n"),
  );

  addRowsToContainer(
    container,
    buildLinkRow([
      createLinkButton(`${ICONS.chat} Preciso de ajuda`, config.links.support),
    ]),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.globe} Fique de olho no site oficial`,
      `D\u00ea uma olhada no nosso site e acompanhe as novidades. O ${config.branding.serverName} pode liberar presentes incr\u00edveis que voc\u00ea n\u00e3o vai querer perder.`,
    ].join("\n"),
  );

  addRowsToContainer(
    container,
    buildLinkRow([
      createLinkButton(`${ICONS.sparkles} Acessar site oficial`, config.links.site),
    ]),
  );

  addDivider(container, false, SeparatorSpacingSize.Small);
  addTextBlock(
    container,
    `- Organizado com carinho pela staff de ${config.branding.serverName}.`,
  );

  return {
    flags: MessageFlags.IsComponentsV2,
    components: [container],
  };
}

function buildRulesPanel() {
  const container = new ContainerBuilder().setAccentColor(
    toAccentColor(config.branding.primaryColor),
  );

  addHeroImage(
    container,
    config.branding.rulesBannerUrl,
    `${config.branding.serverName} banner de regras`,
  );

  addTextBlock(
    container,
    [
      `# Regras do servidor ${ICONS.sparkles}`,
      "",
      `> Estas regras existem para manter o servidor bonito por fora e saud\u00e1vel por dentro.`,
      "",
      "A ideia \u00e9 simples: respeito, bom senso e organiza\u00e7\u00e3o. Quando cada pessoa colabora com isso, a experi\u00eancia fica melhor para todo mundo.",
    ].join("\n"),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.compass} O clima que queremos manter`,
      "- Respeito acima de tudo em qualquer conversa.",
      "- Uso correto dos canais para manter o servidor organizado.",
      "- Participa\u00e7\u00e3o leve, clara e sem polui\u00e7\u00e3o visual.",
    ].join("\n"),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.stop} O que n\u00e3o tem espa\u00e7o aqui`,
      "- Flood, spam e repeti\u00e7\u00e3o de mensagens fora de contexto.",
      "- Ataques pessoais, preconceito, ass\u00e9dio ou provoca\u00e7\u00f5es insistentes.",
      "- Autopromo\u00e7\u00e3o sem permiss\u00e3o, links suspeitos e conte\u00fado inadequado.",
    ].join("\n"),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.lock} Privacidade e seguran\u00e7a`,
      "N\u00e3o compartilhe dados pessoais de terceiros, conte\u00fados ilegais ou qualquer material que coloque membros da comunidade em risco.",
    ].join("\n"),
  );

  addDivider(container);

  addTextBlock(
    container,
    [
      `## ${ICONS.balance} Como a staff atua`,
      "Dependendo da gravidade da situa\u00e7\u00e3o, a equipe pode aplicar aviso, mute, restri\u00e7\u00e3o de acesso ou banimento. O objetivo \u00e9 proteger o ambiente e manter a conviv\u00eancia saud\u00e1vel.",
    ].join("\n"),
  );

  addRowsToContainer(
    container,
    buildLinkRow([
      createLinkButton(`${ICONS.sparkles} Ver boas-vindas`, config.links.welcome),
      createLinkButton(`${ICONS.tag} Entender cargos`, config.links.roles),
      createLinkButton(`${ICONS.chat} Pedir ajuda`, config.links.support),
    ]),
  );

  addDivider(container, false, SeparatorSpacingSize.Small);
  addTextBlock(
    container,
    "Se algo n\u00e3o estiver claro, procure a staff antes de agir no impulso.",
  );

  return {
    flags: MessageFlags.IsComponentsV2,
    components: [container],
  };
}

async function buildRolesPanel(context = {}) {
  const container = new ContainerBuilder().setAccentColor(
    toAccentColor(config.branding.primaryColor),
  );

  const roleMentions = await resolveRoleMentions(context.guild);

  addHeroImage(
    container,
    config.branding.rolesBannerUrl,
    `${config.branding.serverName} banner de cargos`,
  );

  addTextBlock(
    container,
    [
      `# Cargos do ${config.branding.serverName} ${ICONS.sparkles}`,
      "",
      `> N\u00e3o \u00e9 um painel para escolher cargos. \u00c9 um mapa simples para entender quem \u00e9 quem dentro do servidor.`,
      "",
      "Cada nome abaixo mostra uma presen\u00e7a diferente dentro da comunidade: origem, responsabilidade, vigil\u00e2ncia, reconhecimento e participa\u00e7\u00e3o.",
    ].join("\n"),
  );

  addDivider(container, false, SeparatorSpacingSize.Small);

  addTextBlock(
    container,
    ROLE_PRESENTATIONS.map((role) => {
      const mention = roleMentions.get(role.name) || `**${role.name}**`;
      return `${mention} ${role.description}`;
    }).join("\n\n"),
  );

  addDivider(container, false, SeparatorSpacingSize.Small);
  addTextBlock(
    container,
    "Painel informativo. Aqui, cargos n\u00e3o s\u00e3o escolhidos: s\u00e3o compreendidos.",
  );

  return {
    flags: MessageFlags.IsComponentsV2,
    allowedMentions: { parse: [] },
    components: [container],
  };
}

function buildSupportPanel() {
  const embed = buildStyledEmbed({
    eyebrow: `${config.branding.serverName} | Suporte`,
    title: "D\u00favidas, suporte e relat\u00f3rios",
    description:
      "Use este painel como ponto de apoio para orientar pedidos de ajuda, relat\u00f3rios e contato com a staff sem deixar a experi\u00eancia confusa ou pesada.",
    color: config.branding.primaryColor,
    fields: [
      {
        name: `${ICONS.pin} Antes de pedir ajuda`,
        value:
          "Verifique as regras, o canal de cargos e as mensagens fixadas. Muitas respostas importantes j\u00e1 costumam estar por ali.",
        inline: false,
      },
      {
        name: `${ICONS.chat} Como falar com a equipe`,
        value:
          "Explique o problema com contexto, envie prints quando fizer sentido e diga em qual canal a situa\u00e7\u00e3o aconteceu.",
        inline: false,
      },
      {
        name: `${ICONS.tools} Quando reportar algo`,
        value:
          "Use o suporte para bugs, dificuldades de acesso, d\u00favidas sobre organiza\u00e7\u00e3o e qualquer situa\u00e7\u00e3o que precise de orienta\u00e7\u00e3o da staff.",
        inline: false,
      },
    ],
  });

  return {
    embeds: [embed],
    components: buildLinkRow([
      createLinkButton(`${ICONS.ring} Abrir suporte`, config.links.support),
    ]),
  };
}

module.exports = {
  buildWelcomePanel,
  buildRulesPanel,
  buildRolesPanel,
  buildSupportPanel,
};
