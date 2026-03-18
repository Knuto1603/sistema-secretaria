<?php

namespace App\Imports;

use App\Models\Curso;
use App\Models\Escuela;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\ProgramacionAcademica;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Importa inscripciones de alumnos por curso desde el reporte HTML del SIGA
 * ("ALUMNOS POR CURSO XXXXXX.htm").
 *
 * Estrategia de dos fases:
 *  1. parsearFilas()  → array de bloques crudos (cursoCodigo, clave, seccion, semestre, alumnos[])
 *  2. resolverYGuardar() → infiere escuela desde códigos de alumnos, busca programacion,
 *     agrupa bloques con el mismo programacion_id (evita duplicados) y persiste.
 */
class InscripcionesHtmlImport
{
    private int   $programacionesProcesadas  = 0;
    private int   $inscripcionesCreadas      = 0;
    private int   $inscripcionesActualizadas = 0;
    private int   $alumnosNuevos             = 0;
    private int   $bloquesParseados          = 0;
    private array $noEncontrados             = [];
    private array $errores                   = [];

    private ?Role $rolEstudiante = null;

    // Cache de escuelas por código de un dígito
    private array $escuelaCache = [];

    // Cache de periodos por semestre
    private array $periodoCache = [];

    public function import(string $filePath): void
    {
        $this->rolEstudiante = Role::where('name', 'estudiante')
                                   ->where('guard_name', 'web')
                                   ->first();

        $content = file_get_contents($filePath);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $bloques = $this->parsearFilas($content);

        $this->resolverYGuardar($bloques);
    }

    // ─────────────────────────────────────────────────────────────
    // FASE 1: Parseo — devuelve array de bloques crudos
    // ─────────────────────────────────────────────────────────────

    /**
     * Extrae todos los valores FONT COLOR=000080 del HTML en orden de aparición
     * y los analiza secuencialmente para construir bloques curso→alumnos.
     *
     * DOMDocument descarta ~40% del contenido en archivos SIGA multi-página
     * debido a estructura HTML inválida. Por eso se parsea el HTML directamente
     * con regex, evitando cualquier reestructuración del DOM.
     *
     * @return array<int, array{cursoCodigo:string|null, clave:string|null, seccion:string|null, semestre:string|null, alumnos:array}>
     */
    private function parsearFilas(string $content): array
    {
        // Extraer en orden todos los textos dentro de <FONT COLOR=000080>
        // tanto los bold (<B>) como los no-bold (datos de alumnos)
        preg_match_all(
            '/<FONT[^>]*COLOR=000080[^>]*>(?:<B>)?(?:<DIV[^>]*>)?(.*?)(?:<\/DIV>)?(?:<\/B>)?<\/FONT>/si',
            $content,
            $matches
        );

        $valores = [];
        foreach ($matches[1] as $raw) {
            $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text !== '') {
                $valores[] = $text;
            }
        }

        $bloques     = [];
        $semestre    = null;
        $cursoCodigo = null;
        $clave       = null;
        $seccion     = null;
        $alumnos     = [];
        $n           = count($valores);

