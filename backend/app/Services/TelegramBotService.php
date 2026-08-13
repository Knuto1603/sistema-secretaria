<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramBotService
{
    private const MAX_SOLICITUDES_RESUMEN = 5;
    private const CODIGO_TTL_MINUTOS = 10;

    private const ESTADO_ICONOS = [
        'pendiente'   => '🕐',
        'en_revision' => '🟡',
        'aprobada'    => '✅',
        'rechazada'   => '❌',
        'apelado'     => '🔁',
    ];

    private const ESTADO_LABELS = [
        'pendiente'   => 'Pendiente',
        'en_revision' => 'En revisión',
        'aprobada'    => 'Aprobada',
        'rechazada'   => 'Rechazada',
        'apelado'     => 'Apelada',
    ];

    public function __construct(private readonly TelegramService $telegram) {}

    /**
     * Genera un código de un solo uso (TTL corto) para vincular la cuenta del alumno con Telegram.
     */
    public function generarCodigoVinculacion(User $user): string
    {
        $codigo = Str::upper(Str::random(6));
        Cache::put("telegram_link:{$codigo}", $user->id, now()->addMinutes(self::CODIGO_TTL_MINUTOS));

        return $codigo;
    }

    public function desvincular(User $user): void
    {
        $user->update(['telegram_chat_id' => null, 'telegram_linked_at' => null]);
    }

    /**
     * Procesa un update entrante del webhook de Telegram.
     */
    public function procesarUpdate(array $update): void
    {
        $message = $update['message'] ?? null;

        if (!$message || !isset($message['text'], $message['chat']['id'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $texto  = trim($message['text']);

        if (!Str::startsWith($texto, '/start')) {
            $this->telegram->sendMessage(
                $chatId,
                'Este bot solo envía notificaciones sobre tus solicitudes de cupo extra. Para vincular tu cuenta, ve a tu Perfil en el sistema.'
            );
            return;
        }

        $codigo = strtoupper(trim(Str::after($texto, '/start')));

        if ($codigo === '') {
            $this->telegram->sendMessage(
                $chatId,
                'Para vincular tu cuenta, usa el botón "Vincular Telegram" desde tu Perfil en el sistema.'
            );
            return;
        }

        $this->vincularConCodigo($chatId, $codigo);
    }

    private function vincularConCodigo(string $chatId, string $codigo): void
    {
        $userId = Cache::get("telegram_link:{$codigo}");

        if (!$userId) {
            $this->telegram->sendMessage(
                $chatId,
                'Este enlace expiró o no es válido. Genera uno nuevo desde tu Perfil en el sistema.'
            );
            return;
        }

        // Uso único: se invalida apenas se consulta, antes de cualquier otra validación.
        Cache::forget("telegram_link:{$codigo}");

        $user = User::find($userId);

        if (!$user) {
            $this->telegram->sendMessage(
                $chatId,
                'No pudimos encontrar tu cuenta. Genera un nuevo código desde tu Perfil.'
            );
            return;
        }

        try {
            $user->update(['telegram_chat_id' => $chatId, 'telegram_linked_at' => now()]);
        } catch (\Throwable) {
            // telegram_chat_id es unique: este chat ya está vinculado a otra cuenta.
            $this->telegram->sendMessage(
                $chatId,
                'Este chat de Telegram ya está vinculado a otra cuenta del sistema.'
            );
            return;
        }

        $this->telegram->sendMessage($chatId, $this->mensajeBienvenida($user));
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, Solicitud>, total: int}
     */
    public function resumenSolicitudes(User $user): array
    {
        return [
            'items' => $user->solicitudes()
                ->with('programacion.curso')
                ->orderByDesc('created_at')
                ->limit(self::MAX_SOLICITUDES_RESUMEN)
                ->get(),
            'total' => $user->solicitudes()->count(),
        ];
    }

    public function mensajeBienvenida(User $user): string
    {
        ['items' => $items, 'total' => $total] = $this->resumenSolicitudes($user);

        $texto = "✅ ¡Cuenta vinculada correctamente!\n\n"
            . "Hola <b>{$user->name}</b>, a partir de ahora te avisaré aquí cuando haya novedades en tus solicitudes de cupo extra.\n\n"
            . "📋 <b>Tus solicitudes actuales:</b>\n";

        if ($items->isEmpty()) {
            return $texto . 'Actualmente no tienes solicitudes registradas.';
        }

        foreach ($items as $i => $solicitud) {
            $texto .= $this->formatearLineaSolicitud($i + 1, $solicitud);
        }

        if ($total > self::MAX_SOLICITUDES_RESUMEN) {
            $restantes = $total - self::MAX_SOLICITUDES_RESUMEN;
            $texto .= "\nY {$restantes} solicitud(es) más. Ingresa al sistema para verlas todas.";
        }

        return $texto;
    }

    /**
     * Mensaje enviado cuando cambia el estado de una solicitud (admin/secretaria).
     */
    public function mensajeCambioEstado(Solicitud $solicitud): string
    {
        $curso  = $solicitud->programacion?->curso?->nombre ?? 'tu curso';
        $icono  = self::ESTADO_ICONOS[$solicitud->estado] ?? '•';
        $estado = self::ESTADO_LABELS[$solicitud->estado] ?? $solicitud->estado;

        $texto = "🔔 <b>Actualización de tu solicitud</b>\n\n"
            . "Curso: <b>{$curso}</b>\n"
            . "Nuevo estado: {$icono} {$estado}\n";

        if (!empty($solicitud->observaciones_admin)) {
            $texto .= "📝 Observación: {$solicitud->observaciones_admin}\n";
        }

        return $texto . "\nIngresa al sistema para más detalles.";
    }

    private function formatearLineaSolicitud(int $numero, Solicitud $solicitud): string
    {
        $curso  = $solicitud->programacion?->curso?->nombre ?? 'Curso';
        $icono  = self::ESTADO_ICONOS[$solicitud->estado] ?? '•';
        $estado = self::ESTADO_LABELS[$solicitud->estado] ?? $solicitud->estado;

        $linea = "{$numero}. <b>{$curso}</b> — {$icono} {$estado}\n";

        if (!empty($solicitud->observaciones_admin)) {
            $linea .= "   Observación: {$solicitud->observaciones_admin}\n";
        }

        return $linea;
    }
}
