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
 * Estructura real del archivo:
 *   - Una <TABLE> por curso/página
 *   - Fila de cabecera: "CURSO : AL4401 - NOMBRE ... SEMESTRE: 2026-0"  (misma fila)
 *   - Fila de clave:    "CLAVE : 5081 SECCION : 01 ..."
 *   - Filas de alumnos: código (10 dígitos) + nombre
 *
 * El parser extrae TODA la info de cada fila de forma simultánea
 * para manejar el caso CURSO+SEMESTRE en la misma fila.
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
        $this->parsearFilas($xpath);
    }

    /**
     * Recorre TODAS las filas del documento en orden.
     * Extrae CURSO + SEMESTRE de la misma fila cuando aparecen juntos.
     * Acumula alumnos hasta que aparece un nuevo curso, entonces guarda el bloque.
     */
    private function parsearFilas(DOMXPath $xpath): void
    {
        $semestre    = null;
        $cursoCodigo = null;
        $clave       = null;
        $seccion     = null;
        $alumnos     = [];

        foreach ($xpath->query('//tr') as $tr) {
            $text = $this->textoFila($tr, $xpath);
            if ($text === '') {
                continue;
            }

            // ── Extraer simultáneamente todo lo que haya en esta fila ──
            $hayCurso    = (bool) preg_match('/CURSO\s*:\s*([A-Z]{2,3}\d{3,5})/i', $text, $mCurso);
            $haySemestre = (bool) preg_match('/SEMESTRE\s*:\s*(\d{4}-\d)/i',       $text, $mSem);
            $hayClave    = (bool) preg_match('/CLAVE\s*:\s*(\d+)/i',               $text, $mClave);
            $haySeccion  = (bool) preg_match('/SECCION\s*:\s*(\S+)/i',             $text, $mSeccion);
            $hayAlumno   = (bool) preg_match('/^(\d{10})\s+(.+)$/',               $text, $mAlumno);

            // ── Cuando aparece un nuevo CURSO, guardar el bloque anterior ──
            if ($hayCurso) {
                $nuevoCodigo = strtoupper(trim($mCurso[1]));
                if ($nuevoCodigo !== $cursoCodigo) {
                    if (!empty($alumnos)) {
                        $this->procesarBloque($semestre, $cursoCodigo, $clave, $seccion, $alumnos);
                        $alumnos = [];
                    }
                    $cursoCodigo = $nuevoCodigo;
                    $clave       = null;
                    $seccion     = null;
                }
            }

            if ($haySemestre) {
                $semestre = trim($mSem[1]);
            }

            if ($hayClave) {
                $clave = trim($mClave[1]);
            }

            if ($haySeccion) {
                $seccion = trim($mSeccion[1]);
            }

            if ($hayAlumno) {
                $nombre = trim($mAlumno[2]);
                // Descartar filas que parecen cabecera (e.g., "CODIGO", "NOMBRE DE ALUMNO")
                if (!preg_match('/^(CODIGO|NOMBRE|N[°º]|DOCENTE|FACULTAD)/i', $nombre)) {
                    $alumnos[] = [
                        'codigo' => $mAlumno[1],
                        'nombre' => mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'),
                    ];
                }
            }
        }

        // Guardar el último bloque
        if (!empty($alumnos)) {
            $this->procesarBloque($semestre, $cursoCodigo, $clave, $seccion, $alumnos);
        }
    }

    /**
     * Extrae el texto de una fila uniendo celdas individuales con espacio.
     * DOMDocument::textContent concatena sin separador, rompiendo los regex.
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

    private function procesarBloque(?string $semestre, ?string $cursoCodigo, ?string $clave, ?string $seccion, array $alumnos): void
    {
        if (empty($alumnos) || (!$semestre && !$cursoCodigo && !$clave)) {
            return;
        }

        $programacion = $this->buscarProgramacion($clave, $cursoCodigo, $seccion, $semestre);

        if (!$programacion) {
            $label = $cursoCodigo ?? "CLAVE {$clave}";
            $sec   = $seccion ? " sec {$seccion}" : '';
            $sem   = $semestre ?? '?';
            $this->noEncontrados[] = "{$label}{$sec} (sem {$sem}): no se encontró en programación académica.";
            return;
        }

        $this->guardarInscripciones($programacion, $alumnos);
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda de programación con 3 estrategias
    // ─────────────────────────────────────────────────────────────

    private function buscarProgramacion(?string $clave, ?string $cursoCodigo, ?string $seccion, ?string $semestre): ?ProgramacionAcademica
    {
        // 1. Clave SIGA + periodo exacto (más preciso)
        if ($clave && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $prog = ProgramacionAcademica::where('clave', $clave)
                    ->where('periodo_id', $periodo->id)
                    ->first();
                if ($prog) return $prog;
            }
        }

        // 2. Curso + sección + periodo (usando los 3 campos que son únicos)
        if ($cursoCodigo && $seccion && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $prog = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->where('seccion', $seccion)
                        ->first();
                    if ($prog) return $prog;
                }
            }
        }

        // 3. Curso + periodo exacto — solo si hay 1 sección (seguro)
        if ($cursoCodigo && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->get();
                    if ($candidatos->count() === 1) return $candidatos->first();
                    // Varias secciones sin poder distinguir → no adivinar
                }
            }
        }

        // 4. Curso + sección + periodo parcial
        if ($cursoCodigo && $seccion && $semestre) {
            $curso = Curso::where('codigo', $cursoCodigo)->first();
            if ($curso) {
                foreach (Periodo::where('nombre', 'like', "%{$semestre}%")->get() as $periodo) {
                    $prog = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->where('seccion', $seccion)
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

    // ─────────────────────────────────────────────────────────────
    // Persistencia
    // ─────────────────────────────────────────────────────────────

    private function guardarInscripciones(ProgramacionAcademica $programacion, array $alumnos): void
    {
        $this->programacionesProcesadas++;

        DB::transaction(function () use ($programacion, $alumnos) {
            foreach ($alumnos as $alumnoData) {
                try {
                    $user = $this->crearOObtenerEstudiante($alumnoData['codigo'], $alumnoData['nombre']);

                    $result = Inscripcion::updateOrCreate(
                        ['programacion_id' => $programacion->id, 'user_id' => $user->id],
                        ['periodo_id' => $programacion->periodo_id, 'fuente' => 'siga']
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

        $updates = ['tipo_usuario' => 'estudiante', 'email' => User::generarEmailEstudiante($codigo), 'activo' => true];
        if (!empty($nombre)) {
            $updates['name'] = $nombre;
        }

        $user = User::updateOrCreate(['codigo_universitario' => $codigo], $updates);
        $user->asignarDatosDesdeCodigoUniversitario();

        if ($this->rolEstudiante && !$user->hasRole('estudiante')) {
            $user->assignRole($this->rolEstudiante);
        }

        if (!$existe) $this->alumnosNuevos++;

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
