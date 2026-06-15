const path = require("node:path");

function getRuntimeBaseDir() {
  if (process.pkg) {
    return path.dirname(process.execPath);
  }

  return path.resolve(__dirname, "..", "..");
}

function resolveRuntimePath(...segments) {
  return path.resolve(getRuntimeBaseDir(), ...segments);
}

module.exports = {
  getRuntimeBaseDir,
  resolveRuntimePath,
};
