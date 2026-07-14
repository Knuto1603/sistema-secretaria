<?php

namespace App\Services;

use App\Models\Aula;
use App\Models\BorradorProgramacion;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\ModificacionProgramacion;
use App\Models\Programacion;
use App\Models\ProgramacionAcademica;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;

class ImportarDiffProgramacionService
{
    private array $grupoCache   = [];
    private array $aulaCache    = [];
    private array $escuelaCache = [];

    public function __construct()
    {
        GrupoHorario::all(['id', 'nombre'])->each(function ($g) {
            $this->grupoCache[strtoupper(trim($g->nombre))] = $g->id;
        });

        Aula::all(['id', 'nombre'])->each(function ($a) {
            $this->aulaCache[strtoupper(trim($a->nombre))] = $a->id;
        });

        Escuela::all(['id', 'nombre', 'nombre_corto'])->each(function ($e) {
            $this->escuelaCache[$this->normalizar($e->nombre)] = $e->id;
            if ($e->nombre_corto) {
                $this->escuelaCache[$this->normalizar($e->nombre_corto)] = $e->id;
            }
        });
    }

    // ─── Público ────────────────────────────────────────────────────────────

    /**
     * Lee el archivo y devuelve el diff sin tocar la base de datos.
     */
    public function preview(UploadedFile $file, string $periodoId): array
    {
        [$filasArchivo, $omitidos, $debug] = $this->parsearArchivo($file);
        $indiceActual  = $this->construirIndiceActual($periodoId);
        $indiceArchivo = $this->construirIndiceArchivo($filasArchivo);

        $nuevas            = [];
        $eliminadas        = [];
        $reabiertas        = [];
        $cambiosAula       = [];
        $cambiosGrupo      = [];
        $cambiosAulaYGrupo = [];
        $sinCambios        = 0;

        // Filas del archivo vs BD
        foreach ($indiceArchivo as $clave => $fila) {
            if (!isset($indiceActual[$clave])) {
                $nuevas[] = $this->filaResumen($fila);
                continue;
            }

            $prog     = $indiceActual[$clave];
            $mismaAula  = ($prog->aula_id ?? null) === ($fila['aula_id'] ?? null);
            $mismoGrupo = ($prog->grupo_horario_id ?? null) === ($fila['grupo_horario_id'] ?? null);

            if ($mismaAula && $mismoGrupo) {
                // Si estaba inactiva, el archivo indica que debe reactivarse
                if (!$prog->activo) {
                    $reabiertas[] = $this->filaResumenDesdeProg($prog);
                } else {
                    $sinCambios++;
                }
                continue;
            }

            $base = [
                'programacion_id' => $prog->id,
                'curso_codigo'    => $prog->curso?->codigo,
                'curso_nombre'    => $prog->curso?->nombre,
                'escuela_nombre'  => $prog->escuelaProgramada?->nombre_corto ?? $prog->escuelaProgramada?->nombre,
                'seccion'         => $prog->seccion,
                'ciclo'           => $prog->ciclo,
                'aula_anterior'   => $prog->aulaRelacion?->nombre,
                'aula_nueva'      => $fila['aula_nombre'] ?? null,
                'grupo_anterior'  => $prog->grupoHorario?->nombre,
                'grupo_nuevo'     => $fila['grupo_nombre'] ?? null,
            ];

            if (!$mismaAula && !$mismoGrupo) {
                $cambiosAulaYGrupo[] = $base;
            } elseif (!$mismaAula) {
                $cambiosAula[] = $base;
            } else {
                $cambiosGrupo[] = $base;
            }
        }

        // Registros BD que no aparecen en el archivo
        foreach ($indiceActual as $clave => $prog) {
            if (!isset($indiceArchivo[$clave])) {
                // Si ya está inactivo, no hay nueva acción que aplicar
                if (!$prog->activo) continue;
                $eliminadas[] = [
                    'programacion_id' => $prog->id,
                    'curso_codigo'    => $prog->curso?->codigo,
                    'curso_nombre'    => $prog->curso?->nombre,
                    'escuela_nombre'  => $prog->escuelaProgramada?->nombre_corto ?? $prog->escuelaProgramada?->nombre,
                    'seccion'         => $prog->seccion,
                    'ciclo'           => $prog->ciclo,
                    'aula_nombre'     => $prog->aulaRelacion?->nombre,
                    'grupo_nombre'    => $prog->grupoHorario?->nombre,
                ];
            }
        }

        return [
            'nuevas'             => $nuevas,
            'eliminadas'         => $eliminadas,
            'reabiertas'         => $reabiertas,
            'cambios_aula'       => $cambiosAula,
            'cambios_grupo'      => $cambiosGrupo,
            'cambios_aula_y_grupo' => $cambiosAulaYGrupo,
            'sin_cambios'        => $sinCambios,
            'omitidos'           => $omitidos,
            'debug'              => $debug,
        ];
    }

