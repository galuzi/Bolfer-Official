<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\AdminInviteKeyRepository;
use App\Support\DesktopApiPresenter;

final class InvitesController
{
    public function index(): void
    {
        $this->requireFounderContext();

        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => $this->normalizeStatus((string) ($_GET['status'] ?? 'all')),
        ];

        $repo = new AdminInviteKeyRepository();
        $invites = $repo->all($filters);

        json_response([
            'ok' => true,
            'data' => [
                'filters' => [
                    'search' => $filters['search'],
                    'status' => $filters['status'],
                ],
                'summary' => $this->buildSummary($invites),
                'policy' => $this->policy(),
                'invites' => array_map(
                    static fn(array $invite): array => DesktopApiPresenter::invite($invite),
                    $invites
                ),
            ],
        ]);
    }

    public function create(): void
    {
        $context = $this->requireFounderContext();
        $admin = $context['admin'] ?? [];
        $quantity = max(1, min(5, (int) (request_data()['quantity'] ?? 1)));
        $repo = new AdminInviteKeyRepository();
        $createdInvites = [];

        for ($i = 0; $i < $quantity; $i++) {
            $inviteKey = $this->generateUniqueKey($repo);
            $inviteId = $repo->create($inviteKey, (int) ($admin['id'] ?? 0));
            $createdInvites[] = [
                'id' => $inviteId,
                'invite_key' => $inviteKey,
                'created_by_admin_id' => (int) ($admin['id'] ?? 0),
                'created_by_email' => $admin['username'] ?? null,
                'used_by_admin_id' => null,
                'used_by_email' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'used_at' => null,
            ];
        }

        json_response([
            'ok' => true,
            'message' => $quantity === 1
                ? 'Convite para staff gerado com sucesso.'
                : 'Convites para staff gerados com sucesso.',
            'data' => [
                'created' => array_map(
                    static fn(array $invite): array => DesktopApiPresenter::invite($invite),
                    $createdInvites
                ),
                'policy' => $this->policy(),
            ],
        ]);
    }

    public function delete(string $id): void
    {
        $this->requireFounderContext();

        $inviteId = (int) $id;
        if ($inviteId <= 0) {
            json_response([
                'ok' => false,
                'message' => 'Convite invalido.',
            ], 422);
        }

        $deleted = (new AdminInviteKeyRepository())->delete($inviteId);
        if (!$deleted) {
            json_response([
                'ok' => false,
                'message' => 'Convite nao encontrado.',
            ], 404);
        }

        json_response([
            'ok' => true,
            'message' => 'Convite removido com sucesso.',
            'data' => [
                'policy' => $this->policy(),
            ],
        ]);
    }

    private function requireFounderContext(): array
    {
        $context = require_admin_api();

        if (\admin_role_level((string) (($context['admin']['role'] ?? 'staff'))) < 40) {
            json_response([
                'ok' => false,
                'message' => 'Apenas contas founder podem gerenciar convites para staff.',
            ], 403);
        }

        return $context;
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'available', 'used' => $status,
            default => 'all',
        };
    }

    private function buildSummary(array $invites): array
    {
        $available = 0;
        $used = 0;

        foreach ($invites as $invite) {
            if (!empty($invite['used_by_admin_id'])) {
                $used++;
                continue;
            }

            $available++;
        }

        return [
            'visible' => count($invites),
            'available' => $available,
            'used' => $used,
        ];
    }

    private function policy(): array
    {
        return [
            'targetRole' => 'staff',
            'allowFounderCreation' => false,
            'message' => 'Os convites do desktop criam apenas contas staff. Founder não pode ser criado por convite.',
        ];
    }

    private function generateUniqueKey(AdminInviteKeyRepository $repo): string
    {
        do {
            $key = strtoupper(bin2hex(random_bytes(6)));
        } while ($repo->exists($key));

        return $key;
    }
}
