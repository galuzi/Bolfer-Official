<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductAccountMediaService;
use App\Services\DiscordActivityService;
use App\Support\View;

final class AdminProductController
{
    public function index(): void
    {
        require_admin();

        $repo = new ProductRepository();
        $discordService = new DiscordActivityService();
        $defaultDescription = <<<'TEXT'
[DESCRICAO - ENTREGA]

Apos a confirmacao do pagamento, o pedido entra automaticamente em nosso sistema e e processado de forma rapida e segura.

A entrega dos Teras / itens / contas e realizada 100% digital, diretamente no e-mail informado no momento da compra ou pelo meio de contato escolhido (ex: chat ou WhatsApp), sem necessidade de download externo ou etapas complicadas.

Prazo de entrega:

Normalmente entre 5 a 30 minutos apos a confirmacao do pagamento.

Em casos especificos (alta demanda, verificacao adicional ou manutencao), o prazo pode se estender para ate 24 horas, sempre com aviso previo.

Seguranca:
Todo o processo e feito de forma segura, sem acesso indevido a conta do cliente. Trabalhamos com metodos confiaveis para garantir que a entrega seja rapida e sem riscos.

Suporte:
Nossa equipe esta disponivel para acompanhar a entrega e esclarecer qualquer duvida durante todo o processo.
TEXT;

        $defaultNotes = <<<'TEXT'
[OBSERVACOES - DIREITOS, REGRAS E REEMBOLSO]

Ao realizar a compra, o usuario concorda com os termos abaixo:

Direitos do cliente:

Receber o produto exatamente conforme descrito.

Receber suporte caso ocorra qualquer problema na entrega.

Solicitar reembolso total ou parcial caso o produto nao seja entregue dentro do prazo maximo informado ou caso haja erro comprovado por parte da loja.

Reembolso nao aplicavel quando:

O produto ja tiver sido entregue corretamente.

O erro for causado por informacoes incorretas fornecidas pelo cliente (e-mail errado, conta incorreta, etc).

O cliente violar regras do jogo apos a entrega (banimentos, punicoes ou perdas nao sao de nossa responsabilidade).

Reembolso:

Caso aprovado, o reembolso sera realizado pelo mesmo metodo de pagamento, dentro do prazo da plataforma utilizada.

A solicitacao deve ser feita antes do uso do produto.

Importante:

Produtos digitais nao possuem devolucao apos o uso.

Qualquer tentativa de fraude resultara no bloqueio permanente do usuario.

Nosso objetivo e oferecer uma experiencia rapida, segura e transparente, sempre priorizando a satisfacao do cliente.
TEXT;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'create';
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $redirectPath = '/admin/products';
            $requestedEditId = (int) ($_POST['id'] ?? 0);
            if ($requestedEditId > 0) {
                $redirectPath .= '?edit=' . $requestedEditId;
            }
            if ($slug === '' && $name !== '') {
                $slug = $this->slugify($name);
            }
            $productDescription = trim((string) ($_POST['product_description'] ?? ''));
            $accountInfo = trim((string) ($_POST['account_info'] ?? ''));
            $productType = trim((string) ($_POST['product_type'] ?? 'item'));
            $allowedTypes = ['item', 'conta'];
            if (!in_array($productType, $allowedTypes, true)) {
                $productType = 'item';
            }
            $description = trim((string) ($_POST['description'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if ($description === '') {
                $description = $defaultDescription;
            }
            if ($notes === '') {
                $notes = $defaultNotes;
            }

            $stockInput = trim((string) ($_POST['stock'] ?? ''));
            if ($stockInput !== '' && (!ctype_digit(ltrim($stockInput, '+')) || (int) $stockInput < 0)) {
                flash_set('error', 'O estoque deve ficar vazio ou usar um numero inteiro igual ou maior que zero.');
                redirect($redirectPath);
            }

            $minimumQuantityInput = trim((string) ($_POST['minimum_quantity'] ?? '1'));
            if ($minimumQuantityInput === '' || !ctype_digit(ltrim($minimumQuantityInput, '+')) || (int) $minimumQuantityInput < 1) {
                flash_set('error', 'A compra minima do produto deve ser de pelo menos 1 unidade.');
                redirect($redirectPath);
            }

            $minimumQuantity = max(1, (int) $minimumQuantityInput);
            if ($stockInput !== '' && (int) $stockInput > 0 && $minimumQuantity > (int) $stockInput) {
                flash_set('error', 'A compra minima nao pode ser maior que o estoque atual do produto.');
                redirect($redirectPath);
            }

            $currentProduct = null;
            if ($action === 'update' || $action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $currentProduct = $repo->find($id);
            }

            if ($action === 'update' && !$currentProduct) {
                flash_set('error', 'Produto nao encontrado para atualizacao.');
                redirect('/admin/products');
            }

            $media = new ProductAccountMediaService();

            if ($action === 'delete') {
                if ($currentProduct) {
                    $media->deleteAll($media->decodeStoredImages($currentProduct['account_images'] ?? null));
                    $repo->delete((int) $currentProduct['id']);
                    $discordService->notify(
                        admin_user() ?? [],
                        'product_delete',
                        'products',
                        'Produto removido do catalogo',
                        'Um produto foi retirado do painel administrativo.',
                        [
                            ['name' => 'Produto', 'value' => (string) ($currentProduct['name'] ?? 'Produto'), 'inline' => true],
                            ['name' => 'Tipo', 'value' => (string) ($currentProduct['product_type'] ?? 'item'), 'inline' => true],
                            ['name' => 'Slug', 'value' => (string) ($currentProduct['slug'] ?? '-'), 'inline' => true],
                        ]
                    );
                    flash_set('success', 'Produto removido.');
                    redirect('/admin/products');
                }

                flash_set('error', 'Produto nao encontrado.');
                redirect('/admin/products');
            }

            try {
                $currentImages = $currentProduct ? $media->decodeStoredImages($currentProduct['account_images'] ?? null) : [];
                $removeImages = $_POST['remove_account_images'] ?? [];
                if (!is_array($removeImages)) {
                    $removeImages = [];
                }
                $accountImages = $media->sync($currentImages, $_FILES['account_images'] ?? null, $removeImages);
            } catch (\RuntimeException $e) {
                flash_set('error', $e->getMessage());
                redirect('/admin/products' . ($currentProduct ? '?edit=' . (int) $currentProduct['id'] : ''));
            }

            $payload = [
                'category_id' => $_POST['category_id'] ?? 0,
                'name' => $name,
                'slug' => $slug,
                'unit_price' => $_POST['unit_price'] ?? 0,
                'stock' => $stockInput,
                'minimum_quantity' => $minimumQuantity,
                'server_label' => 'LDMO Omegamon',
                'delivery_eta' => $_POST['delivery_eta'] ?? '5min-1h',
                'delivery_method' => $_POST['delivery_method'] ?? null,
                'product_type' => $productType,
                'product_description' => $productDescription,
                'account_info' => $accountInfo !== '' ? $accountInfo : null,
                'account_images' => !empty($accountImages) ? json_encode($accountImages, JSON_UNESCAPED_SLASHES) : null,
                'description' => $description,
                'notes' => $notes,
                'is_active' => $_POST['is_active'] ?? 0,
            ];

            if ($action === 'create') {
                try {
                    $repo->create($payload);
                } catch (\RuntimeException $exception) {
                    flash_set('error', $exception->getMessage());
                    redirect($redirectPath);
                }
                $discordService->notify(
                    admin_user() ?? [],
                    'product_create',
                    'products',
                    'Novo produto criado no painel',
                    'A equipe publicou um novo item ou conta para o catalogo.',
                    [
                        ['name' => 'Produto', 'value' => $name, 'inline' => true],
                        ['name' => 'Tipo', 'value' => $productType, 'inline' => true],
                        ['name' => 'Preco', 'value' => 'R$ ' . number_format((float) ($payload['unit_price'] ?? 0), 2, ',', '.'), 'inline' => true],
                    ]
                );
                flash_set('success', 'Produto criado.');
                redirect('/admin/products');
            }

            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                try {
                    $repo->update($id, $payload);
                } catch (\RuntimeException $exception) {
                    flash_set('error', $exception->getMessage());
                    redirect($redirectPath);
                }
                $discordService->notify(
                    admin_user() ?? [],
                    'product_update',
                    'products',
                    'Produto atualizado no painel',
                    'Um produto recebeu ajuste de dados, preco ou entrega.',
                    [
                        ['name' => 'Produto', 'value' => $name, 'inline' => true],
                        ['name' => 'Tipo', 'value' => $productType, 'inline' => true],
                        ['name' => 'Preco', 'value' => 'R$ ' . number_format((float) ($payload['unit_price'] ?? 0), 2, ',', '.'), 'inline' => true],
                    ]
                );
                flash_set('success', 'Produto atualizado.');
                redirect('/admin/products');
            }
        }

        $products = $repo->all();
        $categories = (new CategoryRepository())->all();
        $editId = (int) ($_GET['edit'] ?? 0);

        View::render('admin/products', [
            'products' => $products,
            'categories' => $categories,
            'editId' => $editId,
            'defaultDescription' => $defaultDescription,
            'defaultNotes' => $defaultNotes,
        ]);
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? $value : bin2hex(random_bytes(3));
    }
}
