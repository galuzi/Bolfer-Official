const { randomUUID } = require("node:crypto");
const {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} = require("discord.js");
const config = require("../config");
const { findPollByMessageId, getPoll, setPoll } = require("../storage/pollStore");
const { buildStyledEmbed } = require("../utils/embedFactory");

const POLL_VOTE_PREFIX = "poll_vote";
const POLL_CLOSE_PREFIX = "poll_close";
const MAX_BUTTONS_PER_ROW = 5;

function truncateLabel(label, maxLength = 70) {
  if (label.length <= maxLength) {
    return label;
  }

  return `${label.slice(0, maxLength - 3)}...`;
}

function buildVoteBar(percentage, length = 10) {
  const filledSlots = Math.round((percentage / 100) * length);
  return `${"#".repeat(filledSlots)}${"-".repeat(length - filledSlots)}`;
}

function getTotalVotes(poll) {
  return poll.options.reduce((sum, option) => sum + option.votes, 0);
}

function getWinningOptions(poll) {
  if (!poll.options.length) {
    return [];
  }

  const highestVotes = Math.max(...poll.options.map((option) => option.votes));
  return poll.options.filter((option) => option.votes === highestVotes);
}

function isWinningOption(poll, option) {
  if (!poll.closedAt || !getTotalVotes(poll)) {
    return false;
  }

  const winners = getWinningOptions(poll);
  return winners.some((winner) => winner.label === option.label && winner.votes === option.votes);
}

function buildResultBlocks(poll) {
  const totalVotes = getTotalVotes(poll);

  return poll.options.map((option, index) => {
    const percentage = totalVotes > 0 ? Math.round((option.votes / totalVotes) * 100) : 0;
    const optionTitle = isWinningOption(poll, option)
      ? `\u{1F3C6} ${index + 1}. ${option.label}`
      : `${index + 1}. ${option.label}`;

    return [
      `**${optionTitle}**`,
      `\`${buildVoteBar(percentage)}\` ${option.votes} voto(s) \u2022 ${percentage}%`,
    ].join("\n");
  });
}

function buildResultFields(poll) {
  const fields = [];
  const resultBlocks = buildResultBlocks(poll);
  let currentValue = "";

  for (const resultBlock of resultBlocks) {
    const nextValue = currentValue ? `${currentValue}\n\n${resultBlock}` : resultBlock;

    if (nextValue.length > 1024) {
      fields.push({
        name:
          fields.length === 0
            ? poll.closedAt
              ? "\u{1F3C6} Resultado final"
              : "\u{1F4CA} Parcial atual"
            : "\u21AA Continua\u00e7\u00e3o",
        value: currentValue,
        inline: false,
      });
      currentValue = resultBlock;
      continue;
    }

    currentValue = nextValue;
  }

  if (currentValue) {
    fields.push({
      name:
        fields.length === 0
          ? poll.closedAt
            ? "\u{1F3C6} Resultado final"
            : "\u{1F4CA} Parcial atual"
          : "\u21AA Continua\u00e7\u00e3o",
      value: currentValue,
      inline: false,
    });
  }

  return fields;
}

function buildPollDescription(poll) {
  const stateHeading = poll.closedAt
    ? "### Resultado consolidado pela comunidade"
    : "### A comunidade decide o destaque principal";

  const stateText = poll.closedAt
    ? "A vota\u00e7\u00e3o foi encerrada e o resultado oficial j\u00e1 est\u00e1 registrado abaixo."
    : "Seu voto entra na contagem oficial assim que uma op\u00e7\u00e3o for escolhida.";

  return [
    stateHeading,
    "",
    `> ${poll.description}`,
    "",
    stateText,
    "",
    "`1 voto por membro` \u2022 `parcial ao vivo` \u2022 `resultado oficial ao encerrar`",
    "",
    `Encerramento reservado ao cargo <@&${config.roleIds.primordial}>.`,
  ].join("\n");
}

