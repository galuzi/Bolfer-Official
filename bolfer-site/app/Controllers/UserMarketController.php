<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\OrderLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserInventoryRepository;
use App\Repositories\UserMarketListingRepository;
use App\Repositories\UserMarketLogRepository;
use App\Repositories\UserMarketRepository;
use App\Repositories\UserMarketTopupRepository;
use App\Services\OrderPendingNotificationService;
use App\Services\PaymentService;
use App\Services\UserMarketService;
use App\Support\RateLimiter;
use App\Support\View;

final class UserMarketController
{
    public function index(): void
    {
        require_user();

        $user = user_session() ?? [];
        $settings = $this->marketSettings();

        View::render('user/market', [
            'user' => $user,
            ...$settings,
            ...$this->buildMarketPayload((int) ($user['id'] ?? 0), $settings),
        ]);
    }

    public function browse(): void
    {
        require_user();

        $user = user_session() ?? [];
        $settings = $this->marketSettings();
        $marketRepository = new UserMarketRepository();
        $listingRepository = new UserMarketListingRepository();
        $filters = $this->marketBrowseFilters();

        View::render('user/market_buy', [
            'user' => $user,
            ...$settings,
            'marketCoins' => $marketRepository->getBalance((int) ($user['id'] ?? 0)),
            'marketKeys' => (new UserInventoryRepository())->getAvailableKeyCount((int) ($user['id'] ?? 0)),
            'inventoryTypeOptions' => UserInventoryRepository::typeOptions(),
            'marketFilterTypeOptions' => UserInventoryRepository::marketTypeOptions(),
            'marketListings' => $listingRepository->listActive((int) ($user['id'] ?? 0), $filters),
            'marketBrowseFilters' => $filters,
        ]);
    }

    public function history(): void
    {
        require_user();

        $user = user_session() ?? [];
        $settings = $this->marketSettings();
        $marketRepository = new UserMarketRepository();
        $topupRepository = new UserMarketTopupRepository();

        View::render('user/market_history', [
            'user' => $user,
            ...$settings,
            'marketCoins' => $marketRepository->getBalance((int) ($user['id'] ?? 0)),
            'marketTransactions' => $marketRepository->listRecentByUserId((int) ($user['id'] ?? 0), 40),
            'marketTopups' => $topupRepository->listRecentByUserId((int) ($user['id'] ?? 0), 12),
        ]);
    }

