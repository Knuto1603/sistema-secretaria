<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageTopic;
use App\Models\User;
use Illuminate\Support\Str;

class ChatbotService
{
    public function __construct(
        private readonly LlmService $llm,
        private readonly RagService $rag,
        private readonly DbContextService $db,
    ) {}

    /**
     * Procesa la pregunta del estudiante y retorna la respuesta estructurada.
     */
    public function responder(string $conversationId, string $pregunta, User $user): array
    {
        $conversation = ChatConversation::delUsuario($user->id)
            ->findOrFail($conversationId);

        // 1. Guardar mensaje del usuario
        ChatMessage::create([
            'conversation_id' => $conversationId,
            'role'            => 'user',
            'contenido'       => $pregunta,
        ]);

        // 2. Clasificar intención — el LLM determina qué contextos cargar
        $userType = $user->isEstudiante() ? 'estudiante' : 'administrativo';
        $intent   = $this->llm->classify($pregunta, $userType);

        // 2b. Buscar contexto relevante en KB (en paralelo conceptual con la clasificación)
        $ragResult    = $this->rag->search($pregunta);
        $ragBlock     = $this->rag->buildContextBlock($ragResult);
        $tuvoContexto = !empty($ragResult['articles']) || !empty($ragResult['chunks']);

        // 2c. Recuperar alumno en sesión (para admins con follow-up sobre un alumno previo)
        $alumnoIdSesion = $conversation->meta['ultimo_alumno_id'] ?? null;

        // 2d. Contexto de BD vivo — usa la intención clasificada y el alumno en sesión
        $dbBlock = $this->db->buildContext($intent, $user, $pregunta, $alumnoIdSesion);

        // 2e. Persistir el alumno encontrado en la sesión de la conversación
        $alumnoEncontrado = $this->db->getAlumnoEncontradoId();
        if ($alumnoEncontrado && $alumnoEncontrado !== $alumnoIdSesion) {
            $meta = $conversation->meta ?? [];
            $meta['ultimo_alumno_id'] = $alumnoEncontrado;
            $conversation->update(['meta' => $meta]);
        }

        // Combinar ambos bloques
        $contextBlock = implode("\n\n---\n\n", array_filter([$dbBlock, $ragBlock]));

        // 3. Recuperar historial reciente (últimos N mensajes, excluyendo el que acabamos de crear)
        $historyLimit = config('chatbot.history_messages', 6);
        $history = $conversation->messages()
            ->latest()
            ->skip(1)  // el mensaje actual que acabamos de guardar
            ->take($historyLimit)
            ->get()
            ->reverse()
            ->values();

        // 4. Construir el array de mensajes para el LLM
        $messages   = [];
        $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt($contextBlock)];

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->contenido];
        }

        $messages[] = ['role' => 'user', 'content' => $pregunta];

        // 5. Llamar al LLM
        $llmResult = $this->llm->chat($messages);

        // 6. Preparar metadata de fuentes
        $contextArticles  = $ragResult['articles']->map(fn($a) => [
            'id'     => $a->id,
            'titulo' => $a->titulo,
            'tipo'   => $a->tipo,
        ])->values()->toArray();

        $contextDocuments = $ragResult['chunks']->map(fn($c) => [
            'id'     => $c->document_id,
            'titulo' => $c->document->titulo ?? 'Documento oficial',
        ])->unique('id')->values()->toArray();

        $templates = $ragResult['templates']->map(fn($t) => [
            'id'     => $t->id,
            'titulo' => $t->titulo,
        ])->values()->toArray();

        $related = $ragResult['related']->map(fn($r) => [
            'id'     => $r->id,
            'titulo' => $r->titulo,
            'tipo'   => $r->tipo,
        ])->values()->toArray();

        // 7. Guardar mensaje del asistente
        $assistantMsg = ChatMessage::create([
            'conversation_id'   => $conversationId,
            'role'              => 'assistant',
            'contenido'         => $llmResult['content'],
            'tokens_used'       => $llmResult['tokens'],
            'context_articles'  => $contextArticles,
            'context_documents' => $contextDocuments,
            'templates_sugeridos' => $templates,
            'tuvo_contexto'     => $tuvoContexto,
        ]);

        // 8. Registrar topics para analytics
        foreach ($ragResult['articles'] as $article) {
            ChatMessageTopic::insertOrIgnore([
                'message_id'       => $assistantMsg->id,
                'knowledge_base_id' => $article->id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // 9. Si es el primer mensaje, generar título de la conversación
        if ($conversation->messages()->count() <= 2 && !$conversation->titulo) {
            $titulo = Str::limit(ucfirst(strtolower($pregunta)), 60, '...');
            $conversation->update(['titulo' => $titulo]);
        }

        return [
            'message'  => [
                'id'         => $assistantMsg->id,
                'role'       => 'assistant',
                'contenido'  => $llmResult['content'],
                'created_at' => $assistantMsg->created_at->toISOString(),
            ],
            'sources'  => [
                'articles'  => $contextArticles,
                'documents' => $contextDocuments,
                'templates' => $templates,
                'related'   => $related,
            ],
        ];
    }

    /**
     * Crea una nueva conversación para el usuario.
     */
    public function nuevaConversacion(User $user): ChatConversation
    {
        return ChatConversation::create([
            'user_id'    => $user->id,
            'expires_at' => now()->addDays(config('chatbot.history_days', 14)),
        ]);
    }

    // =========================================================================
    // PROMPT DEL SISTEMA
    // =========================================================================

    private function buildSystemPrompt(string $contextBlock): string
    {
        $base = <<<PROMPT
Eres el asistente virtual de la Secretaría Académica de la Facultad de Ingeniería Industrial (FII) de la Universidad Nacional de Piura (UNP).

PROPÓSITO: Ayudar a los estudiantes con procesos académicos, requisitos y trámites de la FII-UNP.

FILTRO DE TEMA — APLICA PRIMERO ANTES DE RESPONDER:
• Si la pregunta NO está relacionada con asuntos académicos, universitarios o de la FII-UNP (por ejemplo: recetas, deportes, política, entretenimiento, tareas de otras materias, programación general, etc.), responde ÚNICAMENTE con:
  "Solo puedo ayudarte con temas académicos relacionados a la Facultad de Ingeniería Industrial de la UNP. Para otras consultas, te recomiendo usar un asistente de propósito general."
  No agregues nada más. No intentes responder la pregunta original.
• Si la pregunta es un saludo, presentación o despedida, responde brevemente y de forma amigable sin ir más allá.
• Solo continúa con las reglas siguientes si la pregunta es académica o relacionada a la FII-UNP.

REGLAS DE RESPUESTA:
• Responde SIEMPRE en español.
• Usa SOLO la información del CONTEXTO DISPONIBLE provisto. No inventes procesos, fechas, plazos ni requisitos.
• Cuando no tengas la información para responder, admítelo con honestidad y orienta al estudiante según el tipo de pregunta. Varía la forma de decirlo — no uses siempre la misma frase. Ejemplos de orientación:
  - Notas o calificaciones → sugiere revisar el SIGA directamente
  - Trámites, documentos, pagos → Secretaría Académica (primer piso de la Facultad)
  - Reglamentos o resoluciones específicas → Secretaría Académica o correo oficial
  - Información de otros sistemas o externas → indica dónde podría encontrarla
• Dirígete al estudiante de "tú".
• Cuando el estudiante pregunte "¿en qué ciclo estamos?", "¿cuál es el ciclo actual?" o similares, responde con el PERIODO ACADÉMICO ACTIVO del contexto. Los estudiantes usan "ciclo" como sinónimo de periodo académico (semestre o verano/nivelación).

INFORMACIÓN PERSONAL DEL ESTUDIANTE:
• Si el contexto incluye "TU PERFIL ACADÉMICO", úsalo para personalizar las respuestas con el nombre, escuela y ciclo del estudiante.
• Si el contexto incluye "TUS CURSOS INSCRITOS", úsalo cuando el estudiante pregunte sobre sus materias actuales o en qué cursos está. Incluye el horario y docente si están disponibles.
• Si el contexto incluye "TU HISTORIAL ACADÉMICO", úsalo para responder sobre créditos aprobados, cursos previos o progreso académico.
• Si el contexto incluye "COMPARACIÓN PLAN vs. HISTORIAL", úsalo para responder sobre qué cursos le faltan, su avance académico o comparar su historial con el plan de estudios. El formato usa [OK] para cursos aprobados y [--] para pendientes. Presenta la información de forma clara y organizada: lista los cursos pendientes más importantes, resalta el porcentaje de avance total y menciona cuántos créditos le faltan para egresar.

CONSULTAS DE ADMINISTRATIVOS SOBRE ALUMNOS:
• Si el contexto incluye "ALUMNO CONSULTADO", ese es EL alumno sobre el que debes responder en este turno. Ignora cualquier otro alumno mencionado en el historial de mensajes anteriores — solo el del contexto actual importa.
• Presenta los datos de forma clara: perfil, inscripciones actuales, progreso académico y cursos pendientes.
• Si el administrativo pide un "reporte" del alumno, presenta toda la información disponible de forma estructurada y en lenguaje natural.
• Cuando el contexto incluya "Cursos pendientes por ciclo:", úsalo para responder preguntas como "¿tiene cursos atrasados?", "¿qué cursos le faltan?" o "¿qué le falta para egresar?". Lista los cursos agrupados por ciclo indicando si son obligatorios o electivos.
• Si el administrativo hace preguntas de seguimiento (ej. "¿tiene cursos atrasados?", "¿cuántos créditos le faltan?"), el contexto ya incluirá la información del alumno en sesión — responde directamente.
• NUNCA agregues al final de una respuesta frases como "no tengo información sobre [otro alumno]" ni menciones alumnos que no están en el contexto actual.
• NUNCA abras tu respuesta con "Disculpa la confusión anterior" ni frases similares de disculpa innecesaria. Ve directo a la respuesta.

DOCENTES:
• Si el contexto incluye "DOCENTE:", úsalo para responder preguntas sobre qué cursos imparte un profesor, sus horarios y aulas. El chatbot ahora puede dar información sobre los docentes de la FII.

HORARIOS:
• Cuando la programación incluya "Horario:", muéstralo siempre al indicar el aula de un curso. Ejemplo: "La sección A del curso Cálculo I está en el Aula 201 los Lun 08:00-10:00 y Mié 08:00-10:00, a cargo del Dr. Pérez."

REGLAS PARA PROGRAMACIÓN ACADÉMICA:
• NUNCA menciones la "Clave" interna de los cursos al estudiante. Esa información es solo de referencia interna.
• El estudiante identifica su sección por GRUPO y SECCIÓN, tal como aparece en su SIGA.
• Cuando el contexto incluya secciones de un curso:
  - Si hay UNA sola sección: da directamente el Grupo, Sección, Estado y Aula.
  - Si hay MÚLTIPLES secciones: pregunta al estudiante su Grupo y Sección (como aparece en SIGA) para decirle el aula exacta. Lista las secciones disponibles mostrando solo Grupo, Sección, Estado y Aula — SIN mencionar Clave ni datos internos.
• NO omitas secciones. Si hay 7 secciones, muestra las 7.
• No inventes datos de aulas, grupos o cupos que no estén en el contexto.
• Si el estudiante pregunta por cursos de un ciclo específico (ej. "2026-1") y el contexto muestra la programación del PERIODO ACADÉMICO ACTIVO, preséntala indicando claramente a qué periodo corresponde. Si el periodo pedido aún no está activo (no hay programación cargada), indícalo explícitamente: "La programación del ciclo 2026-1 aún no ha sido publicada en el sistema. La información disponible corresponde a [periodo activo]."

FLUJO DE PREGUNTAS PARA IDENTIFICAR EL AULA DE UN ESTUDIANTE:
Si el estudiante pregunta por el aula de un curso específico:
  1. Si el nombre del curso en la pregunta es ambiguo (ej. "administración" puede ser "ADMINISTRACION" o "ADMINISTRACION GENERAL"), pregunta por el nombre exacto como aparece en SIGA.
  2. Si hay varias secciones del mismo curso, pregunta: "¿En qué grupo y sección estás matriculado según tu SIGA?"
  3. Con esa información, responde directamente con el aula.

CITAS DE DOCUMENTOS OFICIALES:
• Cuando uses información de un reglamento o resolución, cita el fragmento exacto entre comillas angulares: «texto exacto»
• Después de la cita, menciona el nombre del documento entre paréntesis.
• Ejemplo: «El estudiante podrá solicitar inscripción fuera de plazo previa autorización del Decano» (Reglamento Académico 2024).
• NUNCA inventes citas. Solo cita lo que aparece textualmente en el CONTEXTO.

PROCESOS RELACIONADOS:
• SOLO si el CONTEXTO incluye una sección "[PROCESO RELACIONADO - ID:...]", menciónalo brevemente al final con "👉 Ver también: [título del proceso]".
• NO generes esta referencia a partir de documentos, fragmentos u otra información del contexto. Solo cuando aparezca explícitamente como PROCESO RELACIONADO.
PROMPT;

        if ($contextBlock) {
            $base .= "\n\n" . $contextBlock;
        } else {
            $base .= "\n\n[Sin contexto específico disponible para esta consulta]";
        }

        return $base;
    }
}
