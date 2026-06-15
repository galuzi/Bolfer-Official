<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
final class ProductRepository
{
    private static ?bool $hasMinimumQuantityColumn = null;

    public function allActive(): array
    {
        $stmt = Database::pdo()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.created_at DESC');
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $stmt = Database::pdo()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC');
        return $stmt->fetchAll();
    }

    public function findBySlugOrId(string $value): ?array
    {
        if (ctype_digit($value)) {
            $stmt = Database::pdo()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $value]);
        } else {
            $stmt = Database::pdo()->prepare('SELECT * FROM products WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $value]);
        }
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function create(array $data): void
    {
        $minimumQuantity = $this->normalizeMinimumQuantity($data);
        $params = [
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'unit_price' => $data['unit_price'],
            'stock' => $data['stock'] === '' ? null : (int) $data['stock'],
            'server_label' => $data['server_label'] ?? 'LDMO Omegamon',
            'delivery_eta' => $data['delivery_eta'] ?? '5min-1h',
            'delivery_method' => $data['delivery_method'] ?? null,
            'product_type' => $data['product_type'] ?? 'item',
            'product_description' => $data['product_description'] ?? null,
            'account_info' => $data['account_info'] ?? null,
            'account_images' => $data['account_images'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];

        if ($this->hasMinimumQuantityColumn()) {
            $params['minimum_quantity'] = $minimumQuantity;
            $stmt = Database::pdo()->prepare('INSERT INTO products (category_id, name, slug, unit_price, stock, minimum_quantity, server_label, delivery_eta, delivery_method, product_type, product_description, account_info, account_images, description, notes, is_active) VALUES (:category_id, :name, :slug, :unit_price, :stock, :minimum_quantity, :server_label, :delivery_eta, :delivery_method, :product_type, :product_description, :account_info, :account_images, :description, :notes, :is_active)');
            $stmt->execute($params);
            return;
        }

        if ($minimumQuantity > 1) {
            throw new \RuntimeException('O banco ainda nao recebeu o campo de compra minima. Execute sql/changes_only.sql antes de salvar um produto com minimo maior que 1.');
        }

        $stmt = Database::pdo()->prepare('INSERT INTO products (category_id, name, slug, unit_price, stock, server_label, delivery_eta, delivery_method, product_type, product_description, account_info, account_images, description, notes, is_active) VALUES (:category_id, :name, :slug, :unit_price, :stock, :server_label, :delivery_eta, :delivery_method, :product_type, :product_description, :account_info, :account_images, :description, :notes, :is_active)');
        $stmt->execute($params);
    }

    public function update(int $id, array $data): void
    {
        $minimumQuantity = $this->normalizeMinimumQuantity($data);
        $params = [
            'id' => $id,
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'unit_price' => $data['unit_price'],
            'stock' => $data['stock'] === '' ? null : (int) $data['stock'],
            'server_label' => $data['server_label'] ?? 'LDMO Omegamon',
            'delivery_eta' => $data['delivery_eta'] ?? '5min-1h',
            'delivery_method' => $data['delivery_method'] ?? null,
            'product_type' => $data['product_type'] ?? 'item',
            'product_description' => $data['product_description'] ?? null,
            'account_info' => $data['account_info'] ?? null,
            'account_images' => $data['account_images'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];

        if ($this->hasMinimumQuantityColumn()) {
            $params['minimum_quantity'] = $minimumQuantity;
            $stmt = Database::pdo()->prepare('UPDATE products SET category_id = :category_id, name = :name, slug = :slug, unit_price = :unit_price, stock = :stock, minimum_quantity = :minimum_quantity, server_label = :server_label, delivery_eta = :delivery_eta, delivery_method = :delivery_method, product_type = :product_type, product_description = :product_description, account_info = :account_info, account_images = :account_images, description = :description, notes = :notes, is_active = :is_active WHERE id = :id');
            $stmt->execute($params);
            return;
        }

        if ($minimumQuantity > 1) {
            throw new \RuntimeException('O banco ainda nao recebeu o campo de compra minima. Execute sql/changes_only.sql antes de salvar um produto com minimo maior que 1.');
        }

        $stmt = Database::pdo()->prepare('UPDATE products SET category_id = :category_id, name = :name, slug = :slug, unit_price = :unit_price, stock = :stock, server_label = :server_label, delivery_eta = :delivery_eta, delivery_method = :delivery_method, product_type = :product_type, product_description = :product_description, account_info = :account_info, account_images = :account_images, description = :description, notes = :notes, is_active = :is_active WHERE id = :id');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function decrementStock(int $id, int $quantity): void
    {
        $stmt = Database::pdo()->prepare('UPDATE products SET stock = stock - :qty WHERE id = :id AND stock IS NOT NULL');
        $stmt->execute([
            'id' => $id,
            'qty' => $quantity,
        ]);
    }

    private function hasMinimumQuantityColumn(): bool
    {
        if (self::$hasMinimumQuantityColumn !== null) {
            return self::$hasMinimumQuantityColumn;
        }

        try {
            $stmt = Database::pdo()->query("SHOW COLUMNS FROM products LIKE 'minimum_quantity'");
            self::$hasMinimumQuantityColumn = (bool) $stmt->fetch();
        } catch (\Throwable) {
            self::$hasMinimumQuantityColumn = false;
        }

        return self::$hasMinimumQuantityColumn;
    }

    private function normalizeMinimumQuantity(array $data): int
    {
        $rawValue = $data['minimum_quantity'] ?? $data['minimumQuantity'] ?? 1;
        return max(1, (int) $rawValue);
    }
}