        for ($i = 0; $i < $n; $i++) {
            $v     = $valores[$i];
            $upper = strtoupper($v);

            // ── Etiqueta (termina en ':') → el siguiente valor es su dato ──
            if (str_ends_with($v, ':')) {
                $next = $valores[$i + 1] ?? '';

                if (str_contains($upper, 'CURSO')) {
                    // Siguiente valor: "AL4401 - NOMBRE DEL CURSO"
                    preg_match('/^([A-Z]{2,6}\d{2,6})/i', $next, $m);
                    $nuevoCodigo = $m ? strtoupper($m[1]) : null;

                    if ($nuevoCodigo && $nuevoCodigo !== $cursoCodigo) {
                        if (!empty($alumnos)) {
                            $bloques[] = compact('cursoCodigo', 'clave', 'seccion', 'semestre', 'alumnos');
                            $alumnos   = [];
                        }
                        $cursoCodigo = $nuevoCodigo;
                        $clave       = null;
                        $seccion     = null;
                    }
                    $i++;
                    continue;
                }

                if (str_contains($upper, 'SEMESTRE')) {
                    $semestre = $next;
                    $i++;
                    continue;
                }

                if (str_contains($upper, 'CLAVE')) {
                    $nuevaClave = $next;
                    // Mismo curso, nueva clave → nueva sección → cerrar bloque anterior
                    if ($nuevaClave !== $clave && $cursoCodigo !== null && !empty($alumnos)) {
                        $bloques[] = compact('cursoCodigo', 'clave', 'seccion', 'semestre', 'alumnos');
                        $alumnos   = [];
                        $seccion   = null;
                    }
                    $clave = $nuevaClave;
                    $i++;
                    continue;
                }

                if (str_contains($upper, 'SECCION')) {
                    $seccion = $next;
                    $i++;
                    continue;
                }

                // Otras etiquetas (DOCENTE, GRUPO, CAPACIDAD, Nº INSCRITOS…): ignorar solo la etiqueta
                continue;
            }

            // ── Fila de alumno: código de 10 dígitos seguido del nombre ──
            if (preg_match('/^\d{10}$/', $v)) {
                $nombre = $valores[$i + 1] ?? '';
                if ($nombre !== '' && !preg_match('/^(CODIGO|NOMBRE|N[°º]|DOCENTE|FACULTAD)/i', $nombre)) {
                    $alumnos[] = [
                        'codigo' => $v,
                        'nombre' => mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'),
                    ];
                    $i++; // saltar el nombre
                }
            }
        }

        // Guardar el último bloque
        if (!empty($alumnos)) {
            $bloques[] = compact('cursoCodigo', 'clave', 'seccion', 'semestre', 'alumnos');
        }

