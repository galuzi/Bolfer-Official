<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\DiscordActivityService;
use App\Services\ProductAccountMediaService;
use App\Support\DesktopApiPresenter;

final class ProductsController
{
    public function index(): void
    {
        $this->requireFounder();

        $productRepository = new ProductRepository();
        $categoryRepository = new CategoryRepository();

        json_response([
            'ok' => true,
            'data' => [
                'products' => array_map(
                    static fn(array $product): array => DesktopApiPresenter::product($product),
                    $productRepository->all()
                ),
                'categories' => array_map(
                    static fn(array $category): array => DesktopApiPresenter::category($category),
                    $categoryRepository->all()
                ),
                'typeOptions' => [
                    ['value' => 'item', 'label' => 'Item'],
                    ['value' => 'conta', 'label' => 'Conta'],
                ],
                'policy' => [
                    'webpOnly' => true,
                    'maxImages' => 8,
                    'maxFileSizeMb' => 5,
                    'message' => 'Imagens de conta são opcionais, mas o desktop aceita apenas arquivos WEBP.',
                ],
                'defaults' => [
                    'description' => $this->defaultDescription(),
                    'notes' => $this->defaultNotes(),
                    'serverLabel' => 'LDMO Omegamon',
                    'deliveryEta' => '5min-1h',
                ],
            ],
        ]);
    }

