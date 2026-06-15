<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\CategoryRepository;
use App\Services\DiscordActivityService;
use App\Support\DesktopApiPresenter;

final class CategoriesController
{
    public function index(): void
    {
        $this->requireFounder();

        $categories = (new CategoryRepository())->all();

        json_response([
            'ok' => true,
            'data' => [
                'categories' => array_map(
                    static fn(array $category): array => DesktopApiPresenter::category($category),
                    $categories
                ),
            ],
        ]);
    }

    public function create(): void
    {
        $context = $this->requireFounder();
        $payload = request_data();
        $repository = new CategoryRepository();

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            json_response([
                'ok' => false,
                'message' => 'Informe o nome da categoria.',
            ], 422);
        }

        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        if ($repository->findBySlug($slug)) {
            json_response([
                'ok' => false,
                'message' => 'Ja existe uma categoria com esse slug.',
            ], 409);
        }

        $sortOrder = (int) ($payload['sortOrder'] ?? $payload['sort_order'] ?? 0);
        $isActive = !empty($payload['isActive']) || (string) ($payload['is_active'] ?? '1') === '1';

        $repository->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);

        $created = $repository->findBySlug($slug);

        (new DiscordActivityService())->notify(
            $context['admin'] ?? [],
            'category_create',
            'catalog',
            'Categoria criada no desktop',
            'Uma nova categoria foi adicionada ao catalogo pelo app desktop.',
            [
                ['name' => 'Categoria', 'value' => $name, 'inline' => true],
                ['name' => 'Slug', 'value' => $slug, 'inline' => true],
                ['name' => 'Status', 'value' => $isActive ? 'Ativa' : 'Oculta', 'inline' => true],
            ]
        );

        json_response([
            'ok' => true,
            'message' => 'Categoria criada com sucesso.',
            'data' => [
                'category' => $created ? DesktopApiPresenter::category($created) : null,
                'categories' => array_map(
                    static fn(array $category): array => DesktopApiPresenter::category($category),
                    $repository->all()
                ),
            ],
        ]);
    }

    private function requireFounder(): array
    {
        $context = require_admin_api();

        if (\admin_role_level((string) ($context['admin']['role'] ?? 'staff')) < 40) {
            json_response([
                'ok' => false,
                'message' => 'Apenas contas founder podem gerenciar categorias do catalogo no desktop.',
            ], 403);
        }

        return $context;
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : bin2hex(random_bytes(3));
    }
}
