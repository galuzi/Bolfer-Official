<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
final class CategoryRepository
{
    public function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $category = $stmt->fetch();
        return $category ?: null;
    }

    public function allActive(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM categories ORDER BY sort_order, name');
        return $stmt->fetchAll();
    }

    public function create(array $data): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO categories (name, slug, is_active, sort_order) VALUES (:name, :slug, :is_active, :sort_order)');
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_active' => (int) ($data['is_active'] ?? 1),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare('UPDATE categories SET name = :name, slug = :slug, is_active = :is_active, sort_order = :sort_order WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_active' => (int) ($data['is_active'] ?? 0),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
