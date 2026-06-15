<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductRepository;
use App\Support\View;
final class ProductController
{
    public function show(string $slugOrId): void
    {
        $value = rawurldecode($slugOrId);
        $repo = new ProductRepository();
        $product = $repo->findBySlugOrId($value);

        if (!$product && !ctype_digit($value)) {
            $candidate = $this->slugify($value);
            if ($candidate !== '' && $candidate !== $value) {
                $product = $repo->findBySlugOrId($candidate);
                if ($product && (int) $product['is_active'] === 1) {
                    redirect('/produto/' . $product['slug']);
                }
            }
        }

        if (!$product || (int) $product['is_active'] !== 1) {
            http_response_code(404);
            echo 'Produto nao encontrado.';
            return;
        }

        View::render('product', [
            'product' => $product,
        ]);
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value;
    }
}
