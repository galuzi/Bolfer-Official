const fs = require("node:fs");
const path = require("node:path");
const { resolveRuntimePath } = require("../utils/runtimePaths");

const storePath = resolveRuntimePath("data", "polls.json");

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

function getPoll(pollId) {
  const store = readStore();
  return store[pollId] || null;
}

function setPoll(pollId, poll) {
  const store = readStore();
  store[pollId] = {
    ...poll,
    updatedAt: new Date().toISOString(),
  };
  writeStore(store);
}

function findPollByMessageId(messageId) {
  const store = readStore();
  return (
    Object.values(store).find((poll) => poll?.messageId === messageId) || null
  );
}

module.exports = {
  findPollByMessageId,
  getPoll,
  setPoll,
};
