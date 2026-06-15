<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminRepository;
use App\Repositories\DiscordActivityLogRepository;
use App\Repositories\SettingsRepository;

final class DiscordActivityService
{
    private const TYPE_LABELS = [
        'manual' => 'Mensagem manual',
        'website_work' => 'Criando website',
        'ticket_reply' => 'Respondendo ticket',
        'order_status' => 'Pedido atualizado',
        'order_refund' => 'Pedido reembolsado',
        'order_note' => 'Pedido respondido',
        'product_create' => 'Produto criado',
        'product_update' => 'Produto atualizado',
        'product_delete' => 'Produto removido',
        'category_create' => 'Categoria criada',
        'category_update' => 'Categoria atualizada',
        'category_delete' => 'Categoria removida',
        'user_ban' => 'Usuario banido',
        'user_unban' => 'Usuario desbloqueado',
    ];

    private const TYPE_COLORS = [
        'manual' => 0x57F287,
        'website_work' => 0x5865F2,
        'ticket_reply' => 0xFEE75C,
        'order_status' => 0x3BA55D,
        'order_refund' => 0xED4245,
        'order_note' => 0x5865F2,
        'product_create' => 0x57F287,
        'product_update' => 0xFEE75C,
        'product_delete' => 0xED4245,
        'category_create' => 0x57F287,
        'category_update' => 0xFEE75C,
        'category_delete' => 0xED4245,
        'user_ban' => 0xED4245,
        'user_unban' => 0x57F287,
    ];

    private SettingsRepository $settingsRepository;
    private AdminRepository $adminRepository;
    private DiscordActivityLogRepository $logRepository;

    public function __construct()
    {
        $this->settingsRepository = new SettingsRepository();
        $this->adminRepository = new AdminRepository();
        $this->logRepository = new DiscordActivityLogRepository();
    }

    public static function typeLabels(): array
    {
        return self::TYPE_LABELS;
    }

    public function sendManual(array $sessionAdmin, string $type, string $title, string $description): array
    {
        return $this->dispatch(
            $sessionAdmin,
            $type,
            'manual',
            $title,
            $description,
            [],
            true
        );
    }

    public function notify(array $sessionAdmin, string $type, string $scope, string $title, string $description, array $fields = []): void
    {
        $this->dispatch($sessionAdmin, $type, $scope, $title, $description, $fields, false);
    }

    public function recentLogs(int $limit = 10): array
    {
        return $this->logRepository->listRecent($limit);
    }

    public function globalConfig(): array
    {
        return [
            'enabled' => $this->isSettingEnabled('discord_activity_enabled', false),
            'webhook_url' => trim((string) $this->settingsRepository->get('discord_activity_webhook_url', '')),
            'bot_name' => trim((string) $this->settingsRepository->get('discord_activity_bot_name', 'Bolfer Activity')),
            'footer' => trim((string) $this->settingsRepository->get('discord_activity_footer', 'Bolfer · Painel administrativo')),
        ];
    }

    private function dispatch(
        array $sessionAdmin,
        string $type,
        string $scope,
        string $title,
        string $description,
        array $fields,
        bool $isManual
    ): array {
        $adminId = (int) ($sessionAdmin['id'] ?? 0);
        $admin = $adminId > 0 ? $this->adminRepository->findById($adminId) : null;
        $title = $this->truncate(trim($title), 180);
        $description = $this->truncate(trim($description), 1900);

        if ($title === '' || $description === '') {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Preencha titulo e descricao para enviar a atividade.',
            ];
        }

        if (!$admin || \admin_role_level((string) ($admin['role'] ?? '')) < 20) {
            $this->logRepository->create([
                'admin_id' => $adminId > 0 ? $adminId : null,
                'activity_type' => $type,
                'activity_scope' => $scope,
                'title' => $title,
                'description' => $description,
                'fields_json' => $this->encodeFields($fields),
                'status' => 'skipped',
                'is_manual' => $isManual,
                'error_message' => 'Permissao insuficiente para publicar no Discord.',
            ]);

            return [
                'ok' => false,
                'status' => 'skipped',
                'message' => 'Sua conta nao tem permissao para publicar atividade no Discord.',
            ];
        }

        $config = $this->globalConfig();
        if (!$config['enabled']) {
            $this->logRepository->create([
                'admin_id' => $adminId,
                'activity_type' => $type,
                'activity_scope' => $scope,
                'title' => $title,
                'description' => $description,
                'fields_json' => $this->encodeFields($fields),
                'status' => 'skipped',
                'is_manual' => $isManual,
                'error_message' => 'Webhook global desativado nas configuracoes.',
            ]);

            return [
                'ok' => false,
                'status' => 'skipped',
                'message' => 'A integracao com Discord esta desativada nas configuracoes.',
            ];
        }

