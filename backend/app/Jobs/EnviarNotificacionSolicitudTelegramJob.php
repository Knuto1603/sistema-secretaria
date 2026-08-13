<?php

namespace App\Jobs;

use App\Models\Solicitud;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarNotificacionSolicitudTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param string $tipo 'creada' | 'apelacion' | 'cambio_estado'
     */
    public function __construct(
        private readonly string $solicitudId,
        private readonly string $tipo = 'cambio_estado',
    ) {}

    public function handle(TelegramBotService $bot): void
    {
        $solicitud = Solicitud::with(['user', 'programacion.curso'])->find($this->solicitudId);

        if (!$solicitud || !$solicitud->user) {
            return;
        }

        $texto = match ($this->tipo) {
            'creada'    => $bot->mensajeSolicitudCreada($solicitud),
            'apelacion' => $bot->mensajeApelacionRecibida($solicitud),
            default     => $bot->mensajeCambioEstado($solicitud),
        };

        $bot->notificar($solicitud->user, $texto);
    }
}
