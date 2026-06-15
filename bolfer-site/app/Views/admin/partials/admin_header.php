<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Admin'); ?></title>
  <meta name="csrf-token" content="<?= e(csrf_token()); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Open+Sans:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<?php
  $isAuthPage = ($bodyClass ?? '') === 'admin-login';
  $isFullAdmin = admin_is_full();
  $isFounderAdmin = admin_is_founder();
  $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
  $navActive = static function (string $path) use ($currentPath): string {
    if ($path === '/admin/dashboard') {
      return $currentPath === '/admin/dashboard' ? 'is-active' : '';
    }
    return str_starts_with($currentPath, $path) ? 'is-active' : '';
  };
?>
<body class="admin-panel <?= e($bodyClass ?? ''); ?>">
<?php if ($isAuthPage) : ?>
  <main class="admin-auth-main">
<?php else : ?>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-brand">
        <span class="admin-brand-title">Admin</span>
        <span class="admin-brand-sub">Painel</span>
      </div>
      <nav class="admin-nav">
        <a class="admin-nav-link <?= $navActive('/admin/dashboard'); ?>" href="/admin/dashboard">Dashboard</a>
        <a class="admin-nav-link <?= $navActive('/admin/categories'); ?>" href="/admin/categories">Categorias</a>
        <a class="admin-nav-link <?= $navActive('/admin/products'); ?>" href="/admin/products">Produtos</a>
        <a class="admin-nav-link <?= $navActive('/admin/orders'); ?>" href="/admin/orders">Pedidos</a>
        <a class="admin-nav-link <?= $navActive('/admin/users'); ?>" href="/admin/users">Usuarios</a>
        <?php if ($isFullAdmin) : ?>
          <a class="admin-nav-link <?= $navActive('/admin/logs'); ?>" href="/admin/logs">Logs</a>
          <a class="admin-nav-link <?= $navActive('/admin/settings'); ?>" href="/admin/settings">Config</a>
        <?php endif; ?>
        <?php if ($isFounderAdmin) : ?>
          <a class="admin-nav-link <?= $navActive('/admin/invites'); ?>" href="/admin/invites">Convites</a>
        <?php endif; ?>
      </nav>
      <?php if (admin_user()) : ?>
        <div class="admin-side-footer">
          <div class="admin-user">
            <span class="admin-user-label">Logado como</span>
            <strong class="admin-user-name"><?= e(admin_user()['username'] ?? 'Admin'); ?></strong>
          </div>
          <form method="post" action="/admin/logout">
            <?= csrf_field(); ?>
            <button class="admin-logout" type="submit">Sair</button>
          </form>
        </div>
      <?php endif; ?>
    </aside>
    <main class="admin-main">
<?php endif; ?>
