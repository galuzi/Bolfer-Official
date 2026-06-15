const { Client, Collection, GatewayIntentBits } = require("discord.js");
const config = require("./config");
const commands = require("./commands");
const readyEvent = require("./events/ready");
const interactionCreateEvent = require("./events/interactionCreate");

function registerShutdownHandlers(client) {
  let shuttingDown = false;

  async function shutdown(signal) {
    if (shuttingDown) {
      return;
    }

    shuttingDown = true;
    console.log(`[bot] Encerrando o bot (${signal})...`);

    try {
      client.destroy();
    } finally {
      process.exit(0);
    }
  }

  process.once("SIGINT", () => {
    shutdown("SIGINT");
  });

  process.once("SIGTERM", () => {
    shutdown("SIGTERM");
  });
}

function createClient() {
  const client = new Client({
    intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildMessages],
  });

  client.commands = new Collection();

  for (const command of commands) {
    client.commands.set(command.data.name, command);
  }

  client.once("clientReady", () => readyEvent.execute(client));
  client.on("interactionCreate", (interaction) =>
    interactionCreateEvent.execute(interaction, client),
  );

  return client;
}

async function start() {
  if (!config.token) {
    throw new Error("DISCORD_TOKEN nao foi encontrado no .env.");
  }

  const client = createClient();
  registerShutdownHandlers(client);
  await client.login(config.token);
  return client;
}

module.exports = {
  createClient,
  start,
};
