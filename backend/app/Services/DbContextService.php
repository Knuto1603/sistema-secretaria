<?php

namespace App\Services;

use App\Models\Escuela;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\PlanEstudios;
use App\Models\ProgramacionAcademica;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Inyecta datos vivos de la base de datos en el contexto del chatbot.
 *
 * Cuando se provee un $user estudiante, incluye su información personal
 * (perfil, inscripciones del ciclo actual, historial académico) y filtra
 * la programación y el plan de estudios a su escuela.
 */
class DbContextService
{
    /**
     * Construye el bloque de contexto de BD para la pregunta dada.
     */
    public function buildContext(string $query, ?User $user = null): string
    {
        $blocks      = [];
        $esEstudiante = $user && $user->isEstudiante();
        $escuelaId   = $esEstudiante ? $user->escuela_id : null;

        // Siempre: periodo activo
        $periodoBlock = $this->buildPeriodoBlock();
        if ($periodoBlock) {
            $blocks[] = $periodoBlock;
        }

        // Siempre: autoridades (si están configuradas)
        $autoridadesBlock = $this->buildAutoridadesBlock();
        if ($autoridadesBlock) {
            $blocks[] = $autoridadesBlock;
        }

        // Si es estudiante: perfil académico personal (siempre)
        if ($esEstudiante) {
            $user->loadMissing('escuela');
            $blocks[] = $this->buildEstudiantePerfilBlock($user);
        }

        // Si es estudiante y pregunta sobre sus cursos actuales
        if ($esEstudiante && $this->querySeemsAboutMisInscripciones($query)) {
            $inscBlock = $this->buildMisInscripcionesBlock($user);
            if ($inscBlock) {
                $blocks[] = $inscBlock;
            }
        }

        // Condicional: programación académica (filtrada por escuela del estudiante)
        if ($this->querySeemsAboutCursos($query)) {
            $progBlock = $this->buildProgramacionBlock($query, $escuelaId);
            if ($progBlock) {
                $blocks[] = $progBlock;
            }
        }

        // Si es estudiante y pregunta sobre su historial / créditos
        if ($esEstudiante && $this->querySeemsAboutHistorial($query)) {
            $histBlock = $this->buildMiHistorialBlock($user);
            if ($histBlock) {
                $blocks[] = $histBlock;
            }
        }

        // Condicional: plan de estudios (usa la escuela del estudiante por defecto)
        if ($this->querySeemsAboutPlanEstudios($query)) {
            $planBlock = $this->buildPlanEstudiosBlock($query, $escuelaId);
            if ($planBlock) {
                $blocks[] = $planBlock;
            }
        }

        if (empty($blocks)) {
            return '';
        }

        return "=== INFORMACIÓN ACADÉMICA ACTUAL (BASE DE DATOS) ===\n\n"
            . implode("\n\n", $blocks);
    }

    // =========================================================================
    // BLOQUES INDIVIDUALES
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