    public function buyCoins(): void
    {
        require_user();

        $user = user_session() ?? [];
        $this->enforceWriteRateLimit((int) ($user['id'] ?? 0), 'topup');
        $settings = $this->marketSettings();
        $amountRaw = trim((string) ($_POST['amount_brl'] ?? ''));
        $amountRaw = str_replace(',', '.', $amountRaw);
        $amountBrl = (float) $amountRaw;

        if ($amountBrl < (float) $settings['marketTopupMinBrl'] || $amountBrl > (float) $settings['marketTopupMaxBrl']) {
            flash_set(
                'error',
                'Escolha um valor entre R$ ' . number_format((float) $settings['marketTopupMinBrl'], 0, ',', '.')
                . ' e R$ ' . number_format((float) $settings['marketTopupMaxBrl'], 0, ',', '.') . ' para comprar coins.'
            );
            redirect('/usuario/mercado');
        }

        $coinsAmount = max(1, (int) floor($amountBrl * (float) $settings['marketCoinRate']));
        $product = $this->ensureCoinProduct();

        $orderRepo = new OrderRepository();
        $order = $orderRepo->create([
            'product_id' => (int) $product['id'],
            'user_id' => (int) ($user['id'] ?? 0),
            'unit_price_snapshot' => $amountBrl,
            'quantity' => 1,
            'total_amount_snapshot' => $amountBrl,
            'status' => 'pending_payment',
            'contact_channel' => null,
            'contact_value' => null,
            'in_game_nick' => 'MARKET_COINS',
            'in_game_server' => 'Bolfer Market',
            'delivery_notes' => 'MARKET_TOPUP',
        ]);

        $topupId = (new UserMarketTopupRepository())->create([
            'user_id' => (int) ($user['id'] ?? 0),
            'order_id' => (int) ($order['id'] ?? 0),
            'amount_brl' => $amountBrl,
            'coins_amount' => $coinsAmount,
            'status' => 'pending',
        ]);
        (new UserMarketLogRepository())->create([
            'event_type' => 'topup_created',
            'actor_user_id' => (int) ($user['id'] ?? 0),
            'target_user_id' => (int) ($user['id'] ?? 0),
            'topup_id' => $topupId,
            'order_id' => (int) ($order['id'] ?? 0),
            'coins_amount' => $coinsAmount,
            'amount_brl' => $amountBrl,
            'note' => 'Recarga criada e enviada para pagamento.',
        ]);

        (new OrderLogRepository())->create([
            'order_id' => $order['id'],
            'admin_id' => null,
            'action' => 'status_change',
            'message' => 'Recarga de coins criada e aguardando pagamento.',
        ]);

        try {
            $pref = (new PaymentService())->createMarketTopupPreference($order, $amountBrl, $coinsAmount);
            $env = env('MP_ENV', 'production');
            $redirectUrl = ($env === 'sandbox' && !empty($pref['sandbox_init_point']))
                ? $pref['sandbox_init_point']
                : $pref['init_point'];

            if (!empty($user['email'])) {
                $mailResult = (new OrderPendingNotificationService())->deliver($order, $product, $user);
                (new OrderLogRepository())->create([
                    'order_id' => $order['id'],
                    'admin_id' => null,
                    'action' => $mailResult['ok'] ? 'pending_email_sent' : 'pending_email_failed',
                    'message' => $mailResult['ok']
                        ? 'E-mail de recarga pendente enviado para o usuario.'
                        : 'Recarga salva, mas o e-mail de recarga pendente nao foi enviado.',
                ]);
            }

            redirect($redirectUrl);
        } catch (\Throwable $e) {
            flash_set('error', 'Erro ao iniciar pagamento das coins. Tente novamente em alguns instantes.');
            redirect('/usuario/mercado');
        }
    }

