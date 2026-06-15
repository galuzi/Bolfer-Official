<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ServiceRequestNotificationService;
use App\Support\View;
use App\Support\ClientContext;
use App\Support\RateLimiter;
use App\Repositories\SettingsRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

final class PageController
{
    public function terms(): void
    {
        View::render('terms');
    }

    public function services(): void
    {
        $settingsRepo = new SettingsRepository();
        $whatsapp = $settingsRepo->get('whatsapp_link', env('WHATSAPP_LINK'));
        $discord = $settingsRepo->get('discord_link', env('DISCORD_LINK'));

        View::render('services', [
            'whatsapp' => $whatsapp,
            'discord' => $discord,
        ]);
    }

    public function servicesSubmit(): void
    {
        $loggedUser = user_session();
        if (!$loggedUser) {
            flash_set('error', 'Faca login para enviar um pedido de servico.');
            redirect('/login');
        }

        $limiter = new RateLimiter();
        $context = ClientContext::summary();
        $rateKey = 'services-submit:' . hash('sha256', (string) ($context['ip_address'] ?? 'unknown'));
        $retryAfter = (int) ($limiter->isBlocked($rateKey) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas solicitacoes enviadas. Aguarde alguns minutos e tente novamente.');
            redirect('/servicos');
        }

        $limiter->hit($rateKey, 8, 1800, 3600);

        $name = trim((string) ($_POST['name'] ?? ''));
        $channel = trim((string) ($_POST['channel'] ?? ''));
        $contact = trim((string) ($_POST['contact'] ?? ''));
        $service = trim((string) ($_POST['service'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));

        if ($name === '' || $channel === '' || $contact === '' || $service === '') {
            flash_set('error', 'Preencha todos os campos obrigatorios.');
            redirect('/servicos');
        }

        $allowedChannels = ['whatsapp', 'discord'];
        if (!in_array($channel, $allowedChannels, true)) {
            flash_set('error', 'Canal de contato invalido.');
            redirect('/servicos');
        }

        if (strlen($name) > 80 || strlen($contact) > 120 || strlen($service) > 120 || strlen($details) > 2000) {
            flash_set('error', 'Um ou mais campos ultrapassaram o limite permitido.');
            redirect('/servicos');
        }

        $payload = [
            'name' => $name,
            'channel' => $channel,
            'contact' => $contact,
            'service' => $service,
            'details' => $details,
            'created_at' => date('c'),
            'ip' => (string) ($context['ip_address'] ?? ''),
            'user_agent' => (string) ($context['user_agent'] ?? ''),
            'account_id' => (int) ($loggedUser['id'] ?? 0),
            'account_username' => (string) ($loggedUser['username'] ?? ''),
            'account_email' => (string) ($loggedUser['email'] ?? ''),
        ];

        $path = dirname(__DIR__, 2) . '/../storage/logs/service_requests.json';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $data = [];
        if (is_file($path)) {
            $raw = file_get_contents($path);
            $data = json_decode($raw ?: '[]', true);
            if (!is_array($data)) {
                $data = [];
            }
        }

        $data[] = $payload;
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $dispatch = (new ServiceRequestNotificationService())->deliver($payload);
        if (!$dispatch['ok']) {
            flash_set('error', 'Pedido salvo, mas nao foi possivel avisar a equipe por e-mail agora. Revise a caixa admin@example.com e o SMTP da no-reply.');
            redirect('/servicos');
        }

        flash_set('success', 'Pedido enviado. Nossa equipe vai responder em breve.');
        redirect('/servicos');
    }

    public function donations(): void
    {
        $donationMinAmount = (float) env('DONATION_MIN_AMOUNT', '1');
        if ($donationMinAmount < 1) {
            $donationMinAmount = 1;
        }

        $donationMaxAmount = (float) env('DONATION_MAX_AMOUNT', '1000');
        if ($donationMaxAmount < $donationMinAmount) {
            $donationMaxAmount = $donationMinAmount;
        }

        View::render('donations', [
            'donationMinAmount' => $donationMinAmount,
            'donationMaxAmount' => $donationMaxAmount,
        ]);
    }

    public function products(): void
    {
        $categories = (new CategoryRepository())->allActive();
        $products = (new ProductRepository())->allActive();

        View::render('products', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
