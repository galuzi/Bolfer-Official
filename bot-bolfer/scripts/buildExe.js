const fs = require("node:fs");
const path = require("node:path");
const { spawnSync } = require("node:child_process");
const dotenv = require("dotenv");

const projectRoot = path.resolve(__dirname, "..");
const envPath = path.resolve(projectRoot, ".env");
const bundledEnvPath = path.resolve(
  projectRoot,
  "src",
  "generated",
  "bundledEnv.js",
);

function writePlaceholder() {
  fs.writeFileSync(bundledEnvPath, "module.exports = {};\n", "utf8");
}

function writeBundledEnv() {
  if (!fs.existsSync(envPath)) {
    throw new Error("Arquivo .env nao encontrado na raiz do projeto.");
  }

  const parsedEnv = dotenv.parse(fs.readFileSync(envPath, "utf8"));
  const contents = `module.exports = ${JSON.stringify(parsedEnv, null, 2)};\n`;
  fs.writeFileSync(bundledEnvPath, contents, "utf8");
}

function runPkgBuild() {
  const pkgBinary = path.resolve(
    projectRoot,
    "node_modules",
    ".bin",
    process.platform === "win32" ? "pkg.cmd" : "pkg",
  );
  const command =
    process.platform === "win32"
      ? `"${pkgBinary}" . --targets node18-win-x64 --output dist/BOLFER.exe`
      : `"${pkgBinary}" . --targets node18-win-x64 --output dist/BOLFER.exe`;
  const result = spawnSync(command, {
    cwd: projectRoot,
    stdio: "inherit",
    shell: true,
  });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    throw new Error(
      `Falha ao gerar o executavel (codigo ${result.status ?? "desconhecido"}).`,
    );
  }
}

try {
  writeBundledEnv();
  runPkgBuild();
  console.log("[build:exe] Executavel gerado com configuracao embutida.");
} finally {
  writePlaceholder();
}
