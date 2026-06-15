<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Services\DiscordActivityService;
use App\Support\View;
final class AdminCategoryController
{
    public function index(): void
    {
        require_admin();

        $repo = new CategoryRepository();
        $discordService = new DiscordActivityService();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'create';
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            if ($slug === '' && $name !== '') {
                $slug = $this->slugify($name);
            }

            if ($action === 'create') {
                $repo->create([
                    'name' => $name,
                    'slug' => $slug,
                    'is_active' => $_POST['is_active'] ?? 0,
                    'sort_order' => $_POST['sort_order'] ?? 0,
                ]);
                $discordService->notify(
                    admin_user() ?? [],
                    'category_create',
                    'catalog',
                    'Categoria criada no painel',
                    'Uma nova categoria foi adicionada ao catalogo do site.',
                    [
                        ['name' => 'Categoria', 'value' => $name, 'inline' => true],
                        ['name' => 'Slug', 'value' => $slug, 'inline' => true],
                    ]
                );
                flash_set('success', 'Categoria criada.');
                redirect('/admin/categories');
            }

            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $repo->update($id, [
                    'name' => $name,
                    'slug' => $slug,
                    'is_active' => $_POST['is_active'] ?? 0,
                    'sort_order' => $_POST['sort_order'] ?? 0,
                ]);
                $discordService->notify(
                    admin_user() ?? [],
                    'category_update',
                    'catalog',
                    'Categoria atualizada no painel',
                    'Uma categoria recebeu ajuste de nome, ordem ou status.',
                    [
                        ['name' => 'Categoria', 'value' => $name, 'inline' => true],
                        ['name' => 'Slug', 'value' => $slug, 'inline' => true],
                    ]
                );
                flash_set('success', 'Categoria atualizada.');
                redirect('/admin/categories');
            }

            if ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $currentCategory = null;
                foreach ($repo->all() as $category) {
                    if ((int) ($category['id'] ?? 0) === $id) {
                        $currentCategory = $category;
                        break;
                    }
                }
                $repo->delete($id);
                if ($currentCategory) {
                    $discordService->notify(
                        admin_user() ?? [],
                        'category_delete',
                        'catalog',
                        'Categoria removida do painel',
                        'Uma categoria foi excluida do catalogo do site.',
                        [
                            ['name' => 'Categoria', 'value' => (string) ($currentCategory['name'] ?? 'Categoria'), 'inline' => true],
                            ['name' => 'Slug', 'value' => (string) ($currentCategory['slug'] ?? '-'), 'inline' => true],
                        ]
                    );
                }
                flash_set('success', 'Categoria removida.');
                redirect('/admin/categories');
            }
        }

        $categories = $repo->all();
        $editId = (int) ($_GET['edit'] ?? 0);

        View::render('admin/categories', [
            'categories' => $categories,
            'editId' => $editId,
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
