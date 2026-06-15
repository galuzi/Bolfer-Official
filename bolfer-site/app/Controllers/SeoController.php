<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ModeratorRepository;
use App\Repositories\ProductRepository;

final class SeoController
{
    public function sitemap(): void
    {
        $entries = [];

        $addEntry = static function (array &$target, string $path, ?string $lastModified = null, string $changefreq = 'weekly', string $priority = '0.7'): void {
            $entry = [
                'loc' => url($path),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];

            if ($lastModified !== null && $lastModified !== '') {
                $timestamp = strtotime($lastModified);
                if ($timestamp !== false) {
                    $entry['lastmod'] = gmdate('c', $timestamp);
                }
            }

            $target[] = $entry;
        };

        $viewLastModified = static function (string $relativePath): ?string {
            $path = dirname(__DIR__) . '/Views/' . $relativePath;
            if (!is_file($path)) {
                return null;
            }

            $mtime = filemtime($path);
            if ($mtime === false) {
                return null;
            }

            return date('c', $mtime);
        };

        $addEntry($entries, '/', $viewLastModified('home.php'), 'daily', '1.0');
        $addEntry($entries, '/produtos', $viewLastModified('products.php'), 'daily', '0.9');
        $addEntry($entries, '/rankings', $viewLastModified('rankings.php'), 'daily', '0.9');
        $addEntry($entries, '/servicos', $viewLastModified('services.php'), 'weekly', '0.8');
        $addEntry($entries, '/doacoes', $viewLastModified('donations.php'), 'weekly', '0.8');
        $addEntry($entries, '/termos', $viewLastModified('terms.php'), 'monthly', '0.4');

        foreach ((new ProductRepository())->allActive() as $product) {
            $slug = trim((string) ($product['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $lastModified = (string) ($product['updated_at'] ?? $product['created_at'] ?? '');
            $addEntry($entries, '/produto/' . rawurlencode($slug), $lastModified, 'daily', '0.8');
        }

        foreach ((new ModeratorRepository())->all() as $slug => $moderator) {
            if (!is_string($slug) || trim($slug) === '') {
                continue;
            }

            $addEntry($entries, '/moderadores/' . rawurlencode($slug), null, 'monthly', '0.6');
        }

        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex');
        }

        $escape = static function (string $value): string {
            return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($entries as $entry) {
            echo "  <url>\n";
            echo '    <loc>' . $escape((string) $entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                echo '    <lastmod>' . $escape((string) $entry['lastmod']) . "</lastmod>\n";
            }
            echo '    <changefreq>' . $escape((string) $entry['changefreq']) . "</changefreq>\n";
            echo '    <priority>' . $escape((string) $entry['priority']) . "</priority>\n";
            echo "  </url>\n";
        }

        echo "</urlset>";
    }
}