    /**
     * Aplica el diff en una transacción.
     */
    public function aplicar(UploadedFile $file, string $periodoId, string $motivo, string $userId): array
    {
        [$filasArchivo, $omitidos, $debug] = $this->parsearArchivo($file);
        $indiceActual  = $this->construirIndiceActual($periodoId);
        $indiceArchivo = $this->construirIndiceArchivo($filasArchivo);

        // Guarda de seguridad: si el archivo no produjo ninguna fila válida pero hay
        // registros en la BD, es casi seguro que el formato no se detectó correctamente.
        // Evita cerrar toda la programación por un error de parsing.
        if (empty($indiceArchivo) && !empty($indiceActual)) {
            throw new \RuntimeException(
                'El archivo no contiene filas válidas (0 cursos reconocidos). '
                . 'Verifique el formato del archivo. '
                . 'Columnas detectadas: ' . implode(', ', array_filter(array_values($debug['columnas_detectadas'] ?? [])))
            );
        }

        $borradorId = BorradorProgramacion::where('periodo_id', $periodoId)
            ->where('estado', 'publicado')
            ->value('id'); // id de la programacion maestra publicada

        $conteos = [
            'nuevas'             => 0,
            'eliminadas'         => 0,
            'reabiertas'         => 0,
            'cambios_aula'       => 0,
            'cambios_grupo'      => 0,
            'cambios_aula_y_grupo' => 0,
        ];

        DB::transaction(function () use (
            $indiceArchivo, $indiceActual,
            $periodoId, $motivo, $userId, $borradorId,
            &$conteos
        ) {
            // Nuevas
            foreach ($indiceArchivo as $clave => $fila) {
                if (isset($indiceActual[$clave])) continue;

                $id    = (string) Str::uuid();
                $claveReg = 'M' . strtoupper(substr(str_replace('-', '', $id), 0, 8));

                $progMaestroId = Programacion::where('periodo_id', $periodoId)
                    ->where('estado', 'publicado')
                    ->value('id');

                $prog = ProgramacionAcademica::create([
                    'id'                    => $id,
                    'curso_id'              => $fila['curso_id'],
                    'programacion_id'       => $progMaestroId,
                    'docente_id'            => null,
                    'grupo_horario_id'      => $fila['grupo_horario_id'] ?? null,
                    'aula_id'               => $fila['aula_id'] ?? null,
                    'clave'                 => $claveReg,
                    'grupo'                 => $fila['grupo_texto'] ?? null,
                    'seccion'               => $fila['seccion'],
                    'ciclo'                 => $fila['ciclo'],
                    'aula'                  => $fila['aula_texto'] ?? null,
                    'n_acta'                => null,
                    'capacidad'             => 40,
                    'n_inscritos'           => 0,
                    'lleno_manual'          => false,
                    'escuela_programada_id' => $fila['escuela_id'] ?? null,
                ]);

                if (!empty($fila['escuela_id'])) {
                    $prog->escuelas()->sync([$fila['escuela_id']]);
                }

                ModificacionProgramacion::create([
                    'periodo_id'       => $periodoId,
                    'borrador_id'      => $borradorId,
                    'tipo'             => 'abrir_seccion',
                    'programacion_id'  => $prog->id,
                    'datos_anteriores' => [],
                    'datos_nuevos'     => [
                        'aula_id'              => $fila['aula_id'] ?? null,
                        'aula_nombre'          => $fila['aula_nombre'] ?? null,
                        'grupo_horario_id'     => $fila['grupo_horario_id'] ?? null,
                        'grupo_horario_nombre' => $fila['grupo_nombre'] ?? null,
                    ],
                    'motivo'  => $motivo,
                    'user_id' => $userId,
                ]);

                $conteos['nuevas']++;
            }

            // Cambios y eliminadas
            foreach ($indiceActual as $clave => $prog) {
                if (!isset($indiceArchivo[$clave])) {
                    // Si ya está inactivo, no duplicar la acción
                    if (!$prog->activo) continue;

                    $prog->update(['activo' => false]);

                    ModificacionProgramacion::create([
                        'periodo_id'       => $periodoId,
                        'borrador_id'      => $borradorId,
                        'tipo'             => 'cerrar_curso',
                        'programacion_id'  => $prog->id,
                        'datos_anteriores' => ['activo' => true],
                        'datos_nuevos'     => ['activo' => false],
                        'motivo'  => $motivo,
                        'user_id' => $userId,
                    ]);

                    $conteos['eliminadas']++;
                    continue;
                }

                $fila       = $indiceArchivo[$clave];
                $mismaAula  = ($prog->aula_id ?? null) === ($fila['aula_id'] ?? null);
                $mismoGrupo = ($prog->grupo_horario_id ?? null) === ($fila['grupo_horario_id'] ?? null);

                if ($mismaAula && $mismoGrupo) {
                    // Reactivar si estaba inactivo
                    if (!$prog->activo) {
                        $prog->update(['activo' => true]);

                        ModificacionProgramacion::create([
                            'periodo_id'       => $periodoId,
                            'borrador_id'      => $borradorId,
                            'tipo'             => 'reabrir_seccion',
                            'programacion_id'  => $prog->id,
                            'datos_anteriores' => ['activo' => false],
                            'datos_nuevos'     => ['activo' => true],
                            'motivo'           => $motivo,
                            'user_id'          => $userId,
                        ]);

                        $conteos['reabiertas']++;
                    }
                    continue;
                }

                $anterior = [
                    'aula_id'              => $prog->aula_id,
                    'aula_nombre'          => $prog->aulaRelacion?->nombre,
                    'grupo_horario_id'     => $prog->grupo_horario_id,
                    'grupo_horario_nombre' => $prog->grupoHorario?->nombre,
                ];

                if (!$mismaAula && !$mismoGrupo) {
                    $prog->update([
                        'aula_id'          => $fila['aula_id'] ?? null,
                        'grupo_horario_id' => $fila['grupo_horario_id'] ?? null,
                        'activo'           => true,
                    ]);
                    $tipo = 'cambio_aula_y_grupo';
                    $conteos['cambios_aula_y_grupo']++;
                } elseif (!$mismaAula) {
                    $prog->update([
                        'aula_id' => $fila['aula_id'] ?? null,
                        'activo'  => true,
                    ]);
                    $tipo = 'cambio_aula';
                    $conteos['cambios_aula']++;
                } else {
                    $prog->update([
                        'grupo_horario_id' => $fila['grupo_horario_id'] ?? null,
                        'activo'           => true,
                    ]);
                    $tipo = 'cambio_grupo';
                    $conteos['cambios_grupo']++;
                }

                ModificacionProgramacion::create([
                    'periodo_id'       => $periodoId,
                    'borrador_id'      => $borradorId,
                    'tipo'             => $tipo,
                    'programacion_id'  => $prog->id,
                    'datos_anteriores' => $anterior,
                    'datos_nuevos'     => [
                        'aula_id'              => $fila['aula_id'] ?? null,
                        'aula_nombre'          => $fila['aula_nombre'] ?? null,
                        'grupo_horario_id'     => $fila['grupo_horario_id'] ?? null,
                        'grupo_horario_nombre' => $fila['grupo_nombre'] ?? null,
                    ],
                    'motivo'  => $motivo,
                    'user_id' => $userId,
                ]);
            }
        });

        return [
            'aplicadas' => $conteos,
            'omitidos'  => $omitidos,
        ];
    }

