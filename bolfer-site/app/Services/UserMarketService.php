<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserInventoryRepository;
use App\Repositories\UserMarketListingRepository;
use App\Repositories\UserMarketLogRepository;
use App\Repositories\UserMarketRepository;
use App\Repositories\UserMarketTopupRepository;
use App\Support\Database;
use Throwable;

final class UserMarketService
{
    private UserInventoryRepository $inventoryRepository;
    private UserMarketRepository $marketRepository;
    private UserMarketListingRepository $listingRepository;
    private UserMarketLogRepository $marketLogRepository;
    private UserMarketTopupRepository $topupRepository;

    public function __construct()
    {
        $this->inventoryRepository = new UserInventoryRepository();
        $this->marketRepository = new UserMarketRepository();
        $this->listingRepository = new UserMarketListingRepository();
        $this->marketLogRepository = new UserMarketLogRepository();
        $this->topupRepository = new UserMarketTopupRepository();
    }

    public function adjustCoins(int $userId, int $amount, string $note, ?int $adminId = null): array
    {
        if ($amount === 0) {
            return [
                'ok' => false,
                'message' => 'Informe uma quantidade de coins diferente de zero.',
            ];
        }

        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $wallet = $this->marketRepository->lockUserBalance($userId);
            if (!$wallet) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Usuário não encontrado para ajuste de coins.',
                ];
            }

            $currentBalance = (int) ($wallet['market_coins'] ?? 0);
            $nextBalance = $currentBalance + $amount;

            if ($nextBalance < 0) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'O usuário não tem coins suficientes para esse desconto.',
                ];
            }

            $this->marketRepository->updateBalance($userId, $nextBalance);
            $this->recordTransaction(
                $userId,
                $amount,
                $amount > 0 ? 'admin_credit' : 'admin_debit',
                $note,
                $adminId
            );
            $this->recordMarketLog([
                'event_type' => 'admin_coin_adjust',
                'target_user_id' => $userId,
                'admin_id' => $adminId,
                'coins_amount' => $amount,
                'note' => $note,
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'message' => $amount > 0 ? 'Coins adicionadas com sucesso.' : 'Coins removidas com sucesso.',
                'balance' => $nextBalance,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Não foi possível ajustar as coins agora.',
            ];
        }
    }

    public function unlockInventoryItem(int $userId, int $inventoryId): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $wallet = $this->marketRepository->lockUserBalance($userId);
            if (!$wallet) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Usuário não encontrado.',
                ];
            }

            $inventoryItem = $this->inventoryRepository->findByUserAndId($userId, $inventoryId, true);
            if (!$inventoryItem) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Item não encontrado no inventário.',
                ];
            }

            $unlockCost = (int) ($inventoryItem['unlock_cost'] ?? 0);
            $lockedContent = trim((string) ($inventoryItem['locked_content'] ?? ''));
            $isUnlocked = !empty($inventoryItem['is_unlocked']);
            $itemType = (string) ($inventoryItem['item_type'] ?? 'outro');
            $usesKeysToUnlock = UserInventoryRepository::usesKeyUnlock($itemType);

            if ($unlockCost <= 0 || $lockedContent === '') {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Esse item não precisa de desbloqueio.',
                ];
            }

            if ($isUnlocked) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Esse item já está desbloqueado.',
                ];
            }

            $currentBalance = (int) ($wallet['market_coins'] ?? 0);
            if ($usesKeysToUnlock) {
                if (!$this->inventoryRepository->consumeKeys($userId, $unlockCost)) {
                    $pdo->rollBack();
                    return [
                        'ok' => false,
                        'message' => 'Você não tem chaves suficientes para desbloquear esse jogo.',
                    ];
                }

                $this->inventoryRepository->markUnlocked($userId, $inventoryId);
                $this->recordMarketLog([
                    'event_type' => 'inventory_unlocked',
                    'actor_user_id' => $userId,
                    'target_user_id' => $userId,
                    'inventory_id' => $inventoryId,
                    'item_name_snapshot' => (string) ($inventoryItem['item_name'] ?? 'Item'),
                    'item_type_snapshot' => $itemType,
                    'quantity' => 1,
                    'unlock_cost' => $unlockCost,
                    'item_lock_state' => 'open',
                    'note' => 'Item desbloqueado com chaves.',
                ]);
                $pdo->commit();

                return [
                    'ok' => true,
                    'message' => 'Jogo desbloqueado com sucesso usando as suas chaves.',
                ];
            }

            if ($currentBalance < $unlockCost) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Você não tem coins suficientes para desbloquear esse item.',
                ];
            }

            $nextBalance = $currentBalance - $unlockCost;
            $this->marketRepository->updateBalance($userId, $nextBalance);
            $this->inventoryRepository->markUnlocked($userId, $inventoryId);
            $this->recordTransaction(
                $userId,
                -$unlockCost,
                'inventory_unlock',
                'Desbloqueio do item ' . (string) ($inventoryItem['item_name'] ?? 'inventário'),
                null,
                $inventoryId
            );
            $this->recordMarketLog([
                'event_type' => 'inventory_unlocked',
                'actor_user_id' => $userId,
                'target_user_id' => $userId,
                'inventory_id' => $inventoryId,
                'item_name_snapshot' => (string) ($inventoryItem['item_name'] ?? 'Item'),
                'item_type_snapshot' => $itemType,
                'quantity' => 1,
                'coins_amount' => -$unlockCost,
                'unlock_cost' => $unlockCost,
                'item_lock_state' => 'open',
                'note' => 'Item desbloqueado com coins.',
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'message' => 'Item desbloqueado com sucesso.',
                'balance' => $nextBalance,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Não foi possível desbloquear o item agora.',
            ];
        }
    }

    public function creditPaidTopup(int $orderId): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $topup = $this->topupRepository->findByOrderId($orderId, true);
            if (!$topup) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Recarga nao encontrada.',
                ];
            }

            if ((string) ($topup['status'] ?? '') === 'paid') {
                $pdo->rollBack();
                return [
                    'ok' => true,
                    'message' => 'Recarga ja creditada.',
                ];
            }

            $wallet = $this->marketRepository->lockUserBalance((int) $topup['user_id']);
            if (!$wallet) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Usuário da recarga não encontrado.',
                ];
            }

            $coinsAmount = (int) ($topup['coins_amount'] ?? 0);
            $nextBalance = (int) ($wallet['market_coins'] ?? 0) + $coinsAmount;

            $this->marketRepository->updateBalance((int) $topup['user_id'], $nextBalance);
            $this->topupRepository->markPaid((int) $topup['id']);
            $this->recordTransaction(
                (int) $topup['user_id'],
                $coinsAmount,
                'topup_credit',
                'Compra de coins aprovada',
                null
            );
            $this->recordMarketLog([
                'event_type' => 'topup_paid',
                'target_user_id' => (int) $topup['user_id'],
                'topup_id' => (int) ($topup['id'] ?? 0),
                'order_id' => $orderId,
                'coins_amount' => $coinsAmount,
                'amount_brl' => (float) ($topup['amount_brl'] ?? 0),
                'note' => 'Recarga aprovada e coins creditadas.',
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'message' => 'Coins creditadas com sucesso.',
                'balance' => $nextBalance,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Não foi possível creditar a recarga.',
            ];
        }
    }

    public function createListing(int $sellerUserId, int $inventoryId, int $quantity, int $priceCoins, int $minimumPrice): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $inventoryItem = $this->inventoryRepository->findByUserAndId($sellerUserId, $inventoryId, true);
            if (!$inventoryItem) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Item não encontrado no seu inventário.',
                ];
            }

            $quantity = max(1, $quantity);
            $availableQuantity = (int) ($inventoryItem['quantity'] ?? 0);
            if ($availableQuantity < $quantity) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Quantidade indisponível para listar.',
                ];
            }

            if ($priceCoins < $minimumPrice) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'O valor mínimo para venda neste mercado é de ' . $minimumPrice . ' coins.',
                ];
            }

            $unlockCost = (int) ($inventoryItem['unlock_cost'] ?? 0);
            $lockedContent = trim((string) ($inventoryItem['locked_content'] ?? ''));
            $isUnlocked = !empty($inventoryItem['is_unlocked']);
            $itemType = (string) ($inventoryItem['item_type'] ?? 'outro');
            $isKeyListing = UserInventoryRepository::isKeyType($itemType);
            $isLockedProtectedItem = $unlockCost > 0 && $lockedContent !== '';

            if (!$isKeyListing && !$isLockedProtectedItem) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Só jogos bloqueados ou chaves disponíveis podem ir para o mercado.',
                ];
            }

            if (!$isKeyListing && $isUnlocked) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Esse item já foi desbloqueado e não pode mais ser vendido no mercado.',
                ];
            }

            if (!$isKeyListing && $quantity !== 1) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Itens bloqueados só podem ser vendidos uma unidade por vez.',
                ];
            }

            $sourceInventoryId = $availableQuantity > $quantity ? $inventoryId : null;

            if ($availableQuantity === $quantity) {
                $this->inventoryRepository->deleteById($inventoryId, $sellerUserId);
            } else {
                $this->inventoryRepository->reduceQuantity($sellerUserId, $inventoryId, $quantity);
            }

            $listingId = $this->listingRepository->create([
                'seller_user_id' => $sellerUserId,
                'source_inventory_id' => $sourceInventoryId,
                'item_name' => (string) ($inventoryItem['item_name'] ?? 'Item'),
                'item_type' => (string) ($inventoryItem['item_type'] ?? 'outro'),
                'quantity' => $quantity,
                'description' => trim((string) ($inventoryItem['description'] ?? '')),
                'unlock_cost' => $unlockCost,
                'locked_content' => $lockedContent,
                'price_coins' => $priceCoins,
                'status' => 'active',
            ]);
            $this->recordMarketLog([
                'event_type' => 'listing_created',
                'actor_user_id' => $sellerUserId,
                'seller_user_id' => $sellerUserId,
                'target_user_id' => $sellerUserId,
                'listing_id' => $listingId,
                'item_name_snapshot' => (string) ($inventoryItem['item_name'] ?? 'Item'),
                'item_type_snapshot' => $itemType,
                'quantity' => $quantity,
                'price_coins' => $priceCoins,
                'unlock_cost' => $unlockCost,
                'item_lock_state' => $this->itemLockState($inventoryItem),
                'note' => 'Oferta publicada no mercado interno.',
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'message' => 'Item colocado a venda no mercado interno.',
                'listing_id' => $listingId,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Não foi possível criar a sua venda agora.',
            ];
        }
    }

    public function cancelListing(int $sellerUserId, int $listingId): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $listing = $this->listingRepository->findById($listingId, true);
            if (!$listing || (int) ($listing['seller_user_id'] ?? 0) !== $sellerUserId) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Venda não encontrada para cancelamento.',
                ];
            }

            if ((string) ($listing['status'] ?? '') !== 'active') {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Essa venda já não está ativa.',
                ];
            }

            $returnedItem = $this->inventoryRepository->addTransferredItem($sellerUserId, $listing);
            $this->listingRepository->markCancelled($listingId);
            $this->recordMarketLog([
                'event_type' => 'listing_cancelled',
                'actor_user_id' => $sellerUserId,
                'seller_user_id' => $sellerUserId,
                'target_user_id' => $sellerUserId,
                'listing_id' => $listingId,
                'inventory_id' => (int) ($returnedItem['inventory_id'] ?? 0),
                'item_name_snapshot' => (string) ($listing['item_name'] ?? 'Item'),
                'item_type_snapshot' => (string) ($listing['item_type'] ?? 'outro'),
                'quantity' => max(1, (int) ($listing['quantity'] ?? 1)),
                'price_coins' => (int) ($listing['price_coins'] ?? 0),
                'unlock_cost' => (int) ($listing['unlock_cost'] ?? 0),
                'item_lock_state' => $this->itemLockState($listing),
                'note' => 'Oferta cancelada e item devolvido ao inventário.',
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'message' => 'Venda cancelada e item devolvido ao seu inventário.',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Não foi possível cancelar a venda agora.',
            ];
        }
    }

    public function buyListing(int $buyerUserId, int $listingId, int $requestedQuantity = 1): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $listing = $this->listingRepository->findById($listingId, true);
            if (!$listing) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Oferta não encontrada.',
                ];
            }

            if ((string) ($listing['status'] ?? '') !== 'active') {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Essa oferta não está mais disponível.',
                ];
            }

            $sellerUserId = (int) ($listing['seller_user_id'] ?? 0);
            $listingType = (string) ($listing['item_type'] ?? 'outro');
            $isKeyListing = UserInventoryRepository::isKeyType($listingType);
            $availableQuantity = max(1, (int) ($listing['quantity'] ?? 1));
            $requestedQuantity = max(1, $requestedQuantity);

            if ($sellerUserId === $buyerUserId) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Você não pode comprar a própria oferta.',
                ];
            }

            if (!$isKeyListing) {
                $requestedQuantity = 1;
            }

            if ($requestedQuantity > $availableQuantity) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'A quantidade escolhida não está mais disponível nessa oferta.',
                ];
            }

            [$firstUserId, $secondUserId] = $sellerUserId < $buyerUserId
                ? [$sellerUserId, $buyerUserId]
                : [$buyerUserId, $sellerUserId];

            $firstWallet = $this->marketRepository->lockUserBalance($firstUserId);
            $secondWallet = $this->marketRepository->lockUserBalance($secondUserId);
            $wallets = [
                $firstUserId => $firstWallet,
                $secondUserId => $secondWallet,
            ];

            $buyerWallet = $wallets[$buyerUserId] ?? null;
            $sellerWallet = $wallets[$sellerUserId] ?? null;
            if (!$buyerWallet || !$sellerWallet) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Não foi possível validar as carteiras agora.',
                ];
            }

            $unitPriceCoins = (int) ($listing['price_coins'] ?? 0);
            $priceCoins = $isKeyListing ? ($unitPriceCoins * $requestedQuantity) : $unitPriceCoins;
            $buyerBalance = (int) ($buyerWallet['market_coins'] ?? 0);
            if ($buyerBalance < $priceCoins) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Você não tem coins suficientes para essa compra.',
                ];
            }

            $sellerBalance = (int) ($sellerWallet['market_coins'] ?? 0);
            $this->marketRepository->updateBalance($buyerUserId, $buyerBalance - $priceCoins);
            $this->marketRepository->updateBalance($sellerUserId, $sellerBalance + $priceCoins);

            $transferListing = $listing;
            $transferListing['quantity'] = $requestedQuantity;
            $transferredItem = $this->inventoryRepository->addTransferredItem($buyerUserId, $transferListing);

            $remainingQuantity = $availableQuantity - $requestedQuantity;
            if ($remainingQuantity > 0) {
                $this->listingRepository->updateQuantity($listingId, $remainingQuantity);
            } else {
                $this->listingRepository->markSold($listingId, $buyerUserId);
            }

            $itemName = (string) ($listing['item_name'] ?? 'item');
            $itemLabel = $isKeyListing && $requestedQuantity > 1
                ? ($requestedQuantity . 'x ' . $itemName)
                : $itemName;
            $this->recordTransaction($buyerUserId, -$priceCoins, 'market_purchase', 'Compra do item ' . $itemLabel, null);
            $this->recordTransaction($sellerUserId, $priceCoins, 'market_sale', 'Venda do item ' . $itemLabel, null);
            $this->recordMarketLog([
                'event_type' => 'listing_sold',
                'actor_user_id' => $buyerUserId,
                'seller_user_id' => $sellerUserId,
                'buyer_user_id' => $buyerUserId,
                'target_user_id' => $buyerUserId,
                'listing_id' => $listingId,
                'inventory_id' => (int) ($transferredItem['inventory_id'] ?? 0),
                'item_name_snapshot' => $itemName,
                'item_type_snapshot' => $listingType,
                'quantity' => $requestedQuantity,
                'price_coins' => $priceCoins,
                'coins_amount' => $priceCoins,
                'unlock_cost' => (int) ($listing['unlock_cost'] ?? 0),
                'item_lock_state' => $this->itemLockState($listing),
                'note' => 'Oferta comprada e transferida para o inventário do comprador.',
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'message' => $isKeyListing
                    ? 'Compra realizada e as chaves foram transferidas para o seu inventário.'
                    : 'Compra realizada e o item foi transferido para o seu inventário.',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Não foi possível concluir a compra agora.',
            ];
        }
    }

    private function recordTransaction(
        int $userId,
        int $amount,
        string $type,
        string $note,
        ?int $adminId = null,
        ?int $inventoryId = null
    ): void {
        $this->marketRepository->addTransaction([
            'user_id' => $userId,
            'amount' => $amount,
            'transaction_type' => $type,
            'note' => $note,
            'created_by_admin_id' => $adminId,
            'related_inventory_id' => $inventoryId,
        ]);
    }

    private function recordMarketLog(array $data): void
    {
        $this->marketLogRepository->create($data);
    }

    private function itemLockState(array $item): string
    {
        $unlockCost = (int) ($item['unlock_cost'] ?? 0);
        $lockedContent = trim((string) ($item['locked_content'] ?? ''));

        return $unlockCost > 0 && $lockedContent !== '' ? 'locked' : 'open';
    }
}