function buildStatusFieldValue(poll) {
  const lines = [
    `**Status:** ${poll.closedAt ? "Encerrada" : "Aberta"}`,
    `**Criada por:** <@${poll.createdBy}>`,
    `**Canal:** <#${poll.channelId}>`,
    `**Total de votos:** ${getTotalVotes(poll)}`,
  ];

  if (poll.closedBy) {
    lines.push(`**Encerrada por:** <@${poll.closedBy}>`);
  }

  return lines.join("\n");
}

function buildClosingSummary(poll) {
  const totalVotes = getTotalVotes(poll);
  const winningOptions = getWinningOptions(poll);

  if (!totalVotes) {
    return [
      "Nenhum voto foi registrado antes do encerramento.",
      `Encerrada por <@${poll.closedBy}>.`,
    ].join("\n");
  }

  const summaryTitle =
    winningOptions.length === 1
      ? "Op\u00e7\u00e3o vencedora"
      : "Empate entre as op\u00e7\u00f5es";

  return [
    `**${summaryTitle}:** ${winningOptions.map((option) => option.label).join(" \u2022 ")}`,
    `**Total consolidado:** ${totalVotes} voto(s)`,
    `**Fechada por:** <@${poll.closedBy}>`,
  ].join("\n");
}

function buildPollEmbed(poll) {
  return buildStyledEmbed({
    eyebrow: `${config.branding.serverName} \u2022 Enquete oficial`,
    title: `\u{1F5F3}\uFE0F ${poll.title}`,
    description: buildPollDescription(poll),
    color: config.branding.primaryColor,
    image: poll.imageUrl || config.branding.announcementBannerUrl,
    fields: [
      {
        name: "\u{1F4CC} Painel da vota\u00e7\u00e3o",
        value: buildStatusFieldValue(poll),
        inline: false,
      },
      ...buildResultFields(poll),
      ...(poll.closedAt
        ? [
            {
              name: "\u2728 Encerramento",
              value: buildClosingSummary(poll),
              inline: false,
            },
          ]
        : []),
    ],
    footer: poll.closedAt
      ? `Resultado final registrado \u2022 ${config.branding.serverName}`
      : `Enquete oficial \u2022 ${config.branding.serverName}`,
  });
}

function chunkIntoRows(items, rowSize = MAX_BUTTONS_PER_ROW) {
  const rows = [];

  for (let index = 0; index < items.length; index += rowSize) {
    rows.push(items.slice(index, index + rowSize));
  }

  return rows;
}

function buildPollComponents(poll) {
  if (poll.closedAt) {
    return [];
  }

  const voteButtons = poll.options.map((option, index) =>
    new ButtonBuilder()
      .setCustomId(`${POLL_VOTE_PREFIX}:${poll.id}:${index}`)
      .setLabel(`${index + 1}. ${truncateLabel(option.label, 70)}`)
      .setStyle(ButtonStyle.Secondary),
  );

  return chunkIntoRows(voteButtons).map((buttons) =>
    new ActionRowBuilder().addComponents(buttons),
  );
}

function createPollRecord({
  title,
  description,
  options,
  imageUrl,
  channelId,
  messageId,
  createdBy,
}) {
  return {
    id: randomUUID(),
    title,
    description,
    imageUrl,
    channelId,
    messageId,
    createdBy,
    createdAt: new Date().toISOString(),
    closedAt: null,
    closedBy: null,
    options: options.map((label) => ({
      label,
      votes: 0,
    })),
    votesByUser: {},
  };
}

async function createPollMessage({
  channel,
  title,
  description,
  options,
  imageUrl,
  createdBy,
}) {
  const poll = createPollRecord({
    title,
    description,
    options,
    imageUrl,
    channelId: channel.id,
    messageId: null,
    createdBy,
  });

  const message = await channel.send({
    content: "@here",
    allowedMentions: { parse: ["everyone"] },
    embeds: [buildPollEmbed(poll)],
    components: buildPollComponents(poll),
  });

  poll.messageId = message.id;
  setPoll(poll.id, poll);

  return {
    poll,
    message,
  };
}

