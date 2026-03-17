<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\Plan;
use App\Models\PlanEstudios;
use App\Models\ProgramacionAcademica;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Inyecta datos vivos de la base de datos en el contexto del chatbot.
 *
 * — Estudiante autenticado: perfil personal, inscripciones, historial, plan.
 * — Administrativo/developer: puede consultar cualquier alumno por código o
 *   nombre, ver docentes y sus cursos, y obtener horarios completos.
 * — Todos: programación con docente + horario, periodo activo, autoridades.
 */
class DbContextService
{
    public function buildContext(string $query, ?User $user = null): string
    {
        $blocks       = [];
        $esEstudiante = $user && $user->isEstudiante();
        $esAdmin      = $user && ($user->isAdministrativo() || $user->isDeveloper());
        $escuelaId    = $esEstudiante ? $user->escuela_id : null;

        // Siempre: periodo activo
        $periodoBlock = $this->buildPeriodoBlock();
        if ($periodoBlock) {
            $blocks[] = $periodoBlock;
        }

        // Siempre: autoridades
        $autoridadesBlock = $this->buildAutoridadesBlock();
        if ($autoridadesBlock) {
            $blocks[] = $autoridadesBlock;
        }

        // ── CONTEXTO ESTUDIANTE ──────────────────────────────────────────────
        if ($esEstudiante) {
            $user->loadMissing('escuela');
            $blocks[] = $this->buildEstudiantePerfilBlock($user);

            if ($this->querySeemsAboutMisInscripciones($query)) {
                $b = $this->buildMisInscripcionesBlock($user);
                if ($b) $blocks[] = $b;
            }

            // Comparación plan vs. historial (bloque completo, tiene precedencia)
            if ($this->querySeemsAboutComparacion($query)) {
                $b = $this->buildComparacionPlanHistorialBlock($user);
                if ($b) $blocks[] = $b;
            } elseif ($this->querySeemsAboutHistorial($query)) {
                $b = $this->buildMiHistorialBlock($user);
                if ($b) $blocks[] = $b;
            }
        }

        // ── CONTEXTO ADMIN: CONSULTA DE ALUMNO ──────────────────────────────
        if ($esAdmin && $this->querySeemsAboutAlumno($query)) {
            $b = $this->buildAdminAlumnoBlock($query);
            if ($b) $blocks[] = $b;
        }

        // ── DOCENTE ──────────────────────────────────────────────────────────
        if ($this->querySeemsAboutDocente($query)) {
            $b = $this->buildDocenteBlock($query);
            if ($b) $blocks[] = $b;
        }

        // ── PROGRAMACIÓN ─────────────────────────────────────────────────────
        if ($this->querySeemsAboutCursos($query)) {
            $b = $this->buildProgramacionBlock($query, $escuelaId);
            if ($b) $blocks[] = $b;
        }

        // ── PLAN DE ESTUDIOS ──────────────────────────────────────────────────
        if ($this->querySeemsAboutPlanEstudios($query)) {
            $b = $this->buildPlanEstudiosBlock($query, $escuelaId);
            if ($b) $blocks[] = $b;
        }

        if (empty($blocks)) {
            return '';
        }

        return "=== INFORMACIÓN ACADÉMICA ACTUAL (BASE DE DATOS) ===\n\n"
            . implode("\n\n", $blocks);
    }

    // =========================================================================
    // BLOQUE: PERIODO ACTIVO
    // =========================================================================

    private function buildPeriodoBlock(): string
    {
        $periodo = Periodo::where('activo', true)->first();

        if (!$periodo) {
            return "PERIODO ACTUAL: No hay periodo académico activo en este momento.";
        }

        $tipo   = $this->clasificarPeriodo($periodo->nombre);
        $inicio = $periodo->fecha_inicio?->format('d/m/Y') ?? '—';
        $fin    = $periodo->fecha_fin?->format('d/m/Y')    ?? '—';

        return <<<TEXT
            PERIODO ACADÉMICO ACTIVO (también llamado "ciclo actual" por los estudiantes):
            • Nombre: {$periodo->nombre}
            • Tipo: {$tipo}
            • Vigencia: {$inicio} al {$fin}
            TEXT;
    }

    // =========================================================================
    // BLOQUE: AUTORIDADES
    // =========================================================================

    private function buildAutoridadesBlock(): string
    {
        $decano     = SystemSetting::get('decano_nombre');
        $secretario = SystemSetting::get('secretario_academico_nombre');

        if (!$decano && !$secretario) {
            return '';
        }

        $lines = ["AUTORIDADES FII-UNP:"];
        if ($decano)     $lines[] = "• Decano: {$decano}";
        if ($secretario) $lines[] = "• Secretario Académico: {$secretario}";

        return implode("\n", $lines);
    }

    // =========================================================================
    // BLOQUE: PERFIL DEL ESTUDIANTE AUTENTICADO
    // =========================================================================