    public function createListing(): void
    {
        require_user();

        $user = user_session() ?? [];
        $this->enforceWriteRateLimit((int) ($user['id'] ?? 0), 'create-listing');
        $settings = $this->marketSettings();
        $result = (new UserMarketService())->createListing(
            (int) ($user['id'] ?? 0),
            (int) ($_POST['inventory_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 1),
            (int) ($_POST['price_coins'] ?? 0),
            (int) $settings['marketListingMinPrice']
        );

        flash_set($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'Nao foi possivel criar a oferta.'));
        redirect('/usuario/mercado');
    }

    public function buyListing(): void
    {
        require_user();

        $user = user_session() ?? [];
        $this->enforceWriteRateLimit((int) ($user['id'] ?? 0), 'buy-listing');
        $result = (new UserMarketService())->buyListing(
            (int) ($user['id'] ?? 0),
            (int) ($_POST['listing_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 1)
        );

        flash_set($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'Nao foi possivel comprar a oferta.'));
        redirect($this->marketReturnPath($_POST['return_to'] ?? '/usuario/mercado/comprar'));
    }

    public function cancelListing(): void
    {
        require_user();

        $user = user_session() ?? [];
        $this->enforceWriteRateLimit((int) ($user['id'] ?? 0), 'cancel-listing');
        $result = (new UserMarketService())->cancelListing(
            (int) ($user['id'] ?? 0),
            (int) ($_POST['listing_id'] ?? 0)
        );

        flash_set($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'Nao foi possivel cancelar a oferta.'));
        redirect('/usuario/mercado');
    }

    private function buildMarketPayload(int $userId, array $settings): array
    {
        $inventoryRepository = new UserInventoryRepository();
        $marketRepository = new UserMarketRepository();
        $listingRepository = new UserMarketListingRepository();
        $topupRepository = new UserMarketTopupRepository();

        $topupSuggestions = [
            (float) $settings['marketTopupMinBrl'],
            max((float) $settings['marketTopupMinBrl'], 25.0),
            max((float) $settings['marketTopupMinBrl'], 50.0),
        ];
        $topupSuggestions = array_values(array_unique(array_map(static fn(float $value): float => round($value, 2), $topupSuggestions)));

        return [
            'marketCoins' => $marketRepository->getBalance($userId),
            'marketKeys' => $inventoryRepository->getAvailableKeyCount($userId),
            'sellableInventoryItems' => $inventoryRepository->listSellableByUserId($userId),
            'myMarketListings' => $listingRepository->listBySeller($userId),
            'marketTransactions' => $marketRepository->listRecentByUserId($userId, 10),
            'marketTopups' => $topupRepository->listRecentByUserId($userId, 5),
            'inventoryTypeOptions' => UserInventoryRepository::typeOptions(),
            'marketTopupSuggestions' => $topupSuggestions,
        ];
    }

    private function marketSettings(): array
    {
        $settingsRepo = new SettingsRepository();
        $listingMinPrice = max(100, (int) ($settingsRepo->get('market_listing_min_price', '100') ?? '100'));
        $coinRate = max(1.0, (float) ($settingsRepo->get('market_coin_rate', '10') ?? '10'));
        $topupMin = min(50.0, max(5.0, (float) ($settingsRepo->get('market_topup_min_brl', '10') ?? '10')));
        $topupMax = min(50.0, max($topupMin, (float) ($settingsRepo->get('market_topup_max_brl', '50') ?? '50')));

        return [
            'marketListingMinPrice' => $listingMinPrice,
            'marketCoinRate' => $coinRate,
            'marketTopupMinBrl' => $topupMin,
            'marketTopupMaxBrl' => $topupMax,
        ];
    }

    private function marketBrowseFilters(): array
    {
        $allowedSorts = ['recent', 'price_asc', 'price_desc', 'unlock_asc'];
        $allowedTypes = array_keys(UserInventoryRepository::marketTypeOptions());

        $itemType = trim((string) ($_GET['type'] ?? ''));
        if (!in_array($itemType, $allowedTypes, true)) {
            $itemType = '';
        }

        $sort = trim((string) ($_GET['sort'] ?? 'recent'));
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'recent';
        }

        $priceMax = (int) ($_GET['price_max'] ?? 0);

        return [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'item_type' => $itemType,
            'price_max' => $priceMax > 0 ? $priceMax : '',
            'sort' => $sort,
        ];
    }

    private function marketReturnPath(mixed $returnTo): string
    {
        $path = trim((string) $returnTo);
        $allowed = [
            '/usuario/mercado',
            '/usuario/mercado/comprar',
        ];

        return in_array($path, $allowed, true) ? $path : '/usuario/mercado/comprar';
    }

    private function ensureCoinProduct(): array
    {
        $categoryRepo = new CategoryRepository();
        $productRepo = new ProductRepository();

        $category = $categoryRepo->findBySlug('mercado-interno');
        if (!$category) {
            $categoryRepo->create([
                'name' => 'Mercado Interno',
                'slug' => 'mercado-interno',
                'is_active' => 0,
                'sort_order' => 998,
            ]);
            $category = $categoryRepo->findBySlug('mercado-interno');
        }

        $product = $productRepo->findBySlugOrId('bolfer-market-coins');
        if (!$product && $category) {
            $productRepo->create([
                'category_id' => (int) $category['id'],
                'name' => 'Bolfer Market Coins',
                'slug' => 'bolfer-market-coins',
                'unit_price' => 1,
                'stock' => null,
                'server_label' => 'Bolfer Market',
                'delivery_eta' => 'Instantaneo',
                'delivery_method' => 'Coins',
                'description' => 'Compra de coins do mercado interno.',
                'notes' => null,
                'is_active' => 0,
            ]);
            $product = $productRepo->findBySlugOrId('bolfer-market-coins');
        }

        if (!$product) {
            throw new \RuntimeException('Produto de coins nao encontrado.');
        }

        return $product;
    }

    private function enforceWriteRateLimit(int $userId, string $action): void
    {
        $userId = max(1, $userId);
        $limiter = new RateLimiter();
        $key = 'market-write:' . $action . ':' . hash('sha256', (string) $userId);
        $retryAfter = (int) ($limiter->isBlocked($key) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas nessa area. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/usuario/mercado');
        }

        $limiter->hit($key, 20, 60, 120);
    }
}
