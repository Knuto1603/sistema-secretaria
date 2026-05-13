<?php

namespace App\Services;

use App\Imports\HistorialHtmlImport;
use App\Models\Curso;
use App\Models\HistorialAcademico;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use ZipArchive;

class ImportHistorialesZipService
{
    private int   $estudiantesCreados      = 0;
    private int   $estudiantesActualizados = 0;
    private int   $historialInsertado      = 0;
    private int   $historialActualizado    = 0;
    private int   $cursosNoEncontrados     = 0;
    private array $errores                 = [];

    private ?Role $rolEstudiante = null;

    public function import(UploadedFile $zipFile): array
    {
        return $this->importFromPath($zipFile->getPathname());
    }

    public function importFromPath(string $zipPath): array
    {
        $this->rolEstudiante = Role::where('name', 'estudiante')
                                   ->where('guard_name', 'web')
                                   ->first();

        $tmpDir = sys_get_temp_dir() . '/historiales_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            $this->extraerZip($zipPath, $tmpDir);
            $this->procesarDirectorio($tmpDir);
        } finally {
            $this->eliminarDirectorio($tmpDir);
        }

        return $this->getResumen();
    }

    // ─────────────────────────────────────────
    // Extracción del ZIP
    // ─────────────────────────────────────────

    private function extraerZip(string $zipPath, string $destDir): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \Exception('No se pudo abrir el archivo ZIP.');
        }

        $zip->extractTo($destDir);
        $zip->close();
    }

    // ─────────────────────────────────────────
    // Procesamiento de archivos extraídos
    // ─────────────────────────────────────────

    private function procesarDirectorio(string $dir): void
    {
        $archivos = glob($dir . '/*.htm') ?: [];
        $archivos = array_merge($archivos, glob($dir . '/*.html') ?: []);

        // También buscar en subdirectorios (un nivel)
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $subdir) {
            $archivos = array_merge($archivos, glob($subdir . '/*.htm') ?: []);
            $archivos = array_merge($archivos, glob($subdir . '/*.html') ?: []);
        }

        foreach ($archivos as $archivo) {
            $this->procesarArchivo($archivo);
        }
    }

    private function procesarArchivo(string $rutaArchivo): void
    {
        $nombreArchivo = basename($rutaArchivo);

        try {
            $contenido = file_get_contents($rutaArchivo);

            if ($contenido === false || trim($contenido) === '') {
                $this->errores[] = "{$nombreArchivo}: archivo vacío o ilegible.";
                return;
            }

            $parser = new HistorialHtmlImport();
            $parser->parse($contenido);

            $codigo = $parser->getCodigo();
            $nombre = $parser->getNombre();

            // Fallback: el nombre del archivo ES el código universitario (ej. 0502021001.htm)
            if (!$codigo || !preg_match('/^\d{10}$/', $codigo)) {
                $codigoDelArchivo = pathinfo($nombreArchivo, PATHINFO_FILENAME);
                if (preg_match('/^\d{10}$/', $codigoDelArchivo)) {
                    $codigo = $codigoDelArchivo;
                } else {
                    $this->errores[] = "{$nombreArchivo}: no se pudo extraer código universitario.";
                    return;
                }
            }

            DB::transaction(function () use ($codigo, $nombre, $parser, $nombreArchivo) {
                $user = $this->crearOActualizarEstudiante($codigo, $nombre);
                $this->guardarHistorial($user, $parser->getCursos(), $nombreArchivo);
                $user->update(['ultima_actualizacion_historial' => now()]);
            });

        } catch (\Throwable $e) {
            $this->errores[] = "{$nombreArchivo}: " . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────
    // Estudiante
    // ─────────────────────────────────────────

    private function crearOActualizarEstudiante(string $codigo, string $nombre): User
    {
        $existe = User::where('codigo_universitario', $codigo)->exists();

        $updates = [
            'tipo_usuario' => 'estudiante',
            'email'        => User::generarEmailEstudiante($codigo),
            'activo'       => true,
        ];
        if (!empty($nombre)) {
            $updates['name'] = $nombre;
        }

        $user = User::updateOrCreate(
            ['codigo_universitario' => $codigo],
            $updates
        );

        $user->asignarDatosDesdeCodigoUniversitario();

        if ($this->rolEstudiante && !$user->hasRole('estudiante')) {
            $user->assignRole($this->rolEstudiante);
        }

        if ($existe) {
            $this->estudiantesActualizados++;
        } else {
            $this->estudiantesCreados++;
        }

        return $user;
    }

    // ─────────────────────────────────────────
    // Historial
    // ─────────────────────────────────────────

    private function guardarHistorial(User $user, array $cursos, string $nombreArchivo): void
    {
        foreach ($cursos as $entry) {
            try {
                $curso = Curso::where('codigo', strtoupper($entry['codigo']))->first();

                if (!$curso) {
                    $this->cursosNoEncontrados++;
                    continue;
                }

                $values = [
                    'fuente'   => 'importado',
                    'creditos' => $entry['creditos'],
                    'nota'     => $entry['nota'],
                    'tipo'     => $entry['tipo'],
                ];

                $semestre = $entry['semestre'];

                if ($semestre === null) {
                    // Para convalidados sin semestre: evitar duplicados en MySQL
                    // (el UNIQUE INDEX con NULL no previene duplicados)
                    $existing = HistorialAcademico::where('user_id', $user->id)
                        ->where('curso_id', $curso->id)
                        ->whereNull('semestre')
                        ->first();

                    if ($existing) {
                        $existing->update($values);
                        $this->historialActualizado++;
                    } else {
                        HistorialAcademico::create(array_merge($values, [
                            'user_id'  => $user->id,
                            'curso_id' => $curso->id,
                            'semestre' => null,
                        ]));
                        $this->historialInsertado++;
                    }
                } else {
                    $result = HistorialAcademico::updateOrCreate(
                        [
                            'user_id'  => $user->id,
                            'curso_id' => $curso->id,
                            'semestre' => $semestre,
                        ],
                        $values
                    );

                    if ($result->wasRecentlyCreated) {
                        $this->historialInsertado++;
                    } else {
                        $this->historialActualizado++;
                    }
                }

            } catch (\Throwable $e) {
                $this->errores[] = "{$nombreArchivo} / {$entry['codigo']}: " . $e->getMessage();
            }
        }
    }

    // ─────────────────────────────────────────
    // Limpieza de directorio temporal
    // ─────────────────────────────────────────

    private function eliminarDirectorio(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $ruta = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($ruta) ? $this->eliminarDirectorio($ruta) : unlink($ruta);
        }

        rmdir($dir);
    }

    // ─────────────────────────────────────────
    // Resumen
    // ─────────────────────────────────────────

    public function getResumen(): array
    {
        return [
            'estudiantes_creados'       => $this->estudiantesCreados,
            'estudiantes_actualizados'  => $this->estudiantesActualizados,
            'historial_insertado'       => $this->historialInsertado,
            'historial_actualizado'     => $this->historialActualizado,
            'cursos_no_encontrados'     => $this->cursosNoEncontrados,
            'errores'                   => count($this->errores),
            'detalle_errores'           => $this->errores,
        ];
    }
}