    private function buildEstudiantePerfilBlock(User $user): string
    {
        $escuela   = $user->escuela?->nombre ?? 'No asignada';
        $ciclo     = $user->cicloActual();
        $ingreso   = $user->anio_ingreso ? "Promoción {$user->anio_ingreso}" : '—';
        $estado    = $user->activo ? 'Activo' : 'Inactivo';
        $egresante = $user->egresante ? ' (Egresante)' : '';

        return <<<TEXT
            TU PERFIL ACADÉMICO:
            • Nombre: {$user->name}
            • Código: {$user->codigo_universitario}
            • Escuela: {$escuela}
            • {$ingreso} — Ciclo estimado: {$ciclo}{$egresante}
            • Estado: {$estado}
            TEXT;
    }

    // =========================================================================
    // BLOQUE: INSCRIPCIONES DEL ESTUDIANTE AUTENTICADO
    // =========================================================================

    private function buildMisInscripcionesBlock(User $user): string
    {
        $periodo = Periodo::where('activo', true)->first();
        if (!$periodo) return '';

        $inscripciones = Inscripcion::where('user_id', $user->id)
            ->where('periodo_id', $periodo->id)
            ->with(['programacion.curso', 'programacion.grupoHorario.detalles', 'programacion.docente'])
            ->get();

        $tipo = $this->clasificarPeriodo($periodo->nombre);

        if ($inscripciones->isEmpty()) {
            return "TUS CURSOS INSCRITOS — {$periodo->nombre} ({$tipo}):\n"
                 . "No tienes inscripciones registradas en el sistema para este periodo.";
        }

        $lines = ["TUS CURSOS INSCRITOS — {$periodo->nombre} ({$tipo}):"];

        foreach ($inscripciones as $i) {
            $p = $i->programacion;
            if (!$p) continue;

            $estado  = $p->estaLleno() ? 'LLENO' : 'Disponible';
            $horario = $this->formatHorario($p->grupoHorario);
            $docente = $p->docente?->nombre_completo ?? '—';

            $lines[] = sprintf("• %s — Grupo %s, Secc. %s — Aula: %s — %s",
                Str::limit($p->curso?->nombre ?? '—', 40),
                $p->grupo   ?? '—',
                $p->seccion ?? '—',
                $p->aula    ?? '—',
                $estado
            );
            if ($horario) $lines[] = "  Horario: {$horario}";
            if ($docente !== '—') $lines[] = "  Docente: {$docente}";
        }

        $count   = $inscripciones->count();
        $lines[] = "({$count} curso(s) inscrito(s))";

        return implode("\n", $lines);
    }

    // =========================================================================
    // BLOQUE: HISTORIAL DEL ESTUDIANTE AUTENTICADO
    // =========================================================================