    // ─── Parser ─────────────────────────────────────────────────────────────

    /**
     * Parsea el archivo y devuelve [filasParsadas, omitidos].
     * Detecta automáticamente dos formatos:
     *   - Formato propio (CSV/XLSX): cabecera en fila 1
     *   - Reporte universidad (XLSX UNP): 7 filas de metadata + cabecera en fila 8
     */
    private function parsearArchivo(UploadedFile $file): array
    {
        $collector = new class implements ToCollection {
            public Collection $rows;
            public function collection(Collection $rows): void { $this->rows = $rows; }
        };

        Excel::import($collector, $file);
        $rawRows = $collector->rows ?? collect();

        // Detección de formato: el reporte UNP tiene la fila 1 completamente vacía
        $primeraFila        = $rawRows->get(0);
        $esFormatoUniversidad = $primeraFila &&
            $primeraFila->filter(fn($v) => $v !== null && trim((string) $v) !== '')->isEmpty();

        $filaEncabezado = $esFormatoUniversidad ? $rawRows->get(7)  : $rawRows->get(0);
        $filasData      = $esFormatoUniversidad ? $rawRows->slice(8) : $rawRows->slice(1);

        // Construir mapa posición → clave normalizada
        $indiceColumnas = [];
        if ($filaEncabezado) {
            foreach ($filaEncabezado as $idx => $val) {
                $key = $this->sanitizeKey((string) ($val ?? ''));
                $indiceColumnas[$idx] = $this->normalizarColumna($key);
            }
        }

        $filas    = [];
        $omitidos = [];
        $debug    = [
            'formato'             => $esFormatoUniversidad ? 'reporte_unp' : 'propio',
            'total_filas_archivo' => $rawRows->count(),
            'columnas_detectadas' => $indiceColumnas,
            'primera_fila_raw'    => $primeraFila ? $primeraFila->toArray() : [],
        ];

        foreach ($filasData as $rawRow) {
            $row = [];
            foreach ($rawRow as $idx => $value) {
                $key = $indiceColumnas[$idx] ?? null;
                if ($key !== null && $key !== '') {
                    $row[$key] = $value;
                }
            }

            $codigoRaw = $row['codigo'] ?? null;
            if (!$codigoRaw || trim((string) $codigoRaw) === '') continue;

            $codigoLimpio = trim((string) $codigoRaw);
            $curso = Curso::where('codigo', $codigoLimpio)->first();

            if (!$curso) {
                $omitidos[] = [
                    'codigo' => $codigoLimpio,
                    'nombre' => trim((string) ($row['nombre'] ?? '—')),
                    'motivo' => 'Curso no existe en el sistema',
                ];
                continue;
            }

            $grupoTexto   = $row['grupo']   ?? null;
            $aulaTexto    = $row['aula']    ?? null;
            $escuelaTexto = $row['escuela'] ?? null;
            $seccionTexto = $row['seccion'] ?? null;
            $ciclo        = isset($row['ciclo']) && $row['ciclo'] !== '' ? (int) $row['ciclo'] : null;

            $grupoHorarioId = $this->resolverGrupo($grupoTexto);
            $aulaId         = $this->resolverAula($aulaTexto);
            $escuelaId      = $this->resolverEscuela($escuelaTexto);
            $seccion        = $this->parsearSeccion($seccionTexto);

            // Nombre de aula/grupo para el diff UI
            $aulaNombre  = null;
            if ($aulaId) {
                $aulaNombre = Aula::find($aulaId)?->nombre;
            } elseif ($aulaTexto) {
                $aulaNombre = strtoupper(trim($aulaTexto));
            }

            $grupoNombre = null;
            if ($grupoHorarioId) {
                $grupoNombre = GrupoHorario::find($grupoHorarioId)?->nombre;
            } elseif ($grupoTexto) {
                $grupoNombre = strtoupper(trim($grupoTexto));
            }

            $escuelaNombre = null;
            if ($escuelaId) {
                $escuelaNombre = Escuela::find($escuelaId)?->nombre_corto;
            } elseif ($escuelaTexto) {
                $escuelaNombre = $escuelaTexto;
            }

            $clave = $curso->id . '_' . ($escuelaId ?? '__null__') . '_' . ($seccion ?? '');

            $filas[$clave] = [
                'curso_id'         => $curso->id,
                'curso_codigo'     => $codigoLimpio,
                'curso_nombre'     => $curso->nombre,
                'escuela_id'       => $escuelaId,
                'escuela_nombre'   => $escuelaNombre,
                'seccion'          => $seccion,
                'ciclo'            => $ciclo,
                'grupo_horario_id' => $grupoHorarioId,
                'aula_id'          => $aulaId,
                'aula_nombre'      => $aulaNombre,
                'grupo_nombre'     => $grupoNombre,
                'aula_texto'       => $aulaTexto ? strtoupper(trim($aulaTexto)) : null,
                'grupo_texto'      => $grupoTexto ? strtoupper(trim($grupoTexto)) : null,
            ];
        }

        return [$filas, $omitidos, $debug];
    }

