<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdminInviteKeyRepository;
use App\Support\View;
final class AdminInviteKeyController
{
    public function index(): void
    {
        require_founder_admin();

        $admin = admin_user();
        $adminRole = (string) ($admin['role'] ?? '');
        $canManage = admin_role_level($adminRole) >= 40;
        $repo = new AdminInviteKeyRepository();
        $filters = $this->resolveFilters($_GET, isset($_GET['clear']));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? 'generate');
            $redirectUrl = $this->buildIndexUrl($this->resolveFilters($_POST));

            if (!$canManage) {
                flash_set('error', 'Sem permissao para gerenciar chaves.');
                redirect($redirectUrl);
            }

            if ($action === 'delete') {
                $inviteId = (int) ($_POST['invite_id'] ?? 0);
                if ($inviteId <= 0) {
                    flash_set('error', 'Chave invalida.');
                    redirect($redirectUrl);
                }

                if ($repo->delete($inviteId)) {
                    flash_set('success', 'Chave removida.');
                } else {
                    flash_set('error', 'Chave nao encontrada.');
                }

                redirect($redirectUrl);
            }

            $quantity = max(1, min(5, (int) ($_POST['quantity'] ?? 1)));
            $keys = [];

            for ($i = 0; $i < $quantity; $i++) {
                $key = $this->generateUniqueKey($repo);
                $repo->create($key, (int) ($admin['id'] ?? 0));
                $keys[] = $key;
            }

            $links = array_map(
                static fn(string $key): string => url('/admin/register?invite=' . urlencode($key)),
                $keys
            );
            flash_set('success', 'Links de convite gerados: ' . implode(' | ', $links));
            redirect($redirectUrl);
        }

        $keys = $repo->all($filters);

        View::render('admin/invites', [
            'keys' => $keys,
            'canManage' => $canManage,
            'filters' => $filters,
        ]);
    }

    private function generateUniqueKey(AdminInviteKeyRepository $repo): string
    {
        do {
            $key = strtoupper(bin2hex(random_bytes(6)));
        } while ($repo->exists($key));

        return $key;
    }

    private function resolveFilters(array $source, bool $clear = false): array
    {
        if ($clear) {
            return [
                'search' => '',
                'status' => 'all',
            ];
        }

        return [
            'search' => trim((string) ($source['search'] ?? '')),
            'status' => $this->normalizeStatus((string) ($source['status'] ?? 'all')),
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'available', 'used' => $status,
            default => 'all',
        };
    }

    private function buildIndexUrl(array $filters): string
    {
        $query = [];

        if (($filters['search'] ?? '') !== '') {
            $query['search'] = $filters['search'];
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query['status'] = $filters['status'];
        }

        return $query === []
            ? '/admin/invites'
            : '/admin/invites?' . http_build_query($query);
    }
}
