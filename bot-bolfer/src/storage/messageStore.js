const fs = require("node:fs");
const path = require("node:path");
const { resolveRuntimePath } = require("../utils/runtimePaths");

const storePath = resolveRuntimePath("data", "messages.json");

function ensureStoreFile() {
  const directory = path.dirname(storePath);

  if (!fs.existsSync(directory)) {
    fs.mkdirSync(directory, { recursive: true });
  }

  if (!fs.existsSync(storePath)) {
    fs.writeFileSync(storePath, "{}\n", "utf8");
  }
}

function readStore() {
  ensureStoreFile();

  try {
    const contents = fs.readFileSync(storePath, "utf8");
    return JSON.parse(contents || "{}");
  } catch {
    return {};
  }
}

function writeStore(data) {
  ensureStoreFile();
  fs.writeFileSync(storePath, `${JSON.stringify(data, null, 2)}\n`, "utf8");
}

function getManagedMessage(key) {
  const store = readStore();
  return store[key] || null;
}

function setManagedMessage(key, value) {
  const store = readStore();
  store[key] = {
    ...value,
    updatedAt: new Date().toISOString(),
  };
  writeStore(store);
}

module.exports = {
  getManagedMessage,
  setManagedMessage,
};
