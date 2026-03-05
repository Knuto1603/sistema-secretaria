<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DevService;
use App\Transformers\DevTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
