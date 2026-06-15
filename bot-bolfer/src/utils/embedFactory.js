const {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  EmbedBuilder,
} = require("discord.js");
const config = require("../config");

function hasPlaceholder(value) {
  if (!value) {
    return false;
  }

  return /SEU_SERVER_ID|ID_CANAL|SEU_CANAL|YOUR_|EXAMPLE/i.test(value);
}

function normalizeUrl(value) {
  if (!value || hasPlaceholder(value)) {
    return null;
  }

  try {
    const parsed = new URL(value);
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
      return null;
    }

    return parsed.toString();
  } catch {
    return null;
  }
}

function normalizeColor(value) {
  if (typeof value === "number") {
    return value;
  }

  if (typeof value === "string" && value.trim()) {
    return value.trim();
  }

  return config.branding.primaryColor;
}

function buildStyledEmbed({
  eyebrow,
  title,
  description,
  color,
  fields = [],
  footer,
  image,
  thumbnail,
}) {
  const embed = new EmbedBuilder()
    .setColor(normalizeColor(color))
    .setFooter({ text: footer || config.branding.footerText })
    .setTimestamp();

  if (eyebrow) {
    embed.setAuthor({ name: eyebrow });
  } else if (config.branding.serverName) {
    embed.setAuthor({ name: config.branding.serverName });
  }

  if (title) {
    embed.setTitle(title);
  }

  if (description) {
    embed.setDescription(description);
  }

  if (fields.length) {
    embed.addFields(fields);
  }

  const safeThumbnail = normalizeUrl(thumbnail);
  if (safeThumbnail) {
    embed.setThumbnail(safeThumbnail);
  } else if (config.branding.thumbnailUrl) {
    embed.setThumbnail(config.branding.thumbnailUrl);
  }

  const safeImage = normalizeUrl(image);
  if (safeImage) {
    embed.setImage(safeImage);
  }

  return embed;
}

function createLinkButton(label, url) {
  const safeUrl = normalizeUrl(url);

  if (!label || !safeUrl) {
    return null;
  }

  return new ButtonBuilder()
    .setLabel(label)
    .setStyle(ButtonStyle.Link)
    .setURL(safeUrl);
}

function buildLinkRow(buttons) {
  const validButtons = buttons.filter(Boolean);

  if (!validButtons.length) {
    return [];
  }

  return [new ActionRowBuilder().addComponents(validButtons)];
}

module.exports = {
  buildLinkRow,
  buildStyledEmbed,
  createLinkButton,
  normalizeUrl,
};
