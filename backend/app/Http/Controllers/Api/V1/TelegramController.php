<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TelegramBotService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    // =========================================================================
    // WEBHOOK PÚBLICO (llamado por Telegram)
    // =========================================================================

    /**
     * POST /api/telegram/webhook
     *
     * Recibe los updates del bot de Telegram (comando /start para vincular cuenta).
     * Protegido por el header X-Telegram-Bot-Api-Secret-Token, configurado al
     * registrar el webhook con el comando `telegram:set-webhook`.
     *
     * Siempre responde 200, incluso si algo falla internamente, para que
     * Telegram no reintente indefinidamente la misma actualización.
     */
    public function webhook(Request $request): JsonResponse
    {
        if (!$this->validarWebhookSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->bot->procesarUpdate($request->all());
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // VINCULACIÓN (panel del estudiante, Sanctum)
    // =========================================================================

    /**
     * POST /api/me/telegram/generar-vinculo
     *
     * Genera un código de un solo uso y devuelve el deep link para vincular
     * la cuenta del estudiante autenticado con el bot de Telegram.
     */
    public function generarVinculo(Request $request): JsonResponse
    {
        $codigo = $this->bot->generarCodigoVinculacion($request->user());
        $botUsername = config('telegram.bot_username');

        return $this->success([
            'codigo'    => $codigo,
            'deep_link' => "https://t.me/{$botUsername}?start={$codigo}",
            'expira_en_minutos' => 10,
        ], 'Código de vinculación generado');
    }

    /**
     * GET /api/me/telegram/estado
     *
     * Indica si el estudiante autenticado ya vinculó su cuenta con Telegram.
     */
    public function estado(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'vinculado'         => $user->telegram_chat_id !== null,
            'vinculado_desde'   => $user->telegram_linked_at?->toISOString(),
        ], 'Estado de vinculación');
    }

    /**
     * DELETE /api/me/telegram
     *
     * Desvincula la cuenta de Telegram del estudiante autenticado.
     */
    public function desvincular(Request $request): JsonResponse
    {
        $this->bot->desvincular($request->user());

        return $this->success(null, 'Cuenta de Telegram desvinculada');
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function validarWebhookSecret(Request $request): bool
    {
        $secret = config('telegram.webhook_secret');

        if (!$secret) {
            // Si no hay secret configurado, solo en local se permite
            return app()->environment('local');
        }

        return $request->header('X-Telegram-Bot-Api-Secret-Token') === $secret;
    }
}