function parseVoteCustomId(customId) {
  if (!customId?.startsWith(`${POLL_VOTE_PREFIX}:`)) {
    return null;
  }

  const [, pollId, optionIndex] = customId.split(":");

  if (!pollId || optionIndex === undefined) {
    return null;
  }

  return {
    pollId,
    optionIndex: Number.parseInt(optionIndex, 10),
  };
}

function isLegacyCloseButton(customId) {
  return customId?.startsWith(`${POLL_CLOSE_PREFIX}:`);
}

async function handleVoteInteraction(interaction, poll, parsed) {
  if (poll.closedAt) {
    await interaction.reply({
      content: "Esta enquete ja foi encerrada e nao aceita novos votos.",
      ephemeral: true,
    });
    return true;
  }

  if (!Number.isInteger(parsed.optionIndex) || !poll.options[parsed.optionIndex]) {
    await interaction.reply({
      content: "A opcao selecionada nao e valida para esta enquete.",
      ephemeral: true,
    });
    return true;
  }

  const previousVoteIndex = poll.votesByUser[interaction.user.id];

  if (previousVoteIndex !== undefined) {
    await interaction.reply({
      content: `Seu voto ja foi registrado em **${poll.options[previousVoteIndex].label}**. Cada membro pode votar apenas uma vez.`,
      ephemeral: true,
    });
    return true;
  }

  const nextPoll = {
    ...poll,
    options: poll.options.map((option, index) => ({
      ...option,
      votes: index === parsed.optionIndex ? option.votes + 1 : option.votes,
    })),
    votesByUser: {
      ...poll.votesByUser,
      [interaction.user.id]: parsed.optionIndex,
    },
  };

  await interaction.update({
    embeds: [buildPollEmbed(nextPoll)],
    components: buildPollComponents(nextPoll),
  });

  setPoll(nextPoll.id, nextPoll);
  await interaction.followUp({
    content: `Seu voto em **${nextPoll.options[parsed.optionIndex].label}** foi registrado com sucesso.`,
    ephemeral: true,
  });
  return true;
}

async function handlePollVoteInteraction(interaction) {
  if (isLegacyCloseButton(interaction.customId)) {
    await interaction.reply({
      content: `O encerramento pelo botao foi desativado. Agora apenas <@&${config.roleIds.primordial}> pode fechar a enquete usando \`/encerrar-enquete\`.`,
      ephemeral: true,
    });
    return true;
  }

  const parsed = parseVoteCustomId(interaction.customId);

  if (!parsed) {
    return false;
  }

  const poll = getPoll(parsed.pollId);

  if (!poll) {
    await interaction.reply({
      content: "Esta enquete nao foi encontrada ou ja nao esta mais disponivel.",
      ephemeral: true,
    });
    return true;
  }

  return handleVoteInteraction(interaction, poll, parsed);
}

async function closePollByMessageId({ client, messageId, closedBy }) {
  const poll = findPollByMessageId(messageId);

  if (!poll) {
    throw new Error("Nao encontrei nenhuma enquete vinculada a esse ID de mensagem.");
  }

  if (poll.closedAt) {
    return {
      poll,
      alreadyClosed: true,
    };
  }

  const channel = await client.channels.fetch(poll.channelId);

  if (!channel?.isTextBased?.()) {
    throw new Error("O canal da enquete nao esta mais disponivel para edicao.");
  }

  const message = await channel.messages.fetch(poll.messageId);

  const closedPoll = {
    ...poll,
    closedAt: new Date().toISOString(),
    closedBy,
  };

  await message.edit({
    embeds: [buildPollEmbed(closedPoll)],
    components: buildPollComponents(closedPoll),
  });

  setPoll(closedPoll.id, closedPoll);

  return {
    poll: closedPoll,
    alreadyClosed: false,
  };
}

module.exports = {
  closePollByMessageId,
  createPollMessage,
  handlePollVoteInteraction,
};