    public function create(): void
    {
        $context = $this->requireFounder();
        $payload = request_data();
        $productRepository = new ProductRepository();
        $media = new ProductAccountMediaService();
        $normalized = $this->normalizePayload($payload);

        try {
            $accountImages = $normalized['productType'] === 'conta'
                ? $media->syncDesktop([], $normalized['newImages'], [])
                : [];
        } catch (\RuntimeException $exception) {
            json_response([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        try {
            $productRepository->create($this->repositoryPayload($normalized, $accountImages));
        } catch (\RuntimeException $exception) {
            json_response([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
        $created = $productRepository->findBySlugOrId($normalized['slug']);

        (new DiscordActivityService())->notify(
            $context['admin'] ?? [],
            'product_create',
            'products',
            'Novo produto criado no desktop',
            'A equipe publicou um novo item ou conta pelo app desktop.',
            [
                ['name' => 'Produto', 'value' => $normalized['name'], 'inline' => true],
                ['name' => 'Tipo', 'value' => ucfirst($normalized['productType']), 'inline' => true],
                ['name' => 'Preço', 'value' => 'R$ ' . number_format((float) $normalized['unitPrice'], 2, ',', '.'), 'inline' => true],
            ]
        );

        json_response([
            'ok' => true,
            'message' => 'Produto criado com sucesso.',
            'data' => [
                'product' => $created ? DesktopApiPresenter::product($created) : null,
            ],
        ]);
    }

    public function update(string $id): void
    {
        $context = $this->requireFounder();
        $productId = (int) $id;
        if ($productId <= 0) {
            $this->notFound();
        }

        $productRepository = new ProductRepository();
        $currentProduct = $productRepository->find($productId);
        if (!$currentProduct) {
            $this->notFound();
        }

        $payload = request_data();
        $normalized = $this->normalizePayload($payload, $currentProduct);
        $media = new ProductAccountMediaService();
        $currentImages = $media->decodeStoredImages($currentProduct['account_images'] ?? null);

        try {
            $accountImages = $normalized['productType'] === 'conta'
                ? $media->syncDesktop($currentImages, $normalized['newImages'], $normalized['removeAccountImages'])
                : [];
        } catch (\RuntimeException $exception) {
            json_response([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($normalized['productType'] !== 'conta' && $currentImages !== []) {
            $media->deleteAll($currentImages);
        }

        try {
            $productRepository->update($productId, $this->repositoryPayload($normalized, $accountImages));
        } catch (\RuntimeException $exception) {
            json_response([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
        $updated = $productRepository->find($productId) ?? $currentProduct;

        (new DiscordActivityService())->notify(
            $context['admin'] ?? [],
            'product_update',
            'products',
            'Produto atualizado no desktop',
            'Um produto recebeu ajuste de dados, preco ou entrega pelo app desktop.',
            [
                ['name' => 'Produto', 'value' => $normalized['name'], 'inline' => true],
                ['name' => 'Tipo', 'value' => ucfirst($normalized['productType']), 'inline' => true],
                ['name' => 'Preço', 'value' => 'R$ ' . number_format((float) $normalized['unitPrice'], 2, ',', '.'), 'inline' => true],
            ]
        );

        json_response([
            'ok' => true,
            'message' => 'Produto atualizado com sucesso.',
            'data' => [
                'product' => DesktopApiPresenter::product($updated),
            ],
        ]);
    }

    public function delete(string $id): void
    {
        $context = $this->requireFounder();
        $productId = (int) $id;
        if ($productId <= 0) {
            $this->notFound();
        }

        $productRepository = new ProductRepository();
        $currentProduct = $productRepository->find($productId);
        if (!$currentProduct) {
            $this->notFound();
        }

        $media = new ProductAccountMediaService();
        $media->deleteAll($media->decodeStoredImages($currentProduct['account_images'] ?? null));
        $productRepository->delete($productId);

        (new DiscordActivityService())->notify(
            $context['admin'] ?? [],
            'product_delete',
            'products',
            'Produto removido no desktop',
            'Um produto foi retirado do catálogo pelo app desktop.',
            [
                ['name' => 'Produto', 'value' => (string) ($currentProduct['name'] ?? 'Produto'), 'inline' => true],
                ['name' => 'Tipo', 'value' => ucfirst((string) ($currentProduct['product_type'] ?? 'item')), 'inline' => true],
                ['name' => 'Slug', 'value' => (string) ($currentProduct['slug'] ?? '-'), 'inline' => true],
            ]
        );

        json_response([
            'ok' => true,
            'message' => 'Produto removido com sucesso.',
        ]);
    }

    private function requireFounder(): array
    {
        $context = require_admin_api();
        $admin = $context['admin'] ?? [];

        if (\admin_role_level((string) ($admin['role'] ?? 'staff')) < 40) {
            json_response([
                'ok' => false,
                'message' => 'Sem permissão para gerenciar produtos neste desktop.',
            ], 403);
        }

        return $context;
    }

    private function normalizePayload(array $payload, ?array $currentProduct = null): array
    {
        $categoryId = (int) ($payload['categoryId'] ?? $payload['category_id'] ?? 0);
        if ($categoryId <= 0 || !$this->categoryExists($categoryId)) {
            json_response([
                'ok' => false,
                'message' => 'Selecione uma categoria válida para o produto.',
            ], 422);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            json_response([
                'ok' => false,
                'message' => 'Informe o nome do produto.',
            ], 422);
        }

        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        $productType = trim((string) ($payload['productType'] ?? $payload['product_type'] ?? 'item'));
        if (!in_array($productType, ['item', 'conta'], true)) {
            $productType = 'item';
        }

        $unitPrice = (float) ($payload['unitPrice'] ?? $payload['unit_price'] ?? 0);
        if ($unitPrice <= 0) {
            json_response([
                'ok' => false,
                'message' => 'Defina um preço maior que zero.',
            ], 422);
        }

        $stockRaw = $payload['stock'] ?? '';
        $stock = trim((string) $stockRaw);
        if ($stock !== '' && (!ctype_digit(ltrim($stock, '+')) || (int) $stock < 0)) {
            json_response([
                'ok' => false,
                'message' => 'O estoque deve ser vazio ou um número inteiro igual ou maior que zero.',
            ], 422);
        }

        $minimumQuantityRaw = trim((string) ($payload['minimumQuantity'] ?? $payload['minimum_quantity'] ?? '1'));
        if ($minimumQuantityRaw === '' || !ctype_digit(ltrim($minimumQuantityRaw, '+')) || (int) $minimumQuantityRaw < 1) {
            json_response([
                'ok' => false,
                'message' => 'Defina uma compra mÃ­nima com pelo menos 1 unidade.',
            ], 422);
        }

        $minimumQuantity = max(1, (int) $minimumQuantityRaw);
        if ($stock !== '' && (int) $stock > 0 && $minimumQuantity > (int) $stock) {
            json_response([
                'ok' => false,
                'message' => 'A compra mÃ­nima nÃ£o pode ser maior que o estoque atual do produto.',
            ], 422);
        }

        $productDescription = trim((string) ($payload['productDescription'] ?? $payload['product_description'] ?? ''));
        $accountInfo = trim((string) ($payload['accountInfo'] ?? $payload['account_info'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $serverLabel = trim((string) ($payload['serverLabel'] ?? $payload['server_label'] ?? 'LDMO Omegamon'));
        $deliveryEta = trim((string) ($payload['deliveryEta'] ?? $payload['delivery_eta'] ?? '5min-1h'));
        $deliveryMethod = trim((string) ($payload['deliveryMethod'] ?? $payload['delivery_method'] ?? ''));
        $isActive = !empty($payload['isActive']) || (string) ($payload['is_active'] ?? '0') === '1';
        $removeAccountImages = $payload['removeAccountImages'] ?? $payload['remove_account_images'] ?? [];
        $newImages = $payload['newImages'] ?? [];

        if ($description === '') {
            $description = $currentProduct ? (string) ($currentProduct['description'] ?? '') : $this->defaultDescription();
        }

        if ($notes === '') {
            $notes = $currentProduct ? (string) ($currentProduct['notes'] ?? '') : $this->defaultNotes();
        }

        if ($description === '') {
            $description = $this->defaultDescription();
        }

        if ($notes === '') {
            $notes = $this->defaultNotes();
        }

        return [
            'categoryId' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'unitPrice' => $unitPrice,
            'stock' => $stock,
            'minimumQuantity' => $minimumQuantity,
            'serverLabel' => $serverLabel !== '' ? $serverLabel : 'LDMO Omegamon',
            'deliveryEta' => $deliveryEta !== '' ? $deliveryEta : '5min-1h',
            'deliveryMethod' => $deliveryMethod !== '' ? $deliveryMethod : null,
            'productType' => $productType,
            'productDescription' => $productDescription,
            'accountInfo' => $productType === 'conta' ? $accountInfo : '',
            'description' => $description,
            'notes' => $notes,
            'isActive' => $isActive,
            'removeAccountImages' => is_array($removeAccountImages) ? $removeAccountImages : [],
            'newImages' => is_array($newImages) ? $newImages : [],
        ];
    }

    private function repositoryPayload(array $normalized, array $accountImages): array
    {
        return [
            'category_id' => $normalized['categoryId'],
            'name' => $normalized['name'],
            'slug' => $normalized['slug'],
            'unit_price' => $normalized['unitPrice'],
            'stock' => $normalized['stock'],
            'minimum_quantity' => $normalized['minimumQuantity'],
            'server_label' => $normalized['serverLabel'],
            'delivery_eta' => $normalized['deliveryEta'],
            'delivery_method' => $normalized['deliveryMethod'],
            'product_type' => $normalized['productType'],
            'product_description' => $normalized['productDescription'],
            'account_info' => $normalized['accountInfo'] !== '' ? $normalized['accountInfo'] : null,
            'account_images' => $accountImages !== [] ? json_encode($accountImages, JSON_UNESCAPED_SLASHES) : null,
            'description' => $normalized['description'],
            'notes' => $normalized['notes'],
            'is_active' => $normalized['isActive'] ? 1 : 0,
        ];
    }

    private function categoryExists(int $categoryId): bool
    {
        foreach ((new CategoryRepository())->all() as $category) {
            if ((int) ($category['id'] ?? 0) === $categoryId) {
                return true;
            }
        }

        return false;
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : bin2hex(random_bytes(3));
    }

    private function notFound(): void
    {
        json_response([
            'ok' => false,
            'message' => 'Produto não encontrado.',
        ], 404);
    }

    private function defaultDescription(): string
    {
        return <<<'TEXT'
[DESCRIÇÃO - ENTREGA]

Após a confirmação do pagamento, o pedido entra automaticamente em nosso sistema e é processado de forma rápida e segura.

A entrega dos Teras / itens / contas é realizada 100% digital, diretamente no e-mail informado no momento da compra ou pelo meio de contato escolhido (ex.: chat ou WhatsApp), sem necessidade de download externo ou etapas complicadas.

Prazo de entrega:

Normalmente entre 5 a 30 minutos após a confirmação do pagamento.

Em casos específicos (alta demanda, verificação adicional ou manutenção), o prazo pode se estender para até 24 horas, sempre com aviso prévio.

Segurança:
Todo o processo é feito de forma segura, sem acesso indevido à conta do cliente. Trabalhamos com métodos confiáveis para garantir que a entrega seja rápida e sem riscos.

Suporte:
Nossa equipe está disponível para acompanhar a entrega e esclarecer qualquer dúvida durante todo o processo.
TEXT;
    }

    private function defaultNotes(): string
    {
        return <<<'TEXT'
[OBSERVAÇÕES - DIREITOS, REGRAS E REEMBOLSO]

Ao realizar a compra, o usuário concorda com os termos abaixo:

Direitos do cliente:

Receber o produto exatamente conforme descrito.

Receber suporte caso ocorra qualquer problema na entrega.

Solicitar reembolso total ou parcial caso o produto não seja entregue dentro do prazo máximo informado ou caso haja erro comprovado por parte da loja.

Reembolso não aplicável quando:

O produto já tiver sido entregue corretamente.

O erro for causado por informações incorretas fornecidas pelo cliente (e-mail errado, conta incorreta, etc.).

O cliente violar regras do jogo após a entrega (banimentos, punições ou perdas não são de nossa responsabilidade).

Reembolso:

Caso aprovado, o reembolso será realizado pelo mesmo método de pagamento, dentro do prazo da plataforma utilizada.

A solicitação deve ser feita antes do uso do produto.

Importante:

Produtos digitais não possuem devolução após o uso.

Qualquer tentativa de fraude resultará no bloqueio permanente do usuário.

Nosso objetivo é oferecer uma experiência rápida, segura e transparente, sempre priorizando a satisfação do cliente.
TEXT;
    }
}
