<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Forzar IPv4: en algunos contenedores Docker la interfaz IPv6 esta "a medias"
     * (existe pero sin ruta de salida real). curl CLI lo evita con Happy Eyeballs,
     * pero el cliente cURL de PHP puede quedarse colgado intentando esa ruta hasta
     * agotar el timeout completo (visto en produccion: cURL error 28, 0 bytes
     * recibidos, siempre justo en el timeout configurado).
     */
    private const CURL_FORZAR_IPV4 = ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]];

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://api.telegram.org/bot' . config('telegram.bot_token');
    }

    /**
     * Envía un mensaje de texto a un chat de Telegram.
     *
     * @return array Respuesta cruda de la API de Telegram (incluye 'ok' y, en fallos, 'error_code').
     */
    public function sendMessage(string $chatId, string $texto): array
    {
        $response = Http::timeout(10)->withOptions(self::CURL_FORZAR_IPV4)->post("{$this->baseUrl}/sendMessage", [
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

        return $response->json() ?? ['ok' => false];
    }

    /**
     * Envía un documento (archivo) a un chat de Telegram, con un texto opcional como caption.
     *
     * @return array Respuesta cruda de la API de Telegram (incluye 'ok' y, en fallos, 'error_code').
     */
    public function sendDocument(string $chatId, string $absolutePath, string $caption = ''): array
    {
        $response = Http::timeout(20)
            ->withOptions(self::CURL_FORZAR_IPV4)
            ->attach('document', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post("{$this->baseUrl}/sendDocument", [
                'chat_id'    => $chatId,
                'caption'    => $caption,
                'parse_mode' => 'HTML',
            ]);

        if (!$response->successful()) {
            Log::error('TelegramService: error al enviar documento', [
                'chat_id' => $chatId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        }

        return $response->json() ?? ['ok' => false];
    }

    /**
     * Registra la URL del webhook y el secret token contra los servidores de Telegram.
     * Se ejecuta una vez por deploy/dominio via el comando artisan telegram:set-webhook.
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        $response = Http::timeout(10)->withOptions(self::CURL_FORZAR_IPV4)->post("{$this->baseUrl}/setWebhook", [
            'url'          => $url,
            'secret_token' => $secretToken,
        ]);

        return $response->json() ?? [];
    }
}