    /**
     * Construye el índice de la BD actual.
     * Clave: {curso_id}_{escuela_programada_id|__null__}_{seccion}
     *
     * @return array<string, ProgramacionAcademica>
     */
    private function construirIndiceActual(string $periodoId): array
    {
        $registros = ProgramacionAcademica::periodo($periodoId)
            ->select('programacion_secciones.*')
            ->with(['curso:id,codigo,nombre', 'aulaRelacion:id,nombre', 'grupoHorario:id,nombre', 'escuelaProgramada:id,nombre,nombre_corto'])
            ->get();

        $indice = [];
        foreach ($registros as $prog) {
            $escuelaId = $prog->escuela_programada_id ?? '__null__';
            $clave     = $prog->curso_id . '_' . $escuelaId . '_' . ($prog->seccion ?? '');
            $indice[$clave] = $prog;
        }

        return $indice;
    }

    /**
     * Construye el índice del archivo ya parseado.
     * Clave idéntica a construirIndiceActual.
     */
    private function construirIndiceArchivo(array $filas): array
    {
        return $filas; // la clave ya está correcta desde parsearArchivo()
    }

    // ─── Helpers UI ─────────────────────────────────────────────────────────

    private function filaResumen(array $fila): array
    {
        return [
            'curso_codigo'  => $fila['curso_codigo'],
            'curso_nombre'  => $fila['curso_nombre'],
            'escuela_nombre'=> $fila['escuela_nombre'],
            'seccion'       => $fila['seccion'],
            'ciclo'         => $fila['ciclo'],
            'aula_nombre'   => $fila['aula_nombre'],
            'grupo_nombre'  => $fila['grupo_nombre'],
        ];
    }

