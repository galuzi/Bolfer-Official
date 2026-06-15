<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdminRepository;
use App\Repositories\DiscordActivityLogRepository;
use App\Repositories\OrderRepository;
use App\Services\DiscordActivityService;
use App\Support\View;
final class AdminDashboardController
{
    public function index(): void
    {
        require_admin();

        $adminRepository = new AdminRepository();
        $discordService = new DiscordActivityService();
        $currentAdmin = $adminRepository->findById((int) (admin_user()['id'] ?? 0));
        $twoFactorRecoveryCodes = $_SESSION['_admin_2fa_recovery_codes_once'] ?? [];
        unset($_SESSION['_admin_2fa_recovery_codes_once']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string) ($_POST['action'] ?? ''));

            if ($action === 'discord_profile') {
                $displayName = trim((string) ($_POST['discord_activity_display_name'] ?? ''));
                $enabled = !empty($_POST['discord_activity_enabled']);

                if (strlen($displayName) > 120) {
                    flash_set('error', 'O nome exibido no Discord precisa ter no maximo 120 caracteres.');
                    redirect('/admin/dashboard');
                }

                $adminRepository->updateDiscordProfile((int) ($currentAdmin['id'] ?? 0), $displayName, $enabled);
                $_SESSION['admin']['discord_activity_enabled'] = $enabled ? 1 : 0;
                $_SESSION['admin']['discord_activity_display_name'] = $displayName;
                flash_set('success', 'Preferencias de atividade no Discord atualizadas.');
                redirect('/admin/dashboard');
            }

            if ($action === 'discord_manual') {
                $type = trim((string) ($_POST['activity_type'] ?? 'manual'));
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $allowedTypes = array_keys(DiscordActivityService::typeLabels());

                if (!in_array($type, $allowedTypes, true)) {
                    $type = 'manual';
                }

                $result = $discordService->sendManual(admin_user() ?? [], $type, $title, $description);
                flash_set($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'Nao foi possivel enviar a atividade.'));
                redirect('/admin/dashboard');
            }
        }

        $repo = new OrderRepository();
        $paidWaiting = $repo->countByStatus('paid_waiting_contact');
        $inDelivery = $repo->countByStatus('in_delivery');
        $delivered = $repo->countByStatus('delivered');

        View::render('admin/dashboard', [
            'paidWaiting' => $paidWaiting,
            'inDelivery' => $inDelivery,
            'delivered' => $delivered,
            'currentAdminProfile' => $currentAdmin,
            'discordActivityConfig' => $discordService->globalConfig(),
            'discordActivityTypes' => DiscordActivityService::typeLabels(),
            'discordManualTypes' => array_intersect_key(
                DiscordActivityService::typeLabels(),
                array_flip(['manual', 'website_work', 'ticket_reply', 'order_note'])
            ),
            'discordActivityLogs' => (new DiscordActivityLogRepository())->listRecent(8),
            'twoFactorRecoveryCodes' => is_array($twoFactorRecoveryCodes) ? $twoFactorRecoveryCodes : [],
        ]);
    }
}
