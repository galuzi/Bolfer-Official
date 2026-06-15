const config = require("../config");

async function registerCommands(client) {
  const payload = [...client.commands.values()].map((command) =>
    command.data.toJSON(),
  );

  try {
    if (config.guildId) {
      await client.application.commands.set(payload, config.guildId);
      console.log(
        `[bot] ${payload.length} comandos registrados na guild ${config.guildId}.`,
      );
      return;
    }

    await client.application.commands.set(payload);
    console.log(`[bot] ${payload.length} comandos registrados globalmente.`);
  } catch (error) {
    if (error?.code === 50001) {
      throw new Error(
        `Sem acesso para registrar comandos na guild ${config.guildId}. Verifique o GUILD_ID do .env e confirme se o bot esta dentro desse servidor.`,
      );
    }

    throw error;
  }
}

module.exports = {
  async execute(client) {
    console.log(`[bot] Conectado como ${client.user.tag}.`);
    await registerCommands(client);
  },
};
