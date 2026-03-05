<?php

namespace App\Services;

use App\Models\Escuela;
use App\Models\Periodo;
use App\Models\PlanEstudios;
use App\Models\ProgramacionAcademica;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

/**
 * Inyecta datos vivos de la base de datos en el contexto del chatbot.
 * NO incluye información personal de alumnos ni del personal docente/administrativo.
 * Solo excepción: nombre del Decano y Secretario Académico (desde system_settings).
 */
class DbContextService
{
    /**
     * Construye el bloque de contexto de BD para la pregunta dada.
     */
    public function buildContext(string $query): string
    {
        $blocks = [];

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

        // Condicional: programación académica
        if ($this->querySeemsAboutCursos($query)) {
            $progBlock = $this->buildProgramacionBlock($query);
            if ($progBlock) {
                $blocks[] = $progBlock;
            }
        }

        // Condicional: plan de estudios
        if ($this->querySeemsAboutPlanEstudios($query)) {
            $planBlock = $this->buildPlanEstudiosBlock($query);
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

        $tipo    = $this->clasificarPeriodo($periodo->nombre);
        $inicio  = $periodo->fecha_inicio?->format('d/m/Y') ?? '—';
        $fin     = $periodo->fecha_fin?->format('d/m/Y')    ?? '—';

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
     * Programación del periodo activo.
     *
     * Modo 1 — Búsqueda específica: si la pregunta menciona un curso concreto,
     *           muestra solo los cursos que coinciden (hasta 25).
     *
     * Modo 2 — Resumen general: si la pregunta es sobre disponibilidad/horarios
     *           en general, muestra un resumen de totales + primeros disponibles.
     */
    private function buildProgramacionBlock(string $query): string
    {
        $periodo = Periodo::where('activo', true)->first();
        if (!$periodo) {
            return '';
        }

        $tipo     = $this->clasificarPeriodo($periodo->nombre);
        $keywords = $this->extractKeywords($query);

        // --- Modo 1: keywords coinciden con nombres de cursos ---
        if (!empty($keywords)) {
            // Búsqueda exacta primero
            $programaciones = $this->buscarProgramacion($periodo->id, $keywords, fuzzy: false);

            // Si no hay resultados exactos, intentar con tolerancia a typos (un carácter de diferencia)
            // Solo se acepta el resultado fuzzy si TODOS los cursos encontrados contienen al menos
            // 2 de los keywords (evitar falsos positivos como "computad" matcheando cursos no relacionados)
            if ($programaciones->isEmpty()) {
                $fuzzy = $this->buscarProgramacion($periodo->id, $keywords, fuzzy: true);
                // Validar que los resultados fuzzy son coherentes: el nombre del curso debe contener
                // al menos una keyword con 4+ chars en común (no solo el prefijo corto)
                $fuzzy = $fuzzy->filter(function ($p) use ($keywords) {
                    $nombre = strtolower($p->curso?->nombre ?? '');
                    foreach ($keywords as $kw) {
                        // Verificar que comparten al menos 5 chars en común consecutivos
                        $sinPrimero = mb_substr($kw, 1); // skip-first typo
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
                // Nota: la columna 'Clave' es interna y NO debe mostrarse al estudiante.
                // El estudiante identifica su sección por Grupo + Sección (tal como aparece en SIGA).
                $lines[] = sprintf("%-38s %-6s %-5s %-8s %-8s %-7s %-10s %-12s",
                    'Curso', 'Grupo', 'Secc.', 'Capac.', 'Inscrit.', 'Libres', 'Estado', 'Aula');
                $lines[] = str_repeat('-', 106);

                foreach ($programaciones as $p) {
                    $inscritos = $p->n_inscritos ?? 0;
                    $libres    = max(0, ($p->capacidad ?? 0) - $inscritos);
                    $estado    = $p->estaLleno() ? 'LLENO' : 'Disponible';
                    $lines[]   = sprintf("%-38s %-6s %-5s %-8s %-8s %-7s %-10s %-12s",
                        Str::limit($p->curso?->nombre ?? '—', 36),
                        $p->grupo ?? '—',
                        $p->seccion ?? '—',
                        $p->capacidad ?? '—',
                        $inscritos,
                        $libres,
                        $estado,
                        $p->aula ?? '—'
                    );
                }

                return implode("\n", $lines);
            }
        }

        // --- Modo 2: resumen general de disponibilidad ---
        $total      = ProgramacionAcademica::where('periodo_id', $periodo->id)->count();
        $disponibles = ProgramacionAcademica::where('periodo_id', $periodo->id)
            ->get()
            ->filter(fn($p) => !$p->estaLleno())
            ->count();
        $llenos = $total - $disponibles;

        // Muestra los primeros cursos disponibles como ejemplo
        $ejemplos = ProgramacionAcademica::with('curso')
            ->where('periodo_id', $periodo->id)
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
                $p->grupo ?? '—',
                $p->seccion ?? '—',
                $p->capacidad ?? '—',
                $p->aula ?? '—'
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Plan de estudios desde la base de datos.
     *
     * Detecta automáticamente si la pregunta es sobre:
     * - Una escuela/carrera específica  → filtra por escuela
     * - Un ciclo específico             → filtra por ciclo
     * - Un curso específico             → filtra por keyword
     * - General                         → muestra estructura resumida de todas las escuelas
     */
    private function buildPlanEstudiosBlock(string $query): string
    {
        $qLower   = strtolower($query);
        $keywords = $this->extractKeywords($query);

        // Detectar escuela mencionada
        $escuelaFiltro = $this->detectarEscuela($qLower);

        // Detectar ciclo mencionado (e.g. "ciclo 5", "5to ciclo", "quinto")
        $cicloFiltro = $this->detectarCiclo($qLower);

        $escuelas = Escuela::with(['planEstudios.curso'])->get();

        if ($escuelas->isEmpty()) {
            return '';
        }

        $blocks = [];

        foreach ($escuelas as $escuela) {
            // Filtrar por escuela si se detectó una
            if ($escuelaFiltro) {
                $nombreLow = strtolower($escuela->nombre);
                $match = match($escuelaFiltro) {
                    // "industrial" sin "agro" → solo Ingeniería Industrial
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

            // Filtrar por ciclo si se detectó
            if ($cicloFiltro) {
                $plan = $plan->where('ciclo', $cicloFiltro);
            }

            if ($plan->isEmpty()) {
                continue;
            }

            if ($cicloFiltro) {
                // MODO DETALLE: ciclo específico → mostrar todos los cursos de ese ciclo
                $titulo = "Plan de Estudios — {$escuela->nombre} — Ciclo {$cicloFiltro}:";
                $lines  = [$titulo];
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
                // MODO RESUMEN: sin ciclo específico → estructura general por ciclos
                // (ignorar keywords para mostrar el plan completo de la escuela)
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

    /**
     * Detecta si la pregunta menciona una escuela/carrera específica.
     * Retorna un fragmento del nombre en minúsculas para hacer str_contains.
     */
    private function detectarEscuela(string $query): ?string
    {
        // Orden importa: verificar los más específicos primero
        $map = [
            'agroindustrial' => 'agroindustrial', // antes de 'industrial'
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

    /**
     * Detecta número de ciclo en la pregunta.
     * Acepta: "ciclo 5", "5to ciclo", "quinto ciclo", "5°", etc.
     */
    private function detectarCiclo(string $query): ?int
    {
        // Número directo: "ciclo 5", "ciclo 10"
        if (preg_match('/ciclo\s+(\d+)/i', $query, $m)) {
            return (int) $m[1];
        }

        // Ordinal numérico: "5to", "5°", "5avo"
        if (preg_match('/(\d+)(?:er|do|ro|to|vo|avo|°)\s+ciclo/i', $query, $m)) {
            return (int) $m[1];
        }

        // Números romanos: "V ciclo", "ciclo V", "III ciclo", "ciclo IX", etc.
        $romanos = [
            'X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7, 'VI' => 6,
            'V' => 5, 'IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1,
        ];
        // "V ciclo" o "ciclo V" (word boundary para no confundir con otras letras)
        if (preg_match('/\b(X|IX|VIII|VII|VI|V|IV|III|II|I)\s+ciclo\b/i', $query, $m)) {
            return $romanos[strtoupper($m[1])] ?? null;
        }
        if (preg_match('/\bciclo\s+(X|IX|VIII|VII|VI|V|IV|III|II|I)\b/i', $query, $m)) {
            return $romanos[strtoupper($m[1])] ?? null;
        }
        // "mi V ciclo" o "el III ciclo" (con posesivos/artículos antes)
        if (preg_match('/\b(?:mi|el|del?|al?)\s+(X|IX|VIII|VII|VI|V|IV|III|II|I)\s+ciclo\b/i', $query, $m)) {
            return $romanos[strtoupper($m[1])] ?? null;
        }

        // Ordinales escritos
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

        // Triggers fuertes: cualquiera de estos solos activa el bloque
        $strong = [
            'plan', 'malla', 'crédito', 'credito',
            'obligatorio', 'electivo', 'pensum', 'prerrequisito',
            'currícula', 'curricula', 'ingeniería industrial',
            'ingeniería informática', 'agroindustrial', 'mecatrónica',
        ];
        foreach ($strong as $t) {
            if (str_contains($q, $t)) return true;
        }

        // "ciclo" solo activa el bloque si la pregunta también menciona un número de ciclo,
        // una carrera o palabras curriculares — evitar activarlo con "¿en qué ciclo estamos?"
        if (str_contains($q, 'ciclo') || str_contains($q, 'semestre')) {
            $curricular = [
                'llevar', 'ver', 'tomar', 'cursar', 'carrera', 'escuela',
                'requisito', 'créditos', 'creditos', 'materias',
            ];
            foreach ($curricular as $c) {
                if (str_contains($q, $c)) return true;
            }
            // Si menciona un número (ej. "ciclo 5", "III ciclo") también activa
            if (preg_match('/\d+/', $q) || preg_match('/\b(I{1,3}|IV|V|VI{0,3}|IX|X)\b/i', $q)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Busca programaciones del periodo por keywords.
     * Si fuzzy=false: solo búsqueda exacta (LIKE '%keyword%').
     * Si fuzzy=true: además prueba prefijo de 8 chars y substring sin primer carácter.
     */
    private function buscarProgramacion(string $periodoId, array $keywords, bool $fuzzy): \Illuminate\Support\Collection
    {
        return ProgramacionAcademica::with('curso')
            ->where('periodo_id', $periodoId)
            ->whereHas('curso', function ($q) use ($keywords, $fuzzy) {
                $q->where(function ($inner) use ($keywords, $fuzzy) {
                    foreach ($keywords as $kw) {
                        $inner->orWhere('nombre', 'like', "%{$kw}%")
                              ->orWhere('codigo', 'like', "%{$kw}%");

                        if ($fuzzy) {
                            // Prefijo 8 chars: "computad" matchea "computadores" y "computadoras"
                            if (mb_strlen($kw) >= 7) {
                                $prefix = mb_substr($kw, 0, 8);
                                $inner->orWhere('nombre', 'like', "%{$prefix}%");
                            }
                            // Sin primer carácter: "quitectura" matchea "arquitectura"
                            if (mb_strlen($kw) >= 6) {
                                $sinPrimero = mb_substr($kw, 1);
                                $inner->orWhere('nombre', 'like', "%{$sinPrimero}%");
                            }
                        }
                    }
                });
            })
            ->limit(25)
            ->get();
    }
}
