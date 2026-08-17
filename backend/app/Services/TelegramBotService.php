<?php

namespace App\Services;

use App\Jobs\EnviarMensajeTelegramJob;
use App\Models\Escuela;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * Envía una notificación asíncrona (disparada por un Job, no por el webhook).
     * Si Telegram responde error_code 403 (el alumno bloqueó al bot), desvincula la cuenta.
     */
    public function notificar(User $user, string $texto): void
    {
        if (!$user->telegram_chat_id) {
            return;
        }

        $resultado = $this->telegram->sendMessage($user->telegram_chat_id, $texto);

        if (!($resultado['ok'] ?? false) && (int) ($resultado['error_code'] ?? 0) === 403) {
            $this->desvincular($user);
        }
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

        match (true) {
            Str::startsWith($texto, '/start') => $this->manejarStart($chatId, $texto),
            Str::startsWith($texto, '/misolicitudes') => $this->manejarMisSolicitudes($chatId),
            default => $this->telegram->sendMessage($chatId, $this->mensajeAyuda()),
        };
    }

    private function manejarStart(string $chatId, string $texto): void
    {
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

    private function manejarMisSolicitudes(string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (!$user) {
            $this->telegram->sendMessage(
                $chatId,
                'Tu cuenta no está vinculada. Ve a tu Perfil en el sistema para vincularla.'
            );
            return;
        }

        $this->telegram->sendMessage($chatId, $this->mensajeMisSolicitudes($user));
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
        return "✅ ¡Cuenta vinculada correctamente!\n\n"
            . "Hola <b>{$user->name}</b>, a partir de ahora te avisaré aquí cuando haya novedades en tus solicitudes.\n\n"
            . $this->construirListaSolicitudes($user);
    }

    /**
     * Respuesta al comando /misolicitudes: mismo listado que la bienvenida, sin el saludo.
     */
    public function mensajeMisSolicitudes(User $user): string
    {
        return $this->construirListaSolicitudes($user);
    }

    private function construirListaSolicitudes(User $user): string
    {
        ['items' => $items, 'total' => $total] = $this->resumenSolicitudes($user);

        $texto = "📋 <b>Tus solicitudes actuales:</b>\n";

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

    /**
     * Confirmación enviada apenas se registra una nueva solicitud.
     */
    public function mensajeSolicitudCreada(Solicitud $solicitud): string
    {
        $curso = $solicitud->programacion?->curso?->nombre ?? 'tu curso';

        return "✅ Recibimos tu solicitud para <b>{$curso}</b>. Te avisaremos por aquí cuando sea revisada.";
    }

    /**
     * Confirmación enviada cuando el alumno apela una solicitud rechazada.
     */
    public function mensajeApelacionRecibida(Solicitud $solicitud): string
    {
        $curso = $solicitud->programacion?->curso?->nombre ?? 'tu curso';

        return "🔁 Registramos tu apelación para <b>{$curso}</b>. La secretaría la revisará nuevamente.";
    }

    public function mensajeAyuda(): string
    {
        return "🤖 <b>Comandos disponibles:</b>\n\n"
            . "/misolicitudes — Consulta el estado de tus solicitudes\n"
            . "/ayuda — Muestra este mensaje\n\n"
            . "Este bot te avisa automáticamente cuando hay novedades en tus solicitudes. Para vincular tu cuenta, ve a tu Perfil en el sistema.";
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

    // =========================================================================
    // PANEL ADMIN
    // =========================================================================

    /**
     * @return array{total_estudiantes: int, vinculados: int, porcentaje: float}
     */
    public function estadisticasVinculacion(): array
    {
        $total = User::estudiantes()->count();
        $vinculados = User::estudiantes()->whereNotNull('telegram_chat_id')->count();

        return [
            'total_estudiantes' => $total,
            'vinculados'        => $vinculados,
            'porcentaje'        => $total > 0 ? round(($vinculados / $total) * 100, 1) : 0.0,
        ];
    }

    public function listarVinculados(
        ?string $search,
        ?string $escuelaCodigo = null,
        ?int $anioIngreso = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQueryVinculados($search, $escuelaCodigo, $anioIngreso)
            ->with('escuela')
            ->orderByDesc('telegram_linked_at')
            ->paginate($perPage);
    }

    /**
     * Envía un mensaje libre a estudiantes vinculados.
     *
     * Si $filtros['user_ids'] viene, se envía solo a esos IDs (selección manual).
     * En caso contrario, se envía a todos los que coincidan con search/escuela_codigo/anio_ingreso.
     *
     * @param array{user_ids?: array, search?: string, escuela_codigo?: string, anio_ingreso?: int} $filtros
     */
    public function enviarMensajeMasivo(string $mensaje, array $filtros): int
    {
        if (!empty($filtros['user_ids'])) {
            $ids = User::estudiantes()
                ->whereNotNull('telegram_chat_id')
                ->whereIn('id', $filtros['user_ids'])
                ->pluck('id');
        } else {
            $ids = $this->baseQueryVinculados(
                $filtros['search'] ?? null,
                $filtros['escuela_codigo'] ?? null,
                $filtros['anio_ingreso'] ?? null,
            )->pluck('id');
        }

        // Texto libre del admin: se escapa porque sendMessage siempre usa parse_mode HTML,
        // y un '<' o '&' suelto rompería el parseo de Telegram (400, mensaje no entregado).
        $textoSeguro = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

        foreach ($ids as $id) {
            EnviarMensajeTelegramJob::dispatch($id, $textoSeguro);
        }

        return $ids->count();
    }

    private function baseQueryVinculados(?string $search, ?string $escuelaCodigo, ?int $anioIngreso)
    {
        return User::estudiantes()
            ->whereNotNull('telegram_chat_id')
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('codigo_universitario', 'like', "%{$search}%");
            }))
            ->when($escuelaCodigo, function ($q) use ($escuelaCodigo) {
                $escuela = Escuela::findByCodigo($escuelaCodigo);
                if ($escuela) {
                    $q->where('escuela_id', $escuela->id);
                }
            })
            ->when($anioIngreso, fn ($q) => $q->where('anio_ingreso', $anioIngreso));
    }
}