        $webhookUrl = trim((string) $config['webhook_url']);
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $this->logRepository->create([
                'admin_id' => $adminId,
                'activity_type' => $type,
                'activity_scope' => $scope,
                'title' => $title,
                'description' => $description,
                'fields_json' => $this->encodeFields($fields),
                'status' => 'skipped',
                'is_manual' => $isManual,
                'error_message' => 'Webhook do Discord nao configurado.',
            ]);

            return [
                'ok' => false,
                'status' => 'skipped',
                'message' => 'Configure o webhook do Discord antes de publicar atividades.',
            ];
        }

        if (empty($admin['discord_activity_enabled'])) {
            $this->logRepository->create([
                'admin_id' => $adminId,
                'activity_type' => $type,
                'activity_scope' => $scope,
                'title' => $title,
                'description' => $description,
                'fields_json' => $this->encodeFields($fields),
                'webhook_url' => $webhookUrl,
                'status' => 'skipped',
                'is_manual' => $isManual,
                'error_message' => 'Status pessoal desativado pelo admin.',
            ]);

            return [
                'ok' => false,
                'status' => 'skipped',
                'message' => 'Seu status de atividade no Discord esta desativado.',
            ];
        }

        [$ok, $errorMessage] = $this->postWebhook($webhookUrl, $this->payload(
            $admin,
            $config,
            $type,
            $scope,
            $title,
            $description,
            $fields,
            $isManual
        ));

        $this->logRepository->create([
            'admin_id' => $adminId,
            'activity_type' => $type,
            'activity_scope' => $scope,
            'title' => $title,
            'description' => $description,
            'fields_json' => $this->encodeFields($fields),
            'webhook_url' => $webhookUrl,
            'status' => $ok ? 'sent' : 'failed',
            'is_manual' => $isManual,
            'error_message' => $ok ? null : $errorMessage,
            'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
        ]);

        return [
            'ok' => $ok,
            'status' => $ok ? 'sent' : 'failed',
            'message' => $ok
                ? 'Atividade enviada para o Discord.'
                : 'Nao foi possivel enviar a atividade para o Discord agora.',
        ];
    }

    private function payload(
        array $admin,
        array $config,
        string $type,
        string $scope,
        string $title,
        string $description,
        array $fields,
        bool $isManual
    ): array {
        $actorName = trim((string) ($admin['discord_activity_display_name'] ?? ''));
        if ($actorName === '') {
            $actorName = (string) ($admin['username'] ?? 'Equipe Bolfer');
        }

        $embedFields = [
            [
                'name' => 'Responsavel',
                'value' => $this->truncate($actorName, 250),
                'inline' => true,
            ],
            [
                'name' => 'Nivel',
                'value' => strtoupper((string) ($admin['role'] ?? 'staff')),
                'inline' => true,
            ],
            [
                'name' => 'Area',
                'value' => strtoupper($scope),
                'inline' => true,
            ],
        ];

        foreach ($fields as $field) {
            $name = $this->truncate(trim((string) ($field['name'] ?? 'Detalhe')), 250);
            $value = $this->truncate(trim((string) ($field['value'] ?? '-')), 1000);
            if ($name === '' || $value === '') {
                continue;
            }

            $embedFields[] = [
                'name' => $name,
                'value' => $value,
                'inline' => !empty($field['inline']),
            ];
        }

        return [
            'username' => $this->truncate((string) ($config['bot_name'] ?? 'Bolfer Activity'), 80),
            'embeds' => [[
                'title' => $title,
                'description' => $description,
                'color' => self::TYPE_COLORS[$type] ?? 0x5865F2,
                'fields' => $embedFields,
                'footer' => [
                    'text' => $this->truncate(
                        (string) ($config['footer'] ?? 'Bolfer')
                        . ($isManual ? ' · mensagem manual' : ' · atividade automatica'),
                        200
                    ),
                ],
                'timestamp' => gmdate('c'),
            ]],
        ];
    }

    private function postWebhook(string $webhookUrl, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [false, 'Falha ao gerar o payload do Discord.'];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $error !== '') {
                return [false, $error !== '' ? $error : 'Falha ao enviar com cURL.'];
            }

            return [$httpCode >= 200 && $httpCode < 300, 'HTTP ' . $httpCode];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($webhookUrl, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($response === false) {
            return [false, 'Falha ao enviar usando stream.'];
        }

        return [$httpCode >= 200 && $httpCode < 300, 'HTTP ' . $httpCode];
    }

    private function encodeFields(array $fields): ?string
    {
        if ($fields === []) {
            return null;
        }

        $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    private function isSettingEnabled(string $key, bool $default = false): bool
    {
        $value = strtolower(trim((string) $this->settingsRepository->get($key, $default ? '1' : '0')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function truncate(string $value, int $limit): string
    {
        if ($value === '') {
            return '';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length <= $limit) {
            return $value;
        }

        $slice = function_exists('mb_substr')
            ? mb_substr($value, 0, max(1, $limit - 3))
            : substr($value, 0, max(1, $limit - 3));

        return rtrim($slice) . '...';
    }
}
