<?php

namespace App\Jobs;

use App\Models\Solicitud;
use App\Services\TelegramBotService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarNotificacionSolicitudTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $solicitudId,
    ) {}

    public function handle(TelegramBotService $bot, TelegramService $telegram): void
    {
        $solicitud = Solicitud::with(['user', 'programacion.curso'])->find($this->solicitudId);

        if (!$solicitud || !$solicitud->user || !$solicitud->user->telegram_chat_id) {
            return;
        }

        $telegram->sendMessage(
            $solicitud->user->telegram_chat_id,
            $bot->mensajeCambioEstado($solicitud),
        );
    }
}
