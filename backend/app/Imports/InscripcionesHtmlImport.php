<?php

namespace App\Imports;

use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\ProgramacionAcademica;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Importa inscripciones de alumnos por curso desde el reporte HTML del SIGA
 * ("ALUMNOS POR CURSO XXXXXX.htm" — un <TABLE> por curso).
 */
class InscripcionesHtmlImport
{
    private int   $programacionesProcesadas = 0;
    private int   $inscripcionesCreadas     = 0;
    private int   $inscripcionesActualizadas = 0;
    private int   $alumnosNuevos            = 0;
    private array $noEncontrados            = []; // claves SIGA sin match en BD
    private array $errores                  = [];

    private ?Role $rolEstudiante = null;

    public function import(string $filePath): void
    {
        $this->rolEstudiante = Role::where('name', 'estudiante')
                                   ->where('guard_name', 'web')
                                   ->first();

        $content = file_get_contents($filePath);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();

        $xpath  = new DOMXPath($dom);
        $tables = $xpath->query('//body/table');

        foreach ($tables as $table) {
            $this->procesarTabla($table, $xpath);
        }
    }

    private function procesarTabla(\DOMElement $table, DOMXPath $xpath): void
    {
        $clave      = null;
        $semestre   = null;
        $alumnos    = []; // [{codigo, nombre}]

        $rows = $xpath->query('.//tr', $table);

        foreach ($rows as $tr) {
            $text = preg_replace('/\s+/', ' ', trim($tr->textContent));
            if ($text === '') {
                continue;
            }

            // --- Extraer CLAVE y SEMESTRE ---
            // La fila CURSO contiene el semestre: "CURSO : AL4401 - ... SEMESTRE: 2026-0"
            if (str_contains($text, 'SEMESTRE:') || str_contains($text, 'SEMESTRE :')) {
                if (preg_match('/SEMESTRE\s*:\s*(\S+)/', $text, $m)) {
                    $semestre = trim($m[1]);
                }
            }

            // La fila CLAVE contiene la clave SIGA: "CLAVE : 5081 SECCION : ..."
            if (str_contains($text, 'CLAVE') && str_contains($text, 'SECCION')) {
                if (preg_match('/CLAVE\s*:\s*(\d+)/', $text, $m)) {
                    $clave = trim($m[1]);
                }
            }

            // --- Detectar fila de alumno: empieza con 10 dígitos ---
            if (preg_match('/^(\d{10})\s+(.+)$/', $text, $m)) {
                $alumnos[] = [
                    'codigo' => $m[1],
                    'nombre' => mb_convert_case(trim($m[2]), MB_CASE_TITLE, 'UTF-8'),
                ];
            }
        }

        if (!$clave || !$semestre || empty($alumnos)) {
            return;
        }

        $this->guardarInscripciones($clave, $semestre, $alumnos);
    }

    private function guardarInscripciones(string $clave, string $semestre, array $alumnos): void
    {
        // Buscar el periodo por nombre (e.g., "2026-0")
        $periodo = Periodo::where('nombre', $semestre)->first();

        if (!$periodo) {
            $this->noEncontrados[] = "CLAVE {$clave}: periodo '{$semestre}' no existe en BD.";
            return;
        }

        // Buscar la programacion por clave + periodo
        $programacion = ProgramacionAcademica::where('clave', $clave)
            ->where('periodo_id', $periodo->id)
            ->first();

        if (!$programacion) {
            $this->noEncontrados[] = "CLAVE {$clave} (semestre {$semestre}): no se encontró en programación académica.";
            return;
        }

        $this->programacionesProcesadas++;

        DB::transaction(function () use ($programacion, $periodo, $alumnos) {
            foreach ($alumnos as $alumnoData) {
                try {
                    $user = $this->crearOObtenerEstudiante($alumnoData['codigo'], $alumnoData['nombre']);

                    $result = Inscripcion::updateOrCreate(
                        [
                            'programacion_id' => $programacion->id,
                            'user_id'         => $user->id,
                        ],
                        [
                            'periodo_id' => $periodo->id,
                            'fuente'     => 'siga',
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $this->inscripcionesCreadas++;
                    } else {
                        $this->inscripcionesActualizadas++;
                    }
                } catch (\Throwable $e) {
                    $this->errores[] = "CLAVE {$programacion->clave} / {$alumnoData['codigo']}: " . $e->getMessage();
                }
            }

            // Recalcular n_inscritos
            $count = Inscripcion::where('programacion_id', $programacion->id)->count();
            $programacion->update(['n_inscritos' => $count]);
        });
    }

    private function crearOObtenerEstudiante(string $codigo, string $nombre): User
    {
        $existe = User::where('codigo_universitario', $codigo)->exists();

        $user = User::updateOrCreate(
            ['codigo_universitario' => $codigo],
            [
                'name'         => $nombre,
                'tipo_usuario' => 'estudiante',
                'email'        => User::generarEmailEstudiante($codigo),
                'activo'       => true,
            ]
        );

        $user->asignarDatosDesdeCodigoUniversitario();

        if ($this->rolEstudiante && !$user->hasRole('estudiante')) {
            $user->assignRole($this->rolEstudiante);
        }

        if (!$existe) {
            $this->alumnosNuevos++;
        }

        return $user;
    }

    public function getResumen(): array
    {
        return [
            'programaciones_procesadas'    => $this->programacionesProcesadas,
            'inscripciones_creadas'        => $this->inscripcionesCreadas,
            'inscripciones_actualizadas'   => $this->inscripcionesActualizadas,
            'alumnos_nuevos'               => $this->alumnosNuevos,
            'no_encontrados'               => $this->noEncontrados,
            'errores'                      => count($this->errores),
            'detalle_errores'              => $this->errores,
        ];
    }
}
