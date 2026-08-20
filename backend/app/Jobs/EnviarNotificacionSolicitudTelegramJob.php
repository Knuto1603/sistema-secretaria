<?php

namespace App\Jobs;

use App\Models\Solicitud;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EnviarNotificacionSolicitudTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private readonly string $dedupeId;

    /**
     * @param string $tipo 'creada' | 'apelacion' | 'cambio_estado'
     */
    public function __construct(
        private readonly string $solicitudId,
        private readonly string $tipo = 'cambio_estado',
    ) {
        // Identificador propio de este despacho (no del tipo): 'cambio_estado' se repite en
        // cada transicion de una misma solicitud, asi que si el lock se keyeara por
        // solicitudId+tipo, el segundo cambio de estado quedaria bloqueado por el lock que
        // dejo el primero (10 min de TTL). Al generarse una vez en el constructor y quedar
        // serializado con el job, sigue siendo estable si el worker muere y Laravel reejecuta
        // esta misma instancia desde cero (el caso que el lock si debe bloquear).
        $this->dedupeId = (string) Str::uuid();
    }

    public function handle(TelegramBotService $bot): void
    {
        $solicitud = Solicitud::with(['user', 'programacion.curso'])->find($this->solicitudId);

        if (!$solicitud || !$solicitud->user) {
            return;
        }

        // Lock atomico: si el worker muere justo despues de enviar (ej. el contenedor se
        // reinicia por una caida de BD) el job puede quedar sin marcarse como completado y
        // reejecutarse desde cero al volver. Este lock evita reenviar el mismo mensaje.
        $lockKey = "telegram_notif_enviada:{$this->dedupeId}";
        if (!Cache::add($lockKey, true, now()->addMinutes(10))) {
            return;
        }

        $texto = match ($this->tipo) {
            'creada'    => $bot->mensajeSolicitudCreada($solicitud),
            'apelacion' => $bot->mensajeApelacionRecibida($solicitud),
            default     => $bot->mensajeCambioEstado($solicitud),
        };

        if ($this->tipo === 'creada' && $solicitud->constancia_pdf_path
            && Storage::disk('public')->exists($solicitud->constancia_pdf_path)) {
            try {
                $bot->notificarConDocumento(
                    $solicitud->user,
                    $texto,
                    Storage::disk('public')->path($solicitud->constancia_pdf_path)
                );
                return;
            } catch (\Throwable $e) {
                // sendDocument (multipart) puede fallar por red aunque sendMessage funcione
                // (visto en produccion: timeout especifico a la subida del archivo). Mejor
                // que el alumno reciba al menos el texto a que se pierda todo por 3 reintentos.
                Log::warning('EnviarNotificacionSolicitudTelegramJob: fallo al enviar documento, cae a texto', [
                    'solicitud_id' => $this->solicitudId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bot->notificar($solicitud->user, $texto);
    }
}
