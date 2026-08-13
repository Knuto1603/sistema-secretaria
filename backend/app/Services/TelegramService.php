<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://api.telegram.org/bot' . config('telegram.bot_token');
    }

    /**
     * Envía un mensaje de texto a un chat de Telegram.
     */
    public function sendMessage(string $chatId, string $texto): bool
    {
        $response = Http::timeout(10)->post("{$this->baseUrl}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $texto,
            'parse_mode' => 'HTML',
        ]);

        if (!$response->successful()) {
            Log::error('TelegramService: error al enviar mensaje', [
                'chat_id' => $chatId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        }

        return $response->successful();
    }

    /**
     * Registra la URL del webhook y el secret token contra los servidores de Telegram.
     * Se ejecuta una vez por deploy/dominio via el comando artisan telegram:set-webhook.
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        $response = Http::timeout(10)->post("{$this->baseUrl}/setWebhook", [
            'url'          => $url,
            'secret_token' => $secretToken,
        ]);

        return $response->json() ?? [];
    }
}
