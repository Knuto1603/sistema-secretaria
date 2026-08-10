<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DevService;
use App\Transformers\DevTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DevController extends Controller
{
    public function __construct(private readonly DevService $devService) {}

    // =========================================================================
    // GET /dev/health
    // =========================================================================
    public function health(): JsonResponse
    {
        $data = $this->devService->getHealth();

        return $this->success($data, 'Estado del sistema obtenido');
    }

    // =========================================================================
    // GET /dev/activity-logs
    // =========================================================================
    public function activityLogs(Request $request): JsonResponse
    {
        $paginator = $this->devService->getActivityLogs($request->all());

        $items = $paginator->getCollection()
            ->map(fn($log) => (array) DevTransformer::toActivityLogDTO($log))
            ->toArray();

        return $this->paginated($items, $paginator, 'Logs de actividad');
    }

    // =========================================================================
    // GET /dev/email-logs
    // =========================================================================
    public function emailLogs(Request $request): JsonResponse
    {
        $paginator = $this->devService->getEmailLogs($request->all());

        $items = $paginator->getCollection()->map(function ($otp) {
            return [
                'id'          => $otp->id,
                'purpose'     => $otp->purpose,
                'code'        => $otp->code,
                'user'        => $otp->user ? ['id' => $otp->user->id, 'name' => $otp->user->name] : null,
                'enviado_a'   => $otp->user
                    ? ($otp->user->codigo_universitario
                        ? "{$otp->user->codigo_universitario}@alumnos.unp.edu.pe"
                        : $otp->user->email)
                    : null,
                'expires_at'  => $otp->expires_at?->toISOString(),
                'verified_at' => $otp->verified_at?->toISOString(),
                'usado'       => $otp->isUsed(),
                'expirado'    => $otp->isExpired(),
                'created_at'  => $otp->created_at->toISOString(),
            ];
        })->toArray();

        return $this->paginated($items, $paginator, 'Logs de correos OTP');
    }

    // =========================================================================
    // GET /dev/settings
    // =========================================================================
    public function getSettings(): JsonResponse
    {
        $settings = $this->devService->getSystemSettings();

        return $this->success($settings, 'Configuraciones del sistema');
    }

    // =========================================================================
    // PATCH /dev/settings/{key}
    // =========================================================================
    public function updateSetting(Request $request, string $key): JsonResponse
    {
        $request->validate(['value' => 'required|string|max:1000']);

        $setting = $this->devService->updateSetting($key, $request->input('value'));

        return $this->success($setting, 'Configuración actualizada');
    }

    // =========================================================================
    // POST /dev/maintenance/cache-clear
    // =========================================================================
    public function clearCache(): JsonResponse
    {
        $this->devService->clearCache();

        return $this->success(null, 'Caché limpiado correctamente');
    }

    // =========================================================================
    // POST /dev/maintenance/logs-clear
    // =========================================================================
    public function clearLogs(): JsonResponse
    {
        $count = $this->devService->clearLogs();

        return $this->success(['files_cleared' => $count], "Se limpiaron {$count} archivo(s) de log");
    }

    // =========================================================================
    // GET /dev/routes
    // =========================================================================
    public function routes(): JsonResponse
    {
        $routes = $this->devService->getRoutes();

        return $this->success($routes, 'Rutas del sistema');
    }

    // =========================================================================
    // GET /dev/mail/config
    // =========================================================================
    public function mailConfig(): JsonResponse
    {
        return $this->success([
            // Valores que usa Laravel (vía config cache o mail.php)
            'config_mailer'   => config('mail.default'),
            'config_host'     => config('mail.mailers.smtp.host'),
            'config_port'     => config('mail.mailers.smtp.port'),
            'config_username' => config('mail.mailers.smtp.username'),
            'config_from'     => config('mail.from.address'),

            // Dotenv (lee .env o variable de entorno vía repositorio Laravel)
            'env_host'        => env('MAIL_HOST'),
            'env_port'        => env('MAIL_PORT'),

            // PHP nativo — independiente de Laravel/Dotenv
            'getenv_host'     => getenv('MAIL_HOST'),
            'getenv_port'     => getenv('MAIL_PORT'),
            '_env_host'       => $_ENV['MAIL_HOST']  ?? '(no está en $_ENV)',
            '_server_host'    => $_SERVER['MAIL_HOST'] ?? '(no está en $_SERVER)',

            // ¿Existe archivo .env en disco?
            'dotenv_file'     => file_exists(base_path('.env')) ? 'SÍ existe' : 'NO existe',

            // ¿Existe config cache?
            'config_cached'   => file_exists(base_path('bootstrap/cache/config.php')) ? 'SÍ hay cache' : 'NO hay cache',
        ], 'Diagnóstico de configuración de correo');
    }

    // =========================================================================
    // POST /dev/mail/test
    // =========================================================================
    public function testMail(Request $request): JsonResponse
    {
        $request->validate([
            'destinatario' => ['required', 'email'],
        ]);

        try {
            $result = $this->devService->testMail($request->input('destinatario'));
            return $this->success($result, 'Correo de prueba enviado correctamente');
        } catch (Throwable $e) {
            return $this->error('Error al enviar el correo: ' . $e->getMessage(), 422);
        }
    }

    // =========================================================================
    // POST /dev/impersonate/{userId}
    // =========================================================================
    public function impersonate(string $userId): JsonResponse
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        if ($currentUser->id === $userId) {
            return $this->error('No puedes impersonarte a ti mismo', 422);
        }

        $result = $this->devService->impersonateUser($userId);

        return $this->success($result, 'Impersonación iniciada');
    }

    // =========================================================================
    // DELETE /dev/impersonate
    // =========================================================================
    public function stopImpersonation(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->devService->stopImpersonation($user);

        return $this->success(null, 'Impersonación finalizada');
    }

    // =========================================================================
    // GET /dev/error-logs
    // =========================================================================
    public function errorLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['level', 'search', 'desde', 'hasta', 'page', 'per_page']);
        $result  = $this->devService->getErrorLogs($filters);

        return $this->success($result, 'Logs de errores');
    }

    // =========================================================================
    // GET /dev/database/export
    // =========================================================================
    public function exportDatabase(): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->devService->exportDatabase();
        } catch (Throwable $e) {
            return $this->error('Error al exportar la base de datos: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // POST /dev/database/import
    // =========================================================================
    public function importDatabase(Request $request): JsonResponse
    {
        $request->validate([
            'archivo'      => 'required|file|max:204800',
            'confirmacion' => 'required|string',
        ]);

        if ($request->input('confirmacion') !== 'RESTAURAR BASE DE DATOS') {
            return $this->error('Frase de confirmación incorrecta', 422);
        }

        try {
            $result = $this->devService->importDatabase($request->file('archivo'));

            ActivityLog::create([
                'user_id'            => Auth::id(),
                'accion'             => 'restaurar_base_de_datos',
                'modelo'             => 'Database',
                'modelo_id'          => null,
                'valores_anteriores' => ['backup' => $result['backup_automatico']],
                'valores_nuevos'     => ['archivo' => $result['archivo_restaurado']],
                'ip'                 => $request->ip(),
            ]);

            return $this->success($result, 'Base de datos restaurada correctamente');
        } catch (Throwable $e) {
            return $this->error('Error al restaurar: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // POST /dev/estudiantes/limpiar
    // =========================================================================
    public function limpiarEstudiantes(Request $request): JsonResponse
    {
        $request->validate([
            'confirmacion' => 'required|string',
        ]);

        if ($request->input('confirmacion') !== 'ELIMINAR TODOS LOS ESTUDIANTES') {
            return $this->error('Frase de confirmación incorrecta', 422);
        }

        try {
            $eliminados = $this->devService->limpiarEstudiantes();

            ActivityLog::create([
                'user_id'            => Auth::id(),
                'accion'             => 'limpiar_estudiantes',
                'modelo'             => 'User',
                'modelo_id'          => null,
                'valores_anteriores' => ['total_eliminados' => $eliminados],
                'valores_nuevos'     => null,
                'ip'                 => $request->ip(),
            ]);

            return $this->success(['eliminados' => $eliminados], "Se eliminaron {$eliminados} estudiante(s)");
        } catch (Throwable $e) {
            return $this->error('Error al eliminar los estudiantes: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // GET /dev/database/backups
    // =========================================================================
    public function listBackups(): JsonResponse
    {
        $backups = $this->devService->listBackups();
        return $this->success($backups, 'Backups automáticos disponibles');
    }

    // =========================================================================
    // GET /dev/database/backups/{filename}
    // =========================================================================
    public function downloadBackup(string $filename): BinaryFileResponse|JsonResponse
    {
        if (!preg_match('/^[\w\-]+\.sql$/', $filename)) {
            return $this->error('Nombre de archivo inválido', 422);
        }

        try {
            return $this->devService->downloadBackup($filename);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}