        return $bloques;
    }

    // ─────────────────────────────────────────────────────────────
    // FASE 2: Resolución y persistencia con deduplicación
    // ─────────────────────────────────────────────────────────────

    /**
     * Para cada bloque crudo:
     *  1. Infiere la escuela desde los códigos de los alumnos.
     *  2. Busca la programación académica con la escuela como filtro adicional.
     *  3. Agrupa los bloques que resuelvan al mismo programacion_id (evita duplicados
     *     cuando el mismo curso-sección aparece para varias escuelas en el mismo HTML).
     *  4. Persiste cada grupo una sola vez.
     */
    private function resolverYGuardar(array $bloques): void
    {
        // Mapa programacion_id → alumnos acumulados
        $grupos = [];

        $this->bloquesParseados = count($bloques);

        foreach ($bloques as $bloque) {
            if (empty($bloque['alumnos']) && !$bloque['semestre'] && !$bloque['cursoCodigo'] && !$bloque['clave']) {
                continue;
            }

            // Inferir escuela desde los códigos de los propios alumnos
            $escuela = $this->escuelaDesdeCodigos($bloque['alumnos']);

            $programacion = $this->buscarProgramacion(
                $bloque['clave'],
                $bloque['cursoCodigo'],
                $bloque['seccion'],
                $bloque['semestre'],
                $escuela
            );

            if (!$programacion) {
                $label = $bloque['cursoCodigo'] ?? "CLAVE {$bloque['clave']}";
                $sec   = $bloque['seccion'] ? " sec {$bloque['seccion']}" : '';
                $sem   = $bloque['semestre'] ?? '?';
                $esc   = $escuela ? " ({$escuela->nombre_corto})" : '';
                $this->noEncontrados[] = "{$label}{$sec}{$esc} (sem {$sem}): no se encontró en programación académica.";
                continue;
            }

            // Agrupar por programacion_id para evitar duplicados
            $pid = $programacion->id;
            if (!isset($grupos[$pid])) {
                $grupos[$pid] = [
                    'programacion' => $programacion,
                    'alumnos'      => [],
                ];
            }

            // Fusionar alumnos evitando duplicados por código
            foreach ($bloque['alumnos'] as $alumno) {
                $grupos[$pid]['alumnos'][$alumno['codigo']] = $alumno;
            }
        }

        // Persistir cada grupo una sola vez
        foreach ($grupos as $grupo) {
            $this->guardarInscripciones($grupo['programacion'], array_values($grupo['alumnos']));
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Inferencia de escuela
    // ─────────────────────────────────────────────────────────────

    /**
     * Determina la escuela mayoritaria entre los alumnos del bloque
     * usando el dígito de posición 2 del código universitario (10 dígitos).
     * Formato: FF E GGGG NNN  →  FF=facultad, E=escuela
     */
    private function escuelaDesdeCodigos(array $alumnos): ?Escuela
    {
        $conteo = [];
        foreach ($alumnos as $alumno) {
            $codigo = $alumno['codigo'];
            if (strlen($codigo) !== 10 || !ctype_digit($codigo)) {
                continue;
            }
            $digito = substr($codigo, 2, 1);
            $conteo[$digito] = ($conteo[$digito] ?? 0) + 1;
        }

        if (empty($conteo)) {
            return null;
        }

        // Usar el dígito más frecuente (mayoría)
        arsort($conteo);
        $digitoMayoria = array_key_first($conteo);

        if (!isset($this->escuelaCache[$digitoMayoria])) {
            $this->escuelaCache[$digitoMayoria] = Escuela::findByCodigo($digitoMayoria);
        }

        return $this->escuelaCache[$digitoMayoria];
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda de programación con escuela como filtro
    // ─────────────────────────────────────────────────────────────

    private function buscarProgramacion(
        ?string $clave,
        ?string $cursoCodigo,
        ?string $seccion,
        ?string $semestre,
        ?Escuela $escuela
    ): ?ProgramacionAcademica {
        // Normalizar sección: el SIGA la devuelve como "01", pero el Excel puede
        // haberla guardado como "1" (sin cero inicial).
        $seccionAlt = $seccion ? ltrim($seccion, '0') ?: '0' : null;

        $matchSeccion = function ($query) use ($seccion, $seccionAlt) {
            $query->where(function ($q) use ($seccion, $seccionAlt) {
                $q->where('seccion', $seccion);
                if ($seccionAlt !== $seccion) {
                    $q->orWhere('seccion', $seccionAlt);
                }
            });
        };

        // 1. Clave SIGA + periodo — el match más preciso, no necesita escuela
        if ($clave && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $prog = ProgramacionAcademica::where('clave', $clave)
                    ->where('periodo_id', $periodo->id)
                    ->first();
                if ($prog) return $prog;
            }
        }

        // 2. Curso + sección + escuela (via pivot) + periodo
        if ($cursoCodigo && $seccion && $semestre && $escuela) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->tap($matchSeccion)
                        ->whereHas('escuelas', fn ($q) => $q->where('escuelas.id', $escuela->id))
                        ->get();
                    if ($candidatos->count() >= 1) return $candidatos->first();
                }
            }
        }

        // 3. Curso + sección + periodo (sin filtro de escuela, solo si 1 resultado)
        if ($cursoCodigo && $seccion && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->tap($matchSeccion)
                        ->get();
                    if ($candidatos->count() === 1) return $candidatos->first();
                }
            }
        }

        // 4. Curso + escuela (via pivot) + periodo — solo si 1 sección de esa escuela
        if ($cursoCodigo && $semestre && $escuela) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->whereHas('escuelas', fn ($q) => $q->where('escuelas.id', $escuela->id))
                        ->get();
                    if ($candidatos->count() === 1) return $candidatos->first();
                }
            }
        }

        // 5. Curso + periodo — último recurso, solo si hay exactamente 1 sección total
        if ($cursoCodigo && $semestre) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->get();
                    if ($candidatos->count() === 1) return $candidatos->first();
                }
            }
        }

        return null;
    }

    private function buscarPeriodo(string $semestre): ?Periodo
    {
        if (!isset($this->periodoCache[$semestre])) {
            $this->periodoCache[$semestre] =
                Periodo::where('nombre', $semestre)->first()
                ?? Periodo::where('nombre', 'like', "%{$semestre}%")->first();
        }
        return $this->periodoCache[$semestre];
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
            'bloques_parseados'          => $this->bloquesParseados,
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