    /**
     * Perfil académico personal del estudiante.
     * Se incluye siempre que haya un usuario estudiante.
     */
    private function buildEstudiantePerfilBlock(User $user): string
    {
        $escuela  = $user->escuela?->nombre ?? 'No asignada';
        $ciclo    = $user->cicloActual();
        $ingreso  = $user->anio_ingreso ? "Promoción {$user->anio_ingreso}" : '—';
        $estado   = $user->activo ? 'Activo' : 'Inactivo';
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

    /**
     * Inscripciones del estudiante en el periodo activo actual.
     */
    private function buildMisInscripcionesBlock(User $user): string
    {
        $periodo = Periodo::where('activo', true)->first();
        if (!$periodo) {
            return '';
        }

        $inscripciones = Inscripcion::where('user_id', $user->id)
            ->where('periodo_id', $periodo->id)
            ->with(['programacion.curso'])
            ->get();

        $tipo = $this->clasificarPeriodo($periodo->nombre);

        if ($inscripciones->isEmpty()) {
            return "TUS CURSOS INSCRITOS — {$periodo->nombre} ({$tipo}):\n"
                 . "No tienes inscripciones registradas en el sistema para este periodo.";
        }

        $lines   = ["TUS CURSOS INSCRITOS — {$periodo->nombre} ({$tipo}):"];
        $lines[] = sprintf("%-38s %-6s %-7s %-10s %-12s",
            'Curso', 'Grupo', 'Secc.', 'Estado', 'Aula');
        $lines[] = str_repeat('-', 78);

        foreach ($inscripciones as $i) {
            $p = $i->programacion;
            if (!$p) continue;
            $estado  = $p->estaLleno() ? 'LLENO' : 'Disponible';
            $lines[] = sprintf("%-38s %-6s %-7s %-10s %-12s",
                Str::limit($p->curso?->nombre ?? '—', 36),
                $p->grupo    ?? '—',
                $p->seccion  ?? '—',
                $estado,
                $p->aula     ?? '—'
            );
        }

        $count   = $inscripciones->count();
        $lines[] = "({$count} curso(s) inscrito(s) en el sistema)";

        return implode("\n", $lines);
    }

    /**
     * Historial académico resumido del estudiante.
     */
    private function buildMiHistorialBlock(User $user): string
    {
        if (!$user->tieneHistorial()) {
            return "TU HISTORIAL ACADÉMICO:\n"
                 . "Aún no tienes historial académico registrado en el sistema. "
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

    /**
     * Programación del periodo activo.
     *
     * Modo 1 — Búsqueda específica: si la pregunta menciona un curso concreto,
     *           muestra solo los cursos que coinciden (hasta 25).
     *           Si $escuelaId se provee, filtra a secciones habilitadas para esa escuela.
     *
     * Modo 2 — Resumen general: muestra totales + primeros cursos disponibles.
     */
    private function buildProgramacionBlock(string $query, ?string $escuelaId = null): string
    {
        $periodo = Periodo::where('activo', true)->first();
        if (!$periodo) {
            return '';
        }

        $tipo     = $this->clasificarPeriodo($periodo->nombre);
        $keywords = $this->extractKeywords($query);

        // --- Modo 1: keywords coinciden con nombres de cursos ---
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
                $lines   = ["PROGRAMACIÓN ACADÉMICA — {$periodo->nombre} ({$tipo}):"];
                $lines[] = sprintf("%-38s %-6s %-5s %-8s %-8s %-7s %-10s %-12s",
                    'Curso', 'Grupo', 'Secc.', 'Capac.', 'Inscrit.', 'Libres', 'Estado', 'Aula');
                $lines[] = str_repeat('-', 106);

                foreach ($programaciones as $p) {
                    $inscritos = $p->n_inscritos ?? 0;
                    $libres    = max(0, ($p->capacidad ?? 0) - $inscritos);
                    $estado    = $p->estaLleno() ? 'LLENO' : 'Disponible';
                    $lines[]   = sprintf("%-38s %-6s %-5s %-8s %-8s %-7s %-10s %-12s",
                        Str::limit($p->curso?->nombre ?? '—', 36),
                        $p->grupo    ?? '—',
                        $p->seccion  ?? '—',
                        $p->capacidad ?? '—',
                        $inscritos,
                        $libres,
                        $estado,
                        $p->aula     ?? '—'
                    );
                }

                return implode("\n", $lines);
            }
        }

        // --- Modo 2: resumen general de disponibilidad ---
        $baseQuery = ProgramacionAcademica::where('periodo_id', $periodo->id);
        if ($escuelaId) {
            $baseQuery->whereExists(function ($sub) use ($escuelaId) {
                $sub->from('programacion_escuelas')
                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                    ->where('programacion_escuelas.escuela_id', $escuelaId);
            });
        }

        $total      = (clone $baseQuery)->count();
        $disponibles = (clone $baseQuery)->get()->filter(fn($p) => !$p->estaLleno())->count();
        $llenos     = $total - $disponibles;

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
            sprintf("%-45s %-6s %-5s %-8s %-12s", 'Curso', 'Grupo', 'Secc.', 'Capac.', 'Aula'),
            str_repeat('-', 80),
        ];

        foreach ($ejemplos as $p) {
            $lines[] = sprintf("%-45s %-6s %-5s %-8s %-12s",
                Str::limit($p->curso?->nombre ?? '—', 43),
                $p->grupo    ?? '—',
                $p->seccion  ?? '—',
                $p->capacidad ?? '—',
                $p->aula     ?? '—'
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Plan de estudios desde la base de datos.
     *
     * Si $defaultEscuelaId se provee (estudiante) y no se detecta escuela en la pregunta,
     * muestra solo el plan de esa escuela.
     */
    private function buildPlanEstudiosBlock(string $query, ?string $defaultEscuelaId = null): string
    {
        $qLower   = strtolower($query);
        $keywords = $this->extractKeywords($query);

        $escuelaFiltro = $this->detectarEscuela($qLower);
        $cicloFiltro   = $this->detectarCiclo($qLower);

        $escuelasQuery = Escuela::with(['planEstudios.curso']);

        // Si no se detecta escuela por keyword y hay una por defecto (escuela del estudiante),
        // filtrar directamente por UUID para evitar mostrar todas las escuelas.
        if (!$escuelaFiltro && $defaultEscuelaId) {
            $escuelasQuery->where('id', $defaultEscuelaId);
        }

        $escuelas = $escuelasQuery->get();

        if ($escuelas->isEmpty()) {
            return '';
        }

        $blocks = [];

        foreach ($escuelas as $escuela) {
            // Filtrar por keyword de escuela si se detectó alguna
            if ($escuelaFiltro) {
                $nombreLow = strtolower($escuela->nombre);
                $match = match($escuelaFiltro) {
                    'industrial'     => str_contains($nombreLow, 'industrial')
                                        && !str_contains($nombreLow, 'agroindustrial'),
                    'agroindustrial' => str_contains($nombreLow, 'agroindustrial'),
                    default          => str_contains($nombreLow, $escuelaFiltro)
                                        || str_contains(strtolower($escuela->nombre_corto ?? ''), $escuelaFiltro),
                };
                if (!$match) continue;
            }

            $plan = $escuela->planEstudios;

            if ($plan->isEmpty()) {
                continue;
            }

            if ($cicloFiltro) {
                $plan = $plan->where('ciclo', $cicloFiltro);
            }

            if ($plan->isEmpty()) {
                continue;
            }

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

        if (empty($blocks)) {
            return '';
        }

        return "PLAN DE ESTUDIOS FII-UNP:\n\n" . implode("\n\n", $blocks);
    }

    // =========================================================================
    // HELPERS DE DETECCIÓN
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
                      'estudios', 'ciclo', 'semestre', 'periodo', 'horario'];

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
            if (str_contains($query, $trigger)) {
                return $valor;
            }
        }

        return null;
    }

    private function detectarCiclo(string $query): ?int
    {
        if (preg_match('/ciclo\s+(\d+)/i', $query, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/(\d+)(?:er|do|ro|to|vo|avo|°)\s+ciclo/i', $query, $m)) {
            return (int) $m[1];
        }

        $romanos = [
            'X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7, 'VI' => 6,
            'V' => 5,  'IV' => 4,  'III' => 3,  'II' => 2,  'I' => 1,
        ];

        if (preg_match('/\b(X|IX|VIII|VII|VI|V|IV|III|II|I)\s+ciclo\b/i', $query, $m)) {
            return $romanos[strtoupper($m[1])] ?? null;
        }
        if (preg_match('/\bciclo\s+(X|IX|VIII|VII|VI|V|IV|III|II|I)\b/i', $query, $m)) {
            return $romanos[strtoupper($m[1])] ?? null;
        }
        if (preg_match('/\b(?:mi|el|del?|al?)\s+(X|IX|VIII|VII|VI|V|IV|III|II|I)\s+ciclo\b/i', $query, $m)) {
            return $romanos[strtoupper($m[1])] ?? null;
        }

        $ordinales = [
            'primer' => 1, 'primero' => 1,
            'segundo' => 2,
            'tercer' => 3, 'tercero' => 3,
            'cuarto' => 4,
            'quinto' => 5,
            'sexto' => 6,
            'séptimo' => 7, 'septimo' => 7,
            'octavo' => 8,
            'noveno' => 9,
            'décimo' => 10, 'decimo' => 10,
        ];

        foreach ($ordinales as $palabra => $num) {
            if (str_contains($query, $palabra)) {
                return $num;
            }
        }

        return null;
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
            $curricular = [
                'llevar', 'ver', 'tomar', 'cursar', 'carrera', 'escuela',
                'requisito', 'créditos', 'creditos', 'materias',
            ];
            foreach ($curricular as $c) {
                if (str_contains($q, $c)) return true;
            }
            if (preg_match('/\d+/', $q) || preg_match('/\b(I{1,3}|IV|V|VI{0,3}|IX|X)\b/i', $q)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta si la pregunta es sobre las inscripciones personales del estudiante
     * en el periodo actual.
     */
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

    /**
     * Detecta si la pregunta es sobre el historial académico del estudiante.
     */
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

    /**
     * Busca programaciones del periodo por keywords.
     * Si fuzzy=false: solo búsqueda exacta (LIKE '%keyword%').
     * Si fuzzy=true: además prueba prefijo de 8 chars y substring sin primer carácter.
     * Si escuelaId se provee: filtra a secciones habilitadas para esa escuela.
     */
    private function buscarProgramacion(
        string  $periodoId,
        array   $keywords,
        bool    $fuzzy,
        ?string $escuelaId = null
    ): \Illuminate\Support\Collection {
        $q = ProgramacionAcademica::with('curso')
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
}
