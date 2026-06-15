function formatDescriptionText(value) {
  if (typeof value !== "string") {
    return value;
  }

  return value
    .replace(/\r\n/g, "\n")
    .replace(/\\n/g, "\n")
    .split("\n")
    .map((line) => line.trimEnd())
    .join("\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

module.exports = {
  formatDescriptionText,
};
