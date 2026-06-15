<?php
// Ponto de entrada compatível com estrutura local (public/)
// e com publicacao via FTP na KingHost (public_html/).
$candidates = [
    __DIR__ . '/public_html/index.php',
    __DIR__ . '/public/index.php',
];

foreach ($candidates as $entryPoint) {
    if (is_file($entryPoint)) {
        require $entryPoint;
        return;
    }
}

http_response_code(500);
echo 'Ponto de entrada publico nao encontrado.';
