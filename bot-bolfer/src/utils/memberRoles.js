function memberHasRole(interaction, roleId) {
  if (!roleId) {
    return false;
  }

  const roles = interaction.member?.roles;

  if (!roles) {
    return false;
  }

  if (roles.cache?.has?.(roleId)) {
    return true;
  }

  if (Array.isArray(roles)) {
    return roles.includes(roleId);
  }

  if (Array.isArray(roles._roles)) {
    return roles._roles.includes(roleId);
  }

  return false;
}

module.exports = {
  memberHasRole,
};
