const { PermissionFlagsBits, SlashCommandBuilder } = require("discord.js");
const config = require("../config");
const { closePollByMessageId } = require("../services/pollService");
const { memberHasRole } = require("../utils/memberRoles");

function getOptionString(interaction, name) {
  const value = interaction.options.getString(name);
  return typeof value === "string" ? value.trim() : null;
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName("encerrar-enquete")
    .setDescription("Encerra uma enquete oficial pelo ID da mensagem.")
    .setDMPermission(false)
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageGuild)
    .addStringOption((option) =>
      option
        .setName("mensagem_id")
        .setDescription("ID da mensagem da enquete que sera encerrada.")
        .setRequired(true)
        .setMinLength(17)
        .setMaxLength(25),
    ),

  async execute(interaction) {
    await interaction.deferReply({ ephemeral: true });

    if (!memberHasRole(interaction, config.roleIds.primordial)) {
      await interaction.editReply(
        `Apenas o cargo <@&${config.roleIds.primordial}> pode encerrar enquetes.`,
      );
      return;
    }

    const messageId = getOptionString(interaction, "mensagem_id");

    if (!/^\d{17,25}$/.test(messageId || "")) {
      await interaction.editReply(
        "Informe um ID de mensagem valido para encerrar a enquete.",
      );
      return;
    }

    const result = await closePollByMessageId({
      client: interaction.client,
      messageId,
      closedBy: interaction.user.id,
    });

    if (result.alreadyClosed) {
      await interaction.editReply(
        "Essa enquete ja estava encerrada anteriormente.",
      );
      return;
    }

    await interaction.editReply(
      `Enquete encerrada com sucesso. Resultado final registrado em <#${result.poll.channelId}>.`,
    );
  },
};
