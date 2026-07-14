<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OtpCode;
use App\Models\SystemSetting;
use App\Models\User;
use App\Transformers\DevTransformer;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    // =========================================================================
    // DATABASE BACKUP / RESTORE
    // =========================================================================

    public function exportDatabase(): BinaryFileResponse
    {
        $dbName  = config('database.connections.' . config('database.default') . '.database');
        $filename = 'backup_' . $dbName . '_' . now()->format('Y-m-d_His') . '.sql';
        $tempFile = tempnam(sys_get_temp_dir(), 'secretaria_backup_');

        $this->generateSqlDump($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/octet-stream',
        ])->deleteFileAfterSend(true);
    }

    public function importDatabase(UploadedFile $file): array
    {
        $backupDir = storage_path('app/db-backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $dbName     = config('database.connections.' . config('database.default') . '.database');
        $backupName = 'auto_' . $dbName . '_' . now()->format('Y-m-d_His') . '.sql';
        $backupPath = $backupDir . '/' . $backupName;

        $this->generateSqlDump($backupPath);

        $content = file_get_contents($file->getRealPath());
        $originalName = $file->getClientOriginalName();

        if (str_ends_with($originalName, '.gz')) {
            $content = gzdecode($content);
            if ($content === false) {
                throw new \RuntimeException('No se pudo descomprimir el archivo. Verifica que sea un .gz válido.');
            }
        }

        $this->executeSqlContent($content);

        return [
            'backup_automatico'  => $backupName,
            'archivo_restaurado' => $originalName,
        ];
    }

    public function listBackups(): array
    {
        $backupDir = storage_path('app/db-backups');
        if (!is_dir($backupDir)) {
            return [];
        }

        $files   = glob($backupDir . '/*.sql') ?: [];
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'nombre'     => basename($file),
                'tamano_kb'  => round(filesize($file) / 1024, 1),
                'creado_at'  => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        usort($backups, fn($a, $b) => strcmp($b['creado_at'], $a['creado_at']));

        return array_slice($backups, 0, 20);
    }

    public function downloadBackup(string $filename): BinaryFileResponse
    {
        $path = storage_path('app/db-backups/' . $filename);

        if (!file_exists($path)) {
            throw new \RuntimeException('Backup no encontrado.');
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function generateSqlDump(string $outputPath): void
    {
        $pdo    = DB::getPdo();
        $dbName = config('database.connections.' . config('database.default') . '.database');
        $handle = fopen($outputPath, 'w');

        fwrite($handle, "-- Sistema Secretaría FII-UNP\n");
        fwrite($handle, "-- Base de datos: {$dbName}\n");
        fwrite($handle, "-- Generado: " . now()->toDateTimeString() . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            fwrite($handle, "-- Tabla: `{$table}`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
            fwrite($handle, $create[1] . ";\n\n");

            $stmt    = $pdo->query("SELECT * FROM `{$table}`");
            $columns = null;
            $batch   = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($columns === null) {
                    $columns = '`' . implode('`, `', array_keys($row)) . '`';
                }
                $escaped = array_map(
                    fn($val) => $val === null ? 'NULL' : $pdo->quote((string) $val),
                    $row
                );
                $batch[] = '(' . implode(', ', $escaped) . ')';

                if (count($batch) >= 500) {
                    fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    private function executeSqlContent(string $sql): void
    {
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $statements = array_filter(
            array_map('trim', explode(";\n", $sql)),
            fn($s) => !empty(trim($s))
        );

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if (!empty($trimmed)) {
                    DB::unprepared($trimmed);
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    // =========================================================================
    // ERROR LOGS
    // =========================================================================

    public function getErrorLogs(array $filters): array
    {
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile) || filesize($logFile) === 0) {
            return ['items' => [], 'total' => 0, 'file_size_kb' => 0];
        }

        $fileSizeKb = round(filesize($logFile) / 1024, 1);

        // Lee las últimas N KB para no cargar archivos enormes en memoria
        $maxBytes = 2 * 1024 * 1024; // 2 MB
        $handle   = fopen($logFile, 'r');
        $fileSize = filesize($logFile);

        if ($fileSize > $maxBytes) {
            fseek($handle, -$maxBytes, SEEK_END);
            fgets($handle); // descartar línea parcial
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        $entries = $this->parseLogEntries($content);

        // Filtrar por nivel
        if (!empty($filters['level'])) {
            $level = strtoupper($filters['level']);
            $entries = array_values(array_filter($entries, fn($e) => $e['level'] === $level));
        }

        // Filtrar por búsqueda de texto
        if (!empty($filters['search'])) {
            $needle  = strtolower($filters['search']);
            $entries = array_values(array_filter(
                $entries,
                fn($e) => str_contains(strtolower($e['message']), $needle)
            ));
        }

        // Filtrar por fecha
        if (!empty($filters['desde'])) {
            $entries = array_values(array_filter($entries, fn($e) => $e['timestamp'] >= $filters['desde']));
        }

        if (!empty($filters['hasta'])) {
            $hasta   = $filters['hasta'] . ' 23:59:59';
            $entries = array_values(array_filter($entries, fn($e) => $e['timestamp'] <= $hasta));
        }

        // Ordenar más recientes primero
        $entries = array_reverse($entries);

        $total   = count($entries);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;
        $items   = array_slice($entries, $offset, $perPage);

        return [
            'items'         => $items,
            'total'         => $total,
            'per_page'      => $perPage,
            'current_page'  => $page,
            'last_page'     => max(1, (int) ceil($total / $perPage)),
            'file_size_kb'  => $fileSizeKb,
        ];
    }

    private function parseLogEntries(string $content): array
    {
        // Patrón: [YYYY-MM-DD HH:MM:SS] env.LEVEL: message {context}
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.*?)(?=^\[\d{4}|\z)/ms';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $entries = [];

        foreach ($matches as $match) {
            $timestamp = $match[1];
            $level     = strtoupper($match[2]);
            $body      = trim($match[3]);

            // Separar mensaje de contexto JSON y stack trace
            $jsonStart = $this->findJsonStart($body);
            if ($jsonStart !== false) {
                $message  = trim(substr($body, 0, $jsonStart));
                $rest     = substr($body, $jsonStart);
                $context  = $this->extractJson($rest);
                $trace    = $this->extractStackTrace($body);
            } else {
                $message  = $body;
                $context  = null;
                $trace    = $this->extractStackTrace($body);
            }

            // Extraer excepción del contexto si existe
            $exception = null;
            if ($context && isset($context['exception'])) {
                $exception = is_string($context['exception'])
                    ? $context['exception']
                    : json_encode($context['exception']);
                unset($context['exception']);
            }

            $entries[] = [
                'timestamp' => $timestamp,
                'level'     => $level,
                'message'   => $message,
                'exception' => $exception,
                'context'   => $context && !empty($context) ? $context : null,
                'trace'     => $trace,
            ];
        }

        return $entries;
    }

    private function findJsonStart(string $text): int|false
    {
        $pos = strpos($text, ' {');
        return $pos !== false ? $pos + 1 : false;
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        // Busca el final del JSON balanceando llaves
        $depth  = 0;
        $end    = $start;
        $len    = strlen($text);
        $inStr  = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\' && $inStr) {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inStr = !$inStr;
                continue;
            }
            if (!$inStr) {
                if ($ch === '{') $depth++;
                elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
        }

        $json = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractStackTrace(string $body): ?string
    {
        $pos = strpos($body, "\n#");
        if ($pos === false) {
            $pos = strpos($body, "\r\n#");
        }
        if ($pos === false) {
            return null;
        }

        $trace = substr($body, $pos + 1);
        // Limpiar artefactos de log al final
        $trace = preg_replace('/\[stacktrace\]\s*$/i', '', $trace);
        return trim($trace) ?: null;
    }

    // =========================================================================
    // TEST MAIL
    // =========================================================================

    public function testMail(string $destinatario): array
    {
        $inicio = microtime(true);

        Mail::raw(
            "Correo de prueba del sistema Secretaría FII-UNP.\n\n" .
            "Si recibes este mensaje, el servicio de correo está configurado correctamente.\n\n" .
            "Fecha: " . now()->toDateTimeString() . "\n" .
            "Mailer: " . config('mail.default') . "\n" .
            "Host: " . config('mail.mailers.smtp.host', 'N/A') . "\n" .
            "Puerto: " . config('mail.mailers.smtp.port', 'N/A'),
            fn ($msg) => $msg
                ->to($destinatario)
                ->subject('[Secretaría FII] Prueba de correo')
                ->from(config('mail.from.address'), config('mail.from.name'))
        );

        $ms = round((microtime(true) - $inicio) * 1000);

        return [
            'enviado_a'  => $destinatario,
            'mailer'     => config('mail.default'),
            'host'       => config('mail.mailers.smtp.host', 'N/A'),
            'puerto'     => config('mail.mailers.smtp.port', 'N/A'),
            'from'       => config('mail.from.address'),
            'tiempo_ms'  => $ms,
        ];
    }
}
