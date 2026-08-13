<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registra la URL del webhook del bot de Telegram contra los servidores de Telegram';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $url = rtrim(config('app.url'), '/') . '/api/telegram/webhook';
        $secret = config('telegram.webhook_secret');

        if (!config('telegram.bot_token') || !$secret) {
            $this->error('Configura TELEGRAM_BOT_TOKEN y TELEGRAM_WEBHOOK_SECRET antes de registrar el webhook.');
            return Command::FAILURE;
        }

        $resultado = $telegram->setWebhook($url, $secret);

        if ($resultado['ok'] ?? false) {
            $this->info("Webhook registrado correctamente: {$url}");
            return Command::SUCCESS;
        }

        $this->error('No se pudo registrar el webhook: ' . ($resultado['description'] ?? 'error desconocido'));
        return Command::FAILURE;
    }
}
