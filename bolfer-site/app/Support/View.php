<?php

declare(strict_types=1);

namespace App\Support;
final class View
{
    public static function render(string $template, array $data = []): void
    {
        $viewPath = __DIR__ . '/../Views/' . $template . '.php';
        if (!is_file($viewPath)) {
            http_response_code(500);
            echo 'View não encontrada.';
            exit;
        }

        extract($data, EXTR_SKIP);
        require $viewPath;
    }
}