    private function buildMiHistorialBlock(User $user): string
    {
        if (!$user->tieneHistorial()) {
            return "TU HISTORIAL ACADÉMICO:\n"
                 . "Aún no tienes historial académico registrado. "
                 . "Importa tu historial desde el PDF del SIGA para ver tu progreso.";
        }

        $aprobados = $user->cursosAprobados()
            ->withPivot('nota', 'semestre', 'creditos')
            ->get();

        if ($aprobados->isEmpty()) {
            return "TU HISTORIAL ACADÉMICO:\nNo se encontraron cursos aprobados en tu historial.";
        }

        $totalCreditos = $aprobados->sum('pivot.creditos');
        $totalCursos   = $aprobados->count();
        $ultimos       = $aprobados->sortByDesc('pivot.semestre')->take(8);

        $lines = [
            "TU HISTORIAL ACADÉMICO:",
            "• Cursos aprobados: {$totalCursos}",
            "• Créditos aprobados: {$totalCreditos}",
            "",
            "Cursos aprobados más recientes:",
            sprintf("%-42s %-8s %-8s", 'Curso', 'Nota', 'Semestre'),
            str_repeat('-', 62),
        ];

        foreach ($ultimos as $curso) {
            $nota    = $curso->pivot->nota !== null
                ? number_format((float) $curso->pivot->nota, 1)
                : 'Aprob.';
            $lines[] = sprintf("%-42s %-8s %-8s",
                Str::limit($curso->nombre ?? '—', 40),
                $nota,
                $curso->pivot->semestre ?? '—'
            );
        }

        if ($totalCursos > 8) {
            $lines[] = '... y ' . ($totalCursos - 8) . ' cursos más.';
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // BLOQUE: COMPARACIÓN PLAN DE ESTUDIOS vs. HISTORIAL (estudiante autenticado)
    // =========================================================================

    private function buildComparacionPlanHistorialBlock(User $user): string
    {
        if (!$user->escuela_id) {
            return "COMPARACIÓN PLAN vs. HISTORIAL:\nNo tienes escuela asignada. Contacta a secretaría.";
        }

        if (!$user->tieneHistorial()) {
            return "COMPARACIÓN PLAN vs. HISTORIAL:\n"
                 . "Aún no tienes historial académico registrado en el sistema. "
                 . "Para obtener esta comparación, importa tu historial desde el PDF del SIGA.";
        }

        // Plan activo para la escuela
        $plan = Plan::where('escuela_id', $user->escuela_id)
            ->where('activo', true)
            ->first();

        $planQuery = PlanEstudios::with('curso');
        if ($plan) {
            $planQuery->where('plan_id', $plan->id);
        } else {
            $planQuery->where('escuela_id', $user->escuela_id);
        }
        $planEntries = $planQuery->get();

        if ($planEntries->isEmpty()) {
            return "COMPARACIÓN PLAN vs. HISTORIAL:\n"
                 . "No hay plan de estudios cargado para tu escuela. Consulta a secretaría.";
        }

        // IDs de cursos aprobados + sus equivalencias
        $aprobados = $user->cursosAprobados()
            ->withPivot('nota', 'semestre', 'creditos')
            ->get();

        $aprobadosIds = $aprobados->pluck('id');

        if ($aprobadosIds->isNotEmpty()) {
            $equivalenciaIds = Curso::whereIn('id', $aprobadosIds)
                ->with('equivalencias:id')
                ->get()
                ->flatMap(fn($c) => $c->equivalencias->pluck('id'));
            $aprobadosIds = $aprobadosIds->merge($equivalenciaIds)->unique();
        }

        $planNombre = $plan?->nombre ?? ($user->escuela?->nombre ?? 'Plan de estudios');
        $lines      = ["COMPARACIÓN PLAN vs. HISTORIAL — {$planNombre}:"];

        $totalOblApr = 0; $totalOblReq = 0;
        $totalEleApr = 0; $totalEleReq = 0;

        foreach ($planEntries->groupBy('ciclo')->sortKeys() as $ciclo => $cursosDeCiclo) {
            $lines[] = "";
            $lines[] = "── Ciclo {$ciclo} ──";

            // Obligatorios primero, luego electivos
            $ordenados = $cursosDeCiclo->sortBy(fn($c) => $c->tipo === 'E' ? 1 : 0);

            foreach ($ordenados as $pe) {
                $aprobado = $aprobadosIds->contains($pe->curso_id);
                $tipo     = $pe->tipo === 'O' ? 'Oblig.' : 'Electiv.';
                $marca    = $aprobado ? '[OK]' : '[--]';
                $nombre   = Str::limit($pe->curso?->nombre ?? '—', 42);
                $creds    = (int) ($pe->creditos ?? $pe->curso?->creditos ?? 0);

                $lines[] = sprintf("  %s %-44s %s — %d cr.", $marca, $nombre, $tipo, $creds);

                if ($pe->tipo === 'O') {
                    $totalOblReq += $creds;
                    if ($aprobado) $totalOblApr += $creds;
                } else {
                    $totalEleReq += $creds;
                    if ($aprobado) $totalEleApr += $creds;
                }
            }
        }

        $oblPct  = $totalOblReq > 0 ? round($totalOblApr / $totalOblReq * 100) : 0;
        $elePct  = $totalEleReq > 0 ? round($totalEleApr / $totalEleReq * 100) : 0;
        $totApr  = $totalOblApr + $totalEleApr;
        $totReq  = $totalOblReq + $totalEleReq;
        $totPct  = $totReq > 0 ? round($totApr / $totReq * 100) : 0;

        $lines[] = "";
        $lines[] = "── RESUMEN ──";
        $lines[] = "• Obligatorios : {$totalOblApr}/{$totalOblReq} cr. aprobados ({$oblPct}%)";
        $lines[] = "• Electivos    : {$totalEleApr}/{$totalEleReq} cr. aprobados ({$elePct}%)";
        $lines[] = "• TOTAL        : {$totApr}/{$totReq} cr. aprobados ({$totPct}%)";
        $lines[] = "([OK] = aprobado  [--] = pendiente)";

        return implode("\n", $lines);
    }

    // =========================================================================
    // BLOQUE ADMIN: CONSULTA DE ALUMNO POR CÓDIGO O NOMBRE
    // =========================================================================

    private function buildAdminAlumnoBlock(string $query): string
    {
        // Intentar extraer código universitario (10 dígitos, preferiblemente 05xxxxxxxx)
        $user = null;
        $codigo = $this->extractCodigoUniversitario($query);

        if ($codigo) {
            $user = User::where('codigo_universitario', $codigo)
                ->where('tipo_usuario', 'estudiante')
                ->with('escuela')
                ->first();
        }

        // Si no encontró por código, intentar por nombre
        if (!$user) {
            $nombre = $this->extractNombreEstudiante($query);
            if ($nombre) {
                $partes = preg_split('/\s+/', trim($nombre));
                $userQuery = User::where('tipo_usuario', 'estudiante');
                foreach ($partes as $parte) {
                    if (mb_strlen($parte) >= 3) {
                        $userQuery->where('name', 'like', "%{$parte}%");
                    }
                }
                $user = $userQuery->with('escuela')->first();
            }
        }

        if (!$user) return '';

        $periodo = Periodo::where('activo', true)->first();
        $escuela = $user->escuela?->nombre ?? 'No asignada';

        $lines = [
            "ALUMNO CONSULTADO: {$user->name}",
            "• Código: {$user->codigo_universitario}",
            "• Escuela: {$escuela}",
            "• Año de ingreso: " . ($user->anio_ingreso ?? '—'),
            "• Email: " . $user->getEmailInstitucional(),
            "• Ciclo estimado: " . $user->cicloActual(),
            "• Estado: " . ($user->activo ? 'Activo' : 'Inactivo'),
            "• Cuenta activada: " . ($user->hasPasswordSet() ? 'Sí' : 'No'),
            "• Egresante: " . ($user->egresante ? 'Sí' : 'No'),
        ];

        // Inscripciones del periodo activo
        if ($periodo) {
            $inscripciones = Inscripcion::where('user_id', $user->id)
                ->where('periodo_id', $periodo->id)
                ->with(['programacion.curso', 'programacion.grupoHorario.detalles'])
                ->get();

            if ($inscripciones->isNotEmpty()) {
                $lines[] = '';
                $lines[] = "Cursos inscritos — {$periodo->nombre}:";
                foreach ($inscripciones as $i) {
                    $p = $i->programacion;
                    if (!$p) continue;
                    $horario = $this->formatHorario($p->grupoHorario);
                    $lines[] = sprintf("  • %s — Grp %s / Secc %s%s",
                        Str::limit($p->curso?->nombre ?? '—', 38),
                        $p->grupo   ?? '—',
                        $p->seccion ?? '—',
                        $horario ? " — {$horario}" : ''
                    );
                }
            } else {
                $lines[] = '';
                $lines[] = "Sin inscripciones en el periodo actual.";
            }
        }

        // Progreso académico
        if ($user->escuela_id) {
            try {
                /** @var ProgresoAcademicoService $progresoSvc */
                $progresoSvc = app(ProgresoAcademicoService::class);
                $progreso    = $progresoSvc->calcularProgreso($user);

                $obl = $progreso['obligatorios'];
                $ele = $progreso['electivos'];

                $lines[] = '';
                $lines[] = "Progreso académico:";
                $lines[] = "• Plan: " . ($progreso['plan']['nombre'] ?? 'Sin plan activo');
                $lines[] = "• Obligatorios: {$obl['hechos']} / {$obl['requeridos']} cr. ({$obl['porcentaje']}%)";
                $lines[] = "• Electivos: {$ele['hechos']} / {$ele['requeridos']} cr. ({$ele['porcentaje']}%)";
                $lines[] = "• Egresante (calculado): " . ($progreso['egresante_calculado'] ? 'Sí' : 'No');

                if (!empty($obl['pendientes_por_ciclo'])) {
                    $faltanCreditos = $obl['requeridos'] - $obl['hechos'];
                    $lines[] = "• Créditos obligatorios faltantes: {$faltanCreditos} cr. en "
                             . count($obl['pendientes_por_ciclo']) . " ciclo(s)";
                }
            } catch (\Throwable) {
                // No interrumpir si falla el cálculo
            }
        }

        // Resumen historial
        $aprobados = $user->cursosAprobados()->withPivot('creditos')->get();
        if ($aprobados->isNotEmpty()) {
            $totalCred = $aprobados->sum('pivot.creditos');
            $lines[]   = '';
            $lines[]   = "Historial académico: {$aprobados->count()} cursos aprobados — {$totalCred} créditos acumulados.";
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // BLOQUE: DOCENTE
    // =========================================================================

    private function buildDocenteBlock(string $query): string
    {
        $periodo  = Periodo::where('activo', true)->first();
        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) return '';

        $docenteQuery = Docente::query();
        foreach ($keywords as $kw) {
            if (mb_strlen($kw) >= 4) {
                $docenteQuery->orWhere('nombre_completo', 'like', "%{$kw}%");
            }
        }

        $docentes = $docenteQuery->limit(3)->get();
        if ($docentes->isEmpty()) return '';

        $blocks = [];

        foreach ($docentes as $docente) {
            $lines = ["DOCENTE: {$docente->nombre_completo}"];

            if ($periodo) {
                $programaciones = ProgramacionAcademica::where('docente_id', $docente->id)
                    ->where('periodo_id', $periodo->id)
                    ->with(['curso', 'grupoHorario.detalles'])
                    ->get();

                if ($programaciones->isNotEmpty()) {
                    $lines[] = "Cursos a cargo — {$periodo->nombre}:";
                    foreach ($programaciones as $p) {
                        $horario = $this->formatHorario($p->grupoHorario);
                        $inscritos = $p->n_inscritos ?? 0;
                        $estado    = $p->estaLleno() ? 'LLENO' : "Disponible ({$inscritos} inscr.)";
                        $lines[]   = sprintf("  • %s — Grp %s / Secc %s — Aula: %s — %s%s",
                            Str::limit($p->curso?->nombre ?? '—', 36),
                            $p->grupo   ?? '—',
                            $p->seccion ?? '—',
                            $p->aula    ?? '—',
                            $estado,
                            $horario ? " — {$horario}" : ''
                        );
                    }
                } else {
                    $lines[] = "Sin cursos programados en el periodo actual.";
                }
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    // =========================================================================
    // BLOQUE: PROGRAMACIÓN ACADÉMICA (con docente + horario)
    // =========================================================================

    private function buildProgramacionBlock(string $query, ?string $escuelaId = null): string
    {
        $periodo = Periodo::where('activo', true)->first();
        if (!$periodo) return '';

        $tipo     = $this->clasificarPeriodo($periodo->nombre);
        $keywords = $this->extractKeywords($query);

        // --- Modo 1: búsqueda específica por keywords de curso ---
        if (!empty($keywords)) {
            $programaciones = $this->buscarProgramacion($periodo->id, $keywords, fuzzy: false, escuelaId: $escuelaId);

            if ($programaciones->isEmpty()) {
                $fuzzy = $this->buscarProgramacion($periodo->id, $keywords, fuzzy: true, escuelaId: $escuelaId);
                $fuzzy = $fuzzy->filter(function ($p) use ($keywords) {
                    $nombre = strtolower($p->curso?->nombre ?? '');
                    foreach ($keywords as $kw) {
                        $sinPrimero = mb_substr($kw, 1);
                        if (mb_strlen($sinPrimero) >= 5 && str_contains($nombre, $sinPrimero)) {
                            return true;
                        }
                    }
                    return false;
                });
                $programaciones = $fuzzy;
            }

            if ($programaciones->isNotEmpty()) {
                $lines = ["PROGRAMACIÓN ACADÉMICA — {$periodo->nombre} ({$tipo}):"];

                foreach ($programaciones as $p) {
                    $inscritos = $p->n_inscritos ?? 0;
                    $libres    = max(0, ($p->capacidad ?? 0) - $inscritos);
                    $estado    = $p->estaLleno() ? 'LLENO' : "Disponible ({$libres} lib.)";
                    $horario   = $this->formatHorario($p->grupoHorario);
                    $docente   = $p->docente?->nombre_completo ?? '—';

                    $lines[] = sprintf("• %s — Grp %s / Secc %s — Cap. %s / Inscr. %s — Aula: %s — %s",
                        Str::limit($p->curso?->nombre ?? '—', 38),
                        $p->grupo    ?? '—',
                        $p->seccion  ?? '—',
                        $p->capacidad ?? '—',
                        $inscritos,
                        $p->aula     ?? '—',
                        $estado
                    );
                    if ($horario) $lines[] = "  Horario: {$horario}";
                    if ($docente !== '—') $lines[] = "  Docente: {$docente}";
                }

                return implode("\n", $lines);
            }
        }

        // --- Modo 2: resumen general ---
        $baseQuery = ProgramacionAcademica::where('periodo_id', $periodo->id);
        if ($escuelaId) {
            $baseQuery->whereExists(function ($sub) use ($escuelaId) {
                $sub->from('programacion_escuelas')
                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                    ->where('programacion_escuelas.escuela_id', $escuelaId);
            });
        }

        $total       = (clone $baseQuery)->count();
        $todos        = (clone $baseQuery)->get();
        $disponibles  = $todos->filter(fn($p) => !$p->estaLleno())->count();
        $llenos       = $total - $disponibles;

        $ejemplos = (clone $baseQuery)
            ->with('curso')
            ->get()
            ->filter(fn($p) => !$p->estaLleno())
            ->take(15);

        $lines = [
            "PROGRAMACIÓN ACADÉMICA — {$periodo->nombre} ({$tipo}):",
            "• Total de secciones: {$total}",
            "• Secciones disponibles: {$disponibles}",
            "• Secciones llenas: {$llenos}",
            "",
            "Muestra de cursos disponibles (pregunta por un curso específico para ver todos sus horarios):",
        ];

        foreach ($ejemplos as $p) {
            $lines[] = sprintf("  • %s — Grp %s / Secc %s — Cap. %s — Aula: %s",
                Str::limit($p->curso?->nombre ?? '—', 40),
                $p->grupo    ?? '—',
                $p->seccion  ?? '—',
                $p->capacidad ?? '—',
                $p->aula     ?? '—'
            );
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // BLOQUE: PLAN DE ESTUDIOS
    // =========================================================================

    private function buildPlanEstudiosBlock(string $query, ?string $defaultEscuelaId = null): string
    {
        $qLower        = strtolower($query);
        $escuelaFiltro = $this->detectarEscuela($qLower);
        $cicloFiltro   = $this->detectarCiclo($qLower);

        $escuelasQuery = Escuela::with(['planEstudios.curso']);

        if (!$escuelaFiltro && $defaultEscuelaId) {
            $escuelasQuery->where('id', $defaultEscuelaId);
        }

        $escuelas = $escuelasQuery->get();
        if ($escuelas->isEmpty()) return '';

        $blocks = [];

        foreach ($escuelas as $escuela) {
            if ($escuelaFiltro) {
                $nombreLow = strtolower($escuela->nombre);
                $match = match($escuelaFiltro) {
                    'industrial'     => str_contains($nombreLow, 'industrial') && !str_contains($nombreLow, 'agroindustrial'),
                    'agroindustrial' => str_contains($nombreLow, 'agroindustrial'),
                    default          => str_contains($nombreLow, $escuelaFiltro)
                                        || str_contains(strtolower($escuela->nombre_corto ?? ''), $escuelaFiltro),
                };
                if (!$match) continue;
            }

            $plan = $escuela->planEstudios;
            if ($plan->isEmpty()) continue;

            if ($cicloFiltro) {
                $plan = $plan->where('ciclo', $cicloFiltro);
            }
            if ($plan->isEmpty()) continue;

            if ($cicloFiltro) {
                $titulo  = "Plan de Estudios — {$escuela->nombre} — Ciclo {$cicloFiltro}:";
                $lines   = [$titulo];
                $lines[] = sprintf("%-8s %-45s %-6s %-8s %-12s",
                    'Código', 'Curso', 'Ciclo', 'Cred.', 'Tipo');
                $lines[] = str_repeat('-', 82);

                foreach ($plan->sortBy('ciclo') as $pe) {
                    $tipo    = $pe->tipo === 'O' ? 'Obligatorio' : 'Electivo';
                    $lines[] = sprintf("%-8s %-45s %-6s %-8s %-12s",
                        $pe->curso?->codigo ?? '—',
                        Str::limit($pe->curso?->nombre ?? '—', 43),
                        $pe->ciclo,
                        $pe->creditos,
                        $tipo
                    );
                }
                $blocks[] = implode("\n", $lines);
            } else {
                $lines    = ["Plan de Estudios — {$escuela->nombre}:"];
                $porCiclo = $plan->groupBy('ciclo')->sortKeys();
                foreach ($porCiclo as $ciclo => $cursos) {
                    $lines[] = "  Ciclo {$ciclo}: " . $cursos->count() . " cursos ("
                        . $cursos->where('tipo', 'O')->count() . " oblig., "
                        . $cursos->where('tipo', 'E')->count() . " electiv.)";
                }
                $blocks[] = implode("\n", $lines);
            }
        }

        if (empty($blocks)) return '';

        return "PLAN DE ESTUDIOS FII-UNP:\n\n" . implode("\n\n", $blocks);
    }

    // =========================================================================
    // HELPERS: FORMATEO
    // =========================================================================

    /**
     * Formatea el horario de un GrupoHorario como string legible.
     * Ejemplo: "Lun 08:00-10:00, Mié 08:00-10:00"
     */
    private function formatHorario(?GrupoHorario $gh): string
    {
        if (!$gh || $gh->detalles->isEmpty()) return '';

        $abrevDias = [
            'lunes'     => 'Lun',
            'martes'    => 'Mar',
            'miercoles' => 'Mié',
            'miércoles' => 'Mié',
            'jueves'    => 'Jue',
            'viernes'   => 'Vie',
            'sabado'    => 'Sáb',
            'sábado'    => 'Sáb',
        ];

        $parts = [];
        foreach ($gh->detalles as $d) {
            $dia    = $abrevDias[strtolower($d->dia_semana)] ?? ucfirst($d->dia_semana);
            $inicio = substr((string) $d->hora_inicio, 0, 5);
            $fin    = substr((string) $d->hora_fin,    0, 5);
            $parts[] = "{$dia} {$inicio}-{$fin}";
        }

        return implode(', ', $parts);
    }

    // =========================================================================
    // HELPERS: EXTRACCIÓN DE ENTIDADES
    // =========================================================================

    private function extractCodigoUniversitario(string $query): ?string
    {
        // Código de 10 dígitos (preferentemente empieza con 05)
        if (preg_match('/\b(05\d{8})\b/', $query, $m)) return $m[1];
        if (preg_match('/\b(\d{10})\b/',  $query, $m)) return $m[1];
        return null;
    }

    private function extractNombreEstudiante(string $query): ?string
    {
        // "alumno JUAN PEREZ" o "estudiante Juan Pérez García"
        if (preg_match(
            '/(?:alumno|estudiante|alumna)\s+((?:[A-ZÁÉÍÓÚÜÑ][a-záéíóúüñA-ZÁÉÍÓÚÜÑ]*\s+){1,4}[A-ZÁÉÍÓÚÜÑ][a-záéíóúüñA-ZÁÉÍÓÚÜÑ]*)/u',
            $query,
            $m
        )) {
            return trim($m[1]);
        }
        return null;
    }

    // =========================================================================
    // HELPERS: BÚSQUEDA DE PROGRAMACION
    // =========================================================================

    private function buscarProgramacion(
        string  $periodoId,
        array   $keywords,
        bool    $fuzzy,
        ?string $escuelaId = null
    ): \Illuminate\Support\Collection {
        $q = ProgramacionAcademica::with(['curso', 'docente', 'grupoHorario.detalles'])
            ->where('periodo_id', $periodoId)
            ->whereHas('curso', function ($q) use ($keywords, $fuzzy) {
                $q->where(function ($inner) use ($keywords, $fuzzy) {
                    foreach ($keywords as $kw) {
                        $inner->orWhere('nombre', 'like', "%{$kw}%")
                              ->orWhere('codigo', 'like', "%{$kw}%");

                        if ($fuzzy) {
                            if (mb_strlen($kw) >= 7) {
                                $prefix = mb_substr($kw, 0, 8);
                                $inner->orWhere('nombre', 'like', "%{$prefix}%");
                            }
                            if (mb_strlen($kw) >= 6) {
                                $sinPrimero = mb_substr($kw, 1);
                                $inner->orWhere('nombre', 'like', "%{$sinPrimero}%");
                            }
                        }
                    }
                });
            });

        if ($escuelaId) {
            $q->whereExists(function ($sub) use ($escuelaId) {
                $sub->from('programacion_escuelas')
                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                    ->where('programacion_escuelas.escuela_id', $escuelaId);
            });
        }

        return $q->limit(25)->get();
    }

    // =========================================================================
    // HELPERS: DETECCIÓN DE INTENCIÓN
    // =========================================================================

    private function querySeemsAboutAlumno(string $query): bool
    {
        // Código universitario explícito
        if (preg_match('/\b\d{10}\b/', $query)) return true;

        $q        = strtolower($query);
        $triggers = [
            'alumno', 'alumna', 'estudiante', 'ver perfil',
            'progreso de', 'historial de', 'créditos de', 'creditos de',
            'inscripciones de', 'cursos de', 'información del alumno',
            'buscar alumno', 'consultar alumno', 'datos del alumno',
        ];
        foreach ($triggers as $t) {
            if (str_contains($q, $t)) return true;
        }
        return false;
    }

    private function querySeemsAboutDocente(string $query): bool
    {
        $q        = strtolower($query);
        $triggers = [
            'docente', 'profesor', 'profesora', 'maestro', 'maestra',
            'quién enseña', 'quien enseña', 'quién dicta', 'quien dicta',
            'quién da clases', 'quien da clases', 'quién imparte', 'quien imparte',
            'qué docente', 'que docente', 'cuál es el profesor', 'cual es el profesor',
        ];
        foreach ($triggers as $t) {
            if (str_contains($q, $t)) return true;
        }
        return false;
    }

    private function querySeemsAboutCursos(string $query): bool
    {
        $triggers = [
            'curso', 'materia', 'asignatura', 'clase', 'horario', 'cupo',
            'lleno', 'disponible', 'inscribir', 'inscripción', 'inscripcion',
            'sección', 'seccion', 'grupo', 'aula', 'laboratorio', 'clave',
            'programación', 'programacion', 'verano', 'nivelacion', 'nivelación',
        ];
        $q = strtolower($query);
        foreach ($triggers as $t) {
            if (str_contains($q, $t)) return true;
        }
        return false;
    }

    private function querySeemsAboutMisInscripciones(string $query): bool
    {
        $q        = strtolower($query);
        $triggers = [
            'mis cursos', 'mis materias', 'estoy inscrito', 'me inscrib',
            'qué llevo', 'que llevo', 'cuáles llevo', 'cuales llevo',
            'qué tengo', 'que tengo este', 'mis clases', 'mi matrícula',
            'mi matricula', 'llevo este', 'llevo ahora', 'tengo este ciclo',
            'estoy llevando', 'estoy cursando', 'estoy matriculado',
            'cuántos cursos llevo', 'cuantos cursos llevo',
        ];
        foreach ($triggers as $t) {
            if (str_contains($q, $t)) return true;
        }
        return false;
    }

    private function querySeemsAboutHistorial(string $query): bool
    {
        $q        = strtolower($query);
        $triggers = [
            'historial', 'aprobados', 'créditos aprobados', 'creditos aprobados',
            'mi progreso', 'qué aprobé', 'que aprobe', 'cuántos créditos', 'cuantos creditos',
            'cursos me faltan', 'qué me falta', 'que me falta', 'cuánto me falta',
            'cuanto me falta', 'cursos pendientes', 'mis notas anteriores',
            'avance académico', 'avance academico', 'cuántos cursos he aprobado',
            'cuantos cursos he aprobado',
        ];
        foreach ($triggers as $t) {
            if (str_contains($q, $t)) return true;
        }
        return false;
    }

    private function querySeemsAboutComparacion(string $query): bool
    {
        $q = strtolower($query);

        // Pregunta explícita de comparación historial vs. plan
        $triggers = [
            'comparar', 'compararlo', 'compare', 'versus', 'vs plan',
            'qué me falta', 'que me falta', 'cuánto me falta', 'cuanto me falta',
            'cursos me faltan', 'cursos pendientes', 'cursos que me faltan',
            'qué cursos me faltan', 'que cursos me faltan',
            'faltan para graduarme', 'faltan para egresar', 'faltan para terminar',
            'cuántos créditos me faltan', 'cuantos creditos me faltan',
            'mi avance', 'mi progreso', 'avance académico', 'avance academico',
            'falta para terminar', 'falta para acabar la carrera',
            'historial con mi plan', 'historial y mi plan', 'plan de estudios',
        ];

        foreach ($triggers as $t) {
            if (str_contains($q, $t)) return true;
        }

        // "historial" + "plan" juntos en la misma pregunta
        if (str_contains($q, 'historial') && str_contains($q, 'plan')) return true;
        if (str_contains($q, 'aprobados') && str_contains($q, 'plan'))  return true;
        if (str_contains($q, 'historial') && str_contains($q, 'faltan')) return true;

        return false;
    }

    private function querySeemsAboutPlanEstudios(string $query): bool
    {
        $q = strtolower($query);
        $strong = [
            'plan', 'malla', 'crédito', 'credito',
            'obligatorio', 'electivo', 'pensum', 'prerrequisito',
            'currícula', 'curricula', 'ingeniería industrial',
            'ingeniería informática', 'agroindustrial', 'mecatrónica',
        ];
        foreach ($strong as $t) {
            if (str_contains($q, $t)) return true;
        }
        if (str_contains($q, 'ciclo') || str_contains($q, 'semestre')) {
            $curricular = ['llevar', 'ver', 'tomar', 'cursar', 'carrera', 'escuela',
                           'requisito', 'créditos', 'creditos', 'materias'];
            foreach ($curricular as $c) {
                if (str_contains($q, $c)) return true;
            }
            if (preg_match('/\d+/', $q) || preg_match('/\b(I{1,3}|IV|V|VI{0,3}|IX|X)\b/i', $q)) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    // HELPERS: CLASIFICACIÓN Y DETECCIÓN
    // =========================================================================

    private function clasificarPeriodo(string $nombre): string
    {
        if (preg_match('/\d{4}[-\s]?0$/i', $nombre)) return 'Verano / Nivelación';
        if (preg_match('/\d{4}[-\s]?1$/i', $nombre)) return 'Semestre I';
        if (preg_match('/\d{4}[-\s]?2$/i', $nombre)) return 'Semestre II';
        return $nombre;
    }

    private function extractKeywords(string $query): array
    {
        $stopwords = ['para', 'como', 'cual', 'cuál', 'que', 'qué', 'tiene', 'tengo',
                      'quiero', 'puedo', 'saber', 'sobre', 'acerca', 'este', 'esta',
                      'esto', 'los', 'las', 'del', 'una', 'uno', 'hay', 'cómo',
                      'cuándo', 'cuando', 'donde', 'dónde', 'cuántos', 'cuantos',
                      'cursos', 'curso', 'materia', 'asignatura', 'clase', 'plan',
                      'estudios', 'ciclo', 'semestre', 'periodo', 'horario',
                      'docente', 'profesor', 'alumno', 'estudiante'];

        $words    = preg_split('/\s+/', strtolower($query));
        $keywords = [];

        foreach ($words as $word) {
            $word = preg_replace('/[^a-záéíóúüñ]/u', '', $word);
            if (mb_strlen($word) >= 4 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }

        return array_unique($keywords);
    }

    private function detectarEscuela(string $query): ?string
    {
        $map = [
            'agroindustrial' => 'agroindustrial',
            'industrial'     => 'industrial',
            'informática'    => 'informática',
            'informatica'    => 'informática',
            'mecatrónica'    => 'mecatrónica',
            'mecatronica'    => 'mecatrónica',
        ];
        foreach ($map as $trigger => $valor) {
            if (str_contains($query, $trigger)) return $valor;
        }
        return null;
    }

    private function detectarCiclo(string $query): ?int
    {
        if (preg_match('/ciclo\s+(\d+)/i', $query, $m)) return (int) $m[1];
        if (preg_match('/(\d+)(?:er|do|ro|to|vo|avo|°)\s+ciclo/i', $query, $m)) return (int) $m[1];

        $romanos = ['X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7, 'VI' => 6,
                    'V' => 5,  'IV' => 4,  'III' => 3,  'II' => 2,  'I' => 1];

        if (preg_match('/\b(X|IX|VIII|VII|VI|V|IV|III|II|I)\s+ciclo\b/i', $query, $m))
            return $romanos[strtoupper($m[1])] ?? null;
        if (preg_match('/\bciclo\s+(X|IX|VIII|VII|VI|V|IV|III|II|I)\b/i', $query, $m))
            return $romanos[strtoupper($m[1])] ?? null;

        $ordinales = ['primer' => 1, 'primero' => 1, 'segundo' => 2, 'tercer' => 3,
                      'tercero' => 3, 'cuarto' => 4, 'quinto' => 5, 'sexto' => 6,
                      'séptimo' => 7, 'septimo' => 7, 'octavo' => 8,
                      'noveno' => 9, 'décimo' => 10, 'decimo' => 10];
        foreach ($ordinales as $palabra => $num) {
            if (str_contains($query, $palabra)) return $num;
        }
        return null;
    }
}
