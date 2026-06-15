const { start } = require("./src/index");

if (require.main === module) {
  start().catch((error) => {
    console.error("[bot] Falha ao iniciar o bot:", error);
    process.exit(1);
  });
}

module.exports = { start };