    private function filaResumenDesdeProg(ProgramacionAcademica $prog): array
    {
        return [
            'curso_codigo'  => $prog->curso?->codigo,
            'curso_nombre'  => $prog->curso?->nombre,
            'escuela_nombre'=> $prog->escuelaProgramada?->nombre_corto ?? $prog->escuelaProgramada?->nombre,
            'seccion'       => $prog->seccion,
            'ciclo'         => $prog->ciclo,
            'aula_nombre'   => $prog->aulaRelacion?->nombre,
            'grupo_nombre'  => $prog->grupoHorario?->nombre,
        ];
    }

    // ─── Helpers de resolución (idénticos a ProgramacionMatrizImport) ────────

    private function normalizar(string $texto): string
    {
        $texto = strtoupper(trim($texto));
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'N'],
            $texto
        );
    }

    private function normalizarColumna(string $key): string
    {
        return match($key) {
            'grp'              => 'grupo',
            'sec'              => 'seccion',
            'nombre_del_curso' => 'nombre',
            'n_inscr'          => 'n_inscritos',
            'cap'              => 'capacidad',
            default            => $key,
        };
    }

    private function sanitizeKey(string $key): string
    {
        $clean = trim($key, '. ');
        $clean = str_replace(['N°', 'Nº', 'n°', 'nº'], 'n', $clean);
        return Str::slug($clean, '_');
    }

    private function parsearSeccion(?string $valor): ?string
    {
        if (!$valor || trim($valor) === '') return null;
        $valor = trim($valor);
        if (preg_match('/sec\.?\s*(\w+)/i', $valor, $m)) {
            return $m[1];
        }
        return $valor;
    }

    private function resolverGrupo(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;
        $raw = strtoupper(trim($nombre));
        // "G1abh" → "G1": conservar solo la letra G seguida de dígitos
        $key = preg_match('/^(G\d+)/i', $raw, $m) ? strtoupper($m[1]) : $raw;
        if (isset($this->grupoCache[$key])) {
            return $this->grupoCache[$key];
        }
        $grupo = GrupoHorario::firstOrCreate(
            ['nombre' => $key],
            ['descripcion' => null, 'activo' => true]
        );
        $this->grupoCache[$key] = $grupo->id;
        return $grupo->id;
    }

    private function resolverAula(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;
        $key = strtoupper(trim($nombre));
        if (isset($this->aulaCache[$key])) {
            return $this->aulaCache[$key];
        }
        $aula = Aula::firstOrCreate(
            ['nombre' => $key],
            ['pabellon_id' => null, 'capacidad' => 40, 'activo' => true]
        );
        $this->aulaCache[$key] = $aula->id;
        return $aula->id;
    }

    private function resolverEscuela(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;
        $key = $this->normalizar($nombre);

        if (array_key_exists($key, $this->escuelaCache)) {
            return $this->escuelaCache[$key];
        }

        // Comparación por palabras significativas: ignora prefijos genéricos ("INGENIERIA",
        // "DE", "E"...) y elige la escuela cuya intersección de palabras sea mayor.
        // Esto evita falsos positivos como "INDUSTRIAL" dentro de "AGROINDUSTRIAL".
        $palabrasKey  = $this->palabrasSignificativas($key);
        $encontrado   = null;
        $mejorPuntaje = 0;

        foreach ($this->escuelaCache as $nombreNorm => $id) {
            if ($id === null) continue;

            $palabrasNorm = $this->palabrasSignificativas($nombreNorm);
            $interseccion = count(array_intersect($palabrasKey, $palabrasNorm));

            if ($interseccion > $mejorPuntaje) {
                $mejorPuntaje = $interseccion;
                $encontrado   = $id;
            }
        }

        $this->escuelaCache[$key] = $encontrado;
        return $encontrado;
    }

    private function palabrasSignificativas(string $texto): array
    {
        static $stopWords = ['INGENIERIA', 'DE', 'E', 'Y', 'EN', 'LA', 'LAS', 'LOS', 'DEL'];
        $palabras = array_filter(explode(' ', $texto), fn($w) => strlen($w) > 1);
        return array_values(array_diff($palabras, $stopWords));
    }
}
