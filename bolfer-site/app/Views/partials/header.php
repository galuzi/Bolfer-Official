<!doctype html>
<html lang="pt-br">
<head>
  <?php
    $pageTitle = $metaTitle ?? ($title ?? 'Bolfer Official');
    $metaDescription = $metaDescription ?? 'Bolfer Official: comunidade, prêmios, eventos e apoio direto para fortalecer a experiência da galera.';
    $metaImage = $metaImage ?? '/assets/img/logo.webp';
    $metaImageUrl = str_starts_with($metaImage, 'http') ? $metaImage : url($metaImage);
    $currentRequestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $currentPath = parse_url($currentRequestUri, PHP_URL_PATH) ?? '/';
    $currentUrl = url($currentPath);
    $loggedUser = user_session();
    $gaMeasurementId = trim((string) env('GA4_MEASUREMENT_ID', ''));

    $privatePrefixes = ['/admin', '/usuario', '/verify-email', '/2fa', '/pedido'];
    $privatePaths = ['/login', '/register', '/pedido', '/success', '/pending', '/failure'];
    $shouldNoIndex = in_array($currentPath, $privatePaths, true);
    if (!$shouldNoIndex) {
        foreach ($privatePrefixes as $privatePrefix) {
            if (str_starts_with($currentPath, $privatePrefix)) {
                $shouldNoIndex = true;
                break;
            }
        }
    }

    $metaRobots = $metaRobots ?? ($shouldNoIndex ? 'noindex, nofollow' : 'index, follow');

    $schemaList = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Bolfer Official',
            'url' => url('/'),
            'logo' => $metaImageUrl,
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Bolfer Official',
            'url' => url('/'),
        ],
    ];

    $normalizeSchema = static function (array $schemaData): array {
        $isList = array_keys($schemaData) === range(0, count($schemaData) - 1);
        if (!$isList && isset($schemaData['@type'])) {
            return [$schemaData];
        }

        $normalized = [];
        foreach ($schemaData as $schemaItem) {
            if (is_array($schemaItem) && isset($schemaItem['@type'])) {
                $normalized[] = $schemaItem;
            }
        }

        return $normalized;
    };

    if (isset($schemaData) && is_array($schemaData)) {
        $schemaList = array_merge($schemaList, $normalizeSchema($schemaData));
    }
  ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle); ?></title>
  <meta name="description" content="<?= e($metaDescription); ?>">
  <meta name="robots" content="<?= e($metaRobots); ?>">
  <meta property="og:title" content="<?= e($pageTitle); ?>">
  <meta property="og:description" content="<?= e($metaDescription); ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Bolfer Official">
  <meta property="og:image" content="<?= e($metaImageUrl); ?>">
  <?php if (!empty($currentUrl)) : ?>
    <meta property="og:url" content="<?= e($currentUrl); ?>">
    <link rel="canonical" href="<?= e($currentUrl); ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#0f0d0b">
  <meta name="csrf-token" content="<?= e(csrf_token()); ?>">
  <link rel="icon" href="/assets/img/LOGO.ico" type="image/x-icon">
  <link rel="shortcut icon" href="/assets/img/LOGO.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php if ($gaMeasurementId !== '') : ?>
    <link rel="preconnect" href="https://www.googletagmanager.com">
    <link rel="preconnect" href="https://www.google-analytics.com">
  <?php endif; ?>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Open+Sans:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" integrity="sha512-b7R/Zl/4b+8fUQ7C0rx4M6xK7F8bVsgcuyo6edSxE2xe50Tzw9uQWGWpZJYG1ChcxrFAuo0xO+ogzAm8h1Hnkg==" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="/assets/styles.css">

  <?php foreach ($schemaList as $schemaItem) : ?>
    <script type="application/ld+json"><?= json_encode($schemaItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <?php endforeach; ?>

  <?php if ($gaMeasurementId !== '') : ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaMeasurementId); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', <?= json_encode($gaMeasurementId); ?>, {
        page_path: <?= json_encode($currentPath); ?>,
        page_title: <?= json_encode($pageTitle); ?>,
        anonymize_ip: true
      });

      window.bolferTrack = function(eventName, params, callback) {
        if (typeof window.gtag !== 'function' || !eventName) {
          if (typeof callback === 'function') {
            callback();
          }
          return false;
        }

        const payload = Object.assign({}, params || {});
        if (typeof callback === 'function') {
          payload.event_callback = callback;
          payload.event_timeout = 1200;
        }

        window.gtag('event', eventName, payload);
        return true;
      };

      document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-ga-event]');
        if (!target || typeof window.bolferTrack !== 'function') {
          return;
        }

        const eventName = target.getAttribute('data-ga-event');
        if (!eventName) {
          return;
        }

        const payload = {};
        if (target.dataset.gaLabel) {
          payload.label = target.dataset.gaLabel;
        }
        if (target.dataset.gaLocation) {
          payload.location = target.dataset.gaLocation;
        }
        if (target.dataset.gaContentType) {
          payload.content_type = target.dataset.gaContentType;
        }
        if (target.dataset.gaItemId) {
          payload.item_id = target.dataset.gaItemId;
        }
        if (target.dataset.gaItemName) {
          payload.item_name = target.dataset.gaItemName;
        }

        window.bolferTrack(eventName, payload);
      });
    </script>
  <?php endif; ?>
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <div class="brand-text">
          <span class="brand-title">BOLFER</span>
          <span class="brand-sub">Official</span>
        </div>
      </div>
      <nav class="nav">
        <a class="nav-link" href="/">Home</a>
        <span class="nav-sep">|</span>
        <a class="nav-link" href="/produtos">Produtos</a>
        <span class="nav-sep">|</span>
        <a class="nav-link" href="/#contato">Suporte</a>
        <?php if ($loggedUser) : ?>
          <span class="nav-sep">|</span>
          <a class="nav-link" href="/usuario">Minha conta</a>
          <span class="nav-sep">|</span>
          <a class="nav-link" href="/usuario/mercado">Mercado</a>
          <span class="nav-sep">|</span>
          <span class="nav-user">Ol&aacute;, <strong class="nav-user-name"><?= e($loggedUser['username'] ?? ''); ?></strong></span>
          <span class="nav-sep">|</span>
          <form method="post" action="/logout" class="nav-logout">
            <?= csrf_field(); ?>
            <button class="nav-logout-btn" type="submit">Sair</button>
          </form>
        <?php else : ?>
          <span class="nav-sep">|</span>
          <a class="nav-link" href="/doacoes">Doa&ccedil;&atilde;o</a>
          <span class="nav-sep">|</span>
          <a class="nav-link" href="/login">Login</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main>
