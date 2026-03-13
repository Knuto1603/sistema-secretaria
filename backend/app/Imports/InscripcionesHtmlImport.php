<?php

namespace App\Imports;

use App\Models\Curso;
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
 * ("ALUMNOS POR CURSO XXXXXX.htm").
 *
 * Estrategia de parsing:
 *  - Lee todas las filas del documento en orden
 *  - Detecta cabeceras de curso (SEMESTRE/CURSO/CLAVE) y bloques de alumnos
 *  - Cuando cambia la cabecera, guarda el bloque anterior
 *
 * Estrategia de matching de programación (en orden):
 *  1. clave SIGA + periodo exacto
 *  2. código de curso + periodo exacto
 *  3. código de curso + periodo por búsqueda parcial
 */
class InscripcionesHtmlImport
{
    private int   $programacionesProcesadas  = 0;
    private int   $inscripcionesCreadas      = 0;
    private int   $inscripcionesActualizadas = 0;
    private int   $alumnosNuevos             = 0;
    private array $noEncontrados             = [];
    private array $errores                   = [];

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

        $xpath = new DOMXPath($dom);

        // Leer TODAS las filas del documento en orden (una sola pasada)
        $this->parsearFilas($xpath);
    }

    /**
     * Recorre todas las filas del documento detectando bloques de curso→alumnos.
     * Soporta tanto "una tabla por curso" como "una tabla global".
     */
    private function parsearFilas(DOMXPath $xpath): void
    {
        $semestre    = null;
        $cursoCodigo = null;
        $clave       = null;
        $alumnos     = [];

        $rows = $xpath->query('//tr');

        foreach ($rows as $tr) {
            $text = $this->textoFila($tr, $xpath);

            if ($text === '') {
                continue;
            }

            // ── Detectar SEMESTRE ───────────────────────────────────────
            if (preg_match('/SEMESTRE\s*:?\s*(\d{4}-\d)/i', $text, $m)) {
                // Si venía acumulando alumnos del curso anterior, guardar
                if (!empty($alumnos) && ($semestre || $cursoCodigo || $clave)) {
                    $this->procesarBloque($semestre, $cursoCodigo, $clave, $alumnos);
                    $alumnos = [];
                }
                $semestre = trim($m[1]);
                $cursoCodigo = null;
                $clave       = null;
                continue;
            }

            // ── Detectar código de CURSO: "CURSO : AL4401" o "AL4401 NOMBRE" ─
            if (preg_match('/CURSO\s*:?\s*([A-Z]{2,3}\d{3,5})/i', $text, $m)) {
                if (!empty($alumnos) && ($semestre || $cursoCodigo || $clave)) {
                    $this->procesarBloque($semestre, $cursoCodigo, $clave, $alumnos);
                    $alumnos = [];
                }
                $cursoCodigo = strtoupper(trim($m[1]));
                $clave       = null;
                continue;
            }

            // ── Detectar CLAVE SIGA ─────────────────────────────────────
            if (preg_match('/CLAVE\s*:?\s*(\d+)/i', $text, $m)) {
                $clave = trim($m[1]);
                continue;
            }

            // ── Detectar fila de alumno: empieza con 10 dígitos ─────────
            // Acepta: "0502021001 NOMBRE" o con número de orden previo "1 0502021001 NOMBRE"
            if (preg_match('/(?:^|\s)(\d{10})\s+([A-ZÁÉÍÓÚÑ][^0-9]{2,80})/u', $text, $m)) {
                $nombre = trim($m[2]);
                // Descartar si el "nombre" parece un DNI (solo dígitos)
                if (!ctype_digit($nombre)) {
                    $alumnos[] = [
                        'codigo' => $m[1],
                        'nombre' => mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'),
                    ];
                }
            }
        }

        // Guardar el último bloque
        if (!empty($alumnos) && ($semestre || $cursoCodigo || $clave)) {
            $this->procesarBloque($semestre, $cursoCodigo, $clave, $alumnos);
        }
    }

    /**
     * Extrae el texto de una fila uniendo celdas con espacio
     * (textContent las concatena sin separador).
     */
    private function textoFila(\DOMElement $tr, DOMXPath $xpath): string
    {
        $celdas = $xpath->query('.//td|.//th', $tr);
        if ($celdas->length > 0) {
            $partes = [];
            foreach ($celdas as $td) {
                $val = trim(preg_replace('/\s+/', ' ', $td->textContent));
                if ($val !== '') {
                    $partes[] = $val;
                }
            }
            return implode(' ', $partes);
        }
        return trim(preg_replace('/\s+/', ' ', $tr->textContent));
    }

    private function procesarBloque(?string $semestre, ?string $cursoCodigo, ?string $clave, array $alumnos): void
    {
        if (empty($alumnos)) {
            return;
        }

        if (!$semestre && !$cursoCodigo && !$clave) {
            return;
        }

        $programacion = $this->buscarProgramacion($clave, $cursoCodigo, $semestre);

        if (!$programacion) {
            $label = $cursoCodigo ?? "CLAVE {$clave}";
            $sem   = $semestre ?? 'semestre desconocido';
            $this->noEncontrados[] = "{$label} ({$sem}): no se encontró en programación académica.";
            return;
        }

        $this->guardarInscripciones($programacion, $alumnos);
    }

    /**
     * Busca la programación con 3 estrategias de matching.
     */
    private function buscarProgramacion(?string $clave, ?string $cursoCodigo, ?string $semestre): ?ProgramacionAcademica
    {
        // ── Estrategia 1: clave + periodo exacto ───────────────────────
        if ($clave && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $prog = ProgramacionAcademica::where('clave', $clave)
                    ->where('periodo_id', $periodo->id)
                    ->first();
                if ($prog) return $prog;
            }
        }

        // ── Estrategia 2: código de curso + periodo exacto ─────────────
        if ($cursoCodigo && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->get();

                    if ($candidatos->count() === 1) return $candidatos->first();

                    if ($candidatos->count() > 1 && $clave) {
                        $match = $candidatos->firstWhere('clave', $clave);
                        if ($match) return $match;
                    }

                    if ($candidatos->count() > 0) return $candidatos->first();
                }
            }
        }

        // ── Estrategia 3: código de curso + búsqueda parcial de periodo ─
        if ($cursoCodigo && $semestre) {
            $curso = Curso::where('codigo', $cursoCodigo)->first();
            if ($curso) {
                $periodos = Periodo::where('nombre', 'like', "%{$semestre}%")->get();
                foreach ($periodos as $periodo) {
                    $prog = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->first();
                    if ($prog) return $prog;
                }
            }
        }

        return null;
    }

    private function buscarPeriodo(string $semestre): ?Periodo
    {
        return Periodo::where('nombre', $semestre)->first()
            ?? Periodo::where('nombre', 'like', "%{$semestre}%")->first();
    }

    private function guardarInscripciones(ProgramacionAcademica $programacion, array $alumnos): void
    {
        $this->programacionesProcesadas++;

        DB::transaction(function () use ($programacion, $alumnos) {
            foreach ($alumnos as $alumnoData) {
                try {
                    $user = $this->crearOObtenerEstudiante($alumnoData['codigo'], $alumnoData['nombre']);

                    $result = Inscripcion::updateOrCreate(
                        [
                            'programacion_id' => $programacion->id,
                            'user_id'         => $user->id,
                        ],
                        [
                            'periodo_id' => $programacion->periodo_id,
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

            $count = Inscripcion::where('programacion_id', $programacion->id)->count();
            $programacion->update(['n_inscritos' => $count]);
        });
    }

    private function crearOObtenerEstudiante(string $codigo, string $nombre): User
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

        if (!$existe) {
            $this->alumnosNuevos++;
        }

        return $user;
    }

    public function getResumen(): array
    {
        return [
            'programaciones_procesadas'  => $this->programacionesProcesadas,
            'inscripciones_creadas'      => $this->inscripcionesCreadas,
            'inscripciones_actualizadas' => $this->inscripcionesActualizadas,
            'alumnos_nuevos'             => $this->alumnosNuevos,
            'no_encontrados'             => count($this->noEncontrados),
            'detalle_no_encontrados'     => $this->noEncontrados,
            'errores'                    => count($this->errores),
            'detalle_errores'            => $this->errores,
        ];
    }
}
