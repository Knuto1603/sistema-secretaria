<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OtpCode;
use App\Models\SystemSetting;
use App\Models\User;
use App\Transformers\DevTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DevService
{
    // =========================================================================
    // HEALTH
    // =========================================================================

    public function getHealth(): array
    {
        $dbOk = true;
        try {
            DB::select('SELECT 1');
        } catch (\Exception) {
            $dbOk = false;
        }

        $diskFree  = @disk_free_space(base_path()) ?: 0;
        $diskTotal = @disk_total_space(base_path()) ?: 0;
        $diskPct   = $diskTotal > 0 ? (int) round((1 - $diskFree / $diskTotal) * 100) : 0;

        $data = [
            'database'        => $dbOk,
            'disk_free_gb'    => round($diskFree / 1_073_741_824, 2),
            'disk_total_gb'   => round($diskTotal / 1_073_741_824, 2),
            'disk_pct'        => $diskPct,
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment'     => app()->environment(),
            'timestamp'       => now()->toISOString(),
        ];

        $dto = DevTransformer::toHealthDTO($data);

        return (array) $dto;
    }

    // =========================================================================
    // ACTIVITY LOGS
    // =========================================================================

    public function getActivityLogs(array $filters): LengthAwarePaginator
    {
        $query = ActivityLog::with('user')->latest();

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['accion'])) {
            $query->where('accion', 'like', '%' . $filters['accion'] . '%');
        }

        if (!empty($filters['modelo'])) {
            $query->where('modelo', 'like', '%' . $filters['modelo'] . '%');
        }

        if (!empty($filters['desde'])) {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }

        if (!empty($filters['hasta'])) {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->paginate($perPage);
    }

    // =========================================================================
    // EMAIL LOGS (otp_codes)
    // =========================================================================

    public function getEmailLogs(array $filters): LengthAwarePaginator
    {
        $query = OtpCode::with('user')->latest();

        if (!empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (!empty($filters['usado'])) {
            if ($filters['usado'] === 'true') {
                $query->whereNotNull('verified_at');
            } elseif ($filters['usado'] === 'false') {
                $query->whereNull('verified_at');
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('codigo_universitario', 'like', "%{$search}%"));
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->paginate($perPage);
    }

    // =========================================================================
    // SYSTEM SETTINGS
    // =========================================================================

    public function getSystemSettings(): array
    {
        $settings = SystemSetting::orderBy('grupo')->orderBy('key')->get();

        return $settings->map(fn($s) => DevTransformer::toSystemSettingDTO($s))->toArray();
    }

    public function updateSetting(string $key, mixed $value): array
    {
        $setting = SystemSetting::findOrFail($key);
        $setting->update(['value' => (string) $value]);

        return (array) DevTransformer::toSystemSettingDTO($setting->fresh());
    }

    // =========================================================================
    // MAINTENANCE
    // =========================================================================

    public function clearCache(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
    }

    public function clearLogs(): int
    {
        $logPath = storage_path('logs');
        $files   = glob($logPath . '/*.log') ?: [];
        $count   = 0;

        foreach ($files as $file) {
            if (is_file($file) && is_writable($file)) {
                file_put_contents($file, '');
                $count++;
            }
        }

        return $count;
    }

    // =========================================================================
    // ROUTES
    // =========================================================================

    public function getRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // Solo rutas de la API
            if (!str_starts_with($uri, 'api/') && $uri !== 'api') {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $routes[] = [
                    'method'     => $method,
                    'uri'        => $uri,
                    'name'       => $route->getName(),
                    'middleware' => $route->middleware(),
                ];
            }
        }

        usort($routes, fn($a, $b) => strcmp($a['uri'], $b['uri']));

        return $routes;
    }

    // =========================================================================
    // IMPERSONATION
    // =========================================================================

    public function impersonateUser(string $userId): array
    {
        $target = User::findOrFail($userId);

        // Revoca tokens de impersonación previos para este target
        $target->tokens()->where('name', 'impersonation')->delete();

        $token = $target->createToken('impersonation')->plainTextToken;

        return [
            'token' => $token,
            'user'  => [
                'id'                   => $target->id,
                'name'                 => $target->name,
                'email'                => $target->email,
                'tipo_usuario'         => $target->tipo_usuario,
                'username'             => $target->username,
                'codigo_universitario' => $target->codigo_universitario,
                'roles'                => $target->getRoleNames()->toArray(),
                'permissions'          => $target->getAllPermissions()->pluck('name')->toArray(),
            ],
        ];
    }

    public function stopImpersonation(User $developer): void
    {
        // El token actual es de impersonación; lo revocamos
        $developer->currentAccessToken()->delete();
    }
}
