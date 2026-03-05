<?php

namespace App\Services;

use App\Models\WhatsappSession;

class WhatsappBotService
{
    // Máximo de mensajes en el historial de contexto enviado al LLM
    private const HISTORY_LIMIT = 6;

    // Máximo de mensajes guardados en BD por sesión
    private const MAX_STORED_MESSAGES = 20;

    public function __construct(
        private readonly LlmService       $llm,
        private readonly RagService       $rag,
        private readonly DbContextService $db,
    ) {}

    /**
     * Procesa un mensaje entrante de WhatsApp.
     *
     * @return array{action: string, reply: string|null, estado: string}
     *   action: 'respond' | 'handoff' | 'ignore'
     *   reply: texto para enviar al usuario (null si action=ignore)
     *   estado: estado actual de la sesión
     */
    public function procesarMensaje(string $phone, string $mensaje, ?string $nombre = null): array
    {
        // 1. Buscar o crear sesión
        $session = WhatsappSession::firstOrCreate(
            ['phone' => $phone],
            ['estado' => 'bot_activo', 'nombre' => $nombre]
        );

        // Actualizar nombre si llega ahora y no teníamos uno
        if ($nombre && !$session->nombre) {
            $session->update(['nombre' => $nombre]);
        }

        // 2. Reactivar si estaba cerrada (nuevo mensaje = nueva conversación)
        if ($session->estaCerrada()) {
            $session->update(['estado' => 'bot_activo', 'historial' => []]);
            $session->refresh();
        }

        // 3. Si un agente humano está activo o esperando, no interferir
        if ($session->estaEsperandoHumano() || $session->tieneHumanoActivo()) {
            return [
                'action' => 'ignore',
                'reply'  => null,
                'estado' => $session->estado,
            ];
        }

        // 4. Detectar solicitud de hablar con persona
        if ($this->esHandoffRequest($mensaje)) {
            $reply = "Entendido. En breve un miembro del equipo de Secretaría Académica te atenderá. Por favor espera. 🙏";
            $session->agregarMensaje('user', $mensaje, self::MAX_STORED_MESSAGES);
            $session->agregarMensaje('assistant', $reply, self::MAX_STORED_MESSAGES);
            $session->update(['estado' => 'esperando_humano']);

            return [
                'action' => 'handoff',
                'reply'  => $reply,
                'estado' => 'esperando_humano',
            ];
        }

        // 5. Generar respuesta con RAG + LLM
        try {
            $reply = $this->generarRespuesta($session, $mensaje);
        } catch (\Throwable $e) {
            $reply = "Lo siento, tuve un problema al procesar tu consulta. Por favor intenta de nuevo en unos momentos.";
        }

        // 6. Guardar en historial
        $session->agregarMensaje('user', $mensaje, self::MAX_STORED_MESSAGES);
        $session->agregarMensaje('assistant', $reply, self::MAX_STORED_MESSAGES);

        return [
            'action' => 'respond',
            'reply'  => $reply,
            'estado' => 'bot_activo',
        ];
    }

    /**
     * Marca la sesión como atendida por un agente humano.
     */
    public function tomarControl(string $phone, ?string $agenteNombre = null): WhatsappSession
    {
        $session = WhatsappSession::where('phone', $phone)->firstOrFail();

        $metadata = $session->metadata ?? [];
        if ($agenteNombre) {
            $metadata['agente'] = $agenteNombre;
        }

        $session->update([
            'estado'   => 'humano_activo',
            'metadata' => $metadata,
        ]);

        return $session->fresh();
    }

    /**
     * Devuelve el control al bot y limpia el agente asignado.
     */
    public function devolverAlBot(string $phone): WhatsappSession
    {
        $session = WhatsappSession::where('phone', $phone)->firstOrFail();

        $metadata = $session->metadata ?? [];
        unset($metadata['agente']);

        $session->update([
            'estado'   => 'bot_activo',
            'metadata' => $metadata,
        ]);

        return $session->fresh();
    }

    /**
     * Cierra una conversación.
     */
    public function cerrarConversacion(string $phone): WhatsappSession
    {
        $session = WhatsappSession::where('phone', $phone)->firstOrFail();
        $session->update(['estado' => 'cerrado']);
        return $session->fresh();
    }

    /**
     * Lista las conversaciones que esperan un agente humano.
     */
    public function colaDeEspera(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsappSession::esperandoHumano()
            ->orderBy('ultimo_mensaje_at', 'asc')
            ->get();
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function generarRespuesta(WhatsappSession $session, string $mensaje): string
    {
        // Buscar contexto en Knowledge Base y BD
        $ragResult    = $this->rag->search($mensaje);
        $ragBlock     = $this->rag->buildContextBlock($ragResult);
        $dbBlock      = $this->db->buildContext($mensaje);
        $contextBlock = implode("\n\n---\n\n", array_filter([$dbBlock, $ragBlock]));

        // Construir mensajes para el LLM
        $messages   = [];
        $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt($contextBlock)];

        // Historial reciente para contexto de la conversación
        foreach ($session->historialReciente(self::HISTORY_LIMIT) as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $mensaje];

        $result = $this->llm->chat($messages);
        return $result['content'];
    }

    /**
     * Detecta si el usuario quiere ser transferido a un agente humano.
     */
    private function esHandoffRequest(string $mensaje): bool
    {
        $q = mb_strtolower($mensaje);

        $triggers = [
            'hablar con secretaria',
            'hablar con secretaría',
            'hablar con una persona',
            'hablar con alguien',
            'quiero hablar con',
            'necesito hablar con',
            'comunicarme con secretaria',
            'comunicarme con secretaría',
            'agente humano',
            'persona real',
            'atiéndame',
            'atiendan',
            'quiero que me atiendan',
        ];

        foreach ($triggers as $trigger) {
            if (str_contains($q, $trigger)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sistema prompt optimizado para WhatsApp:
     * - Texto conciso (móvil)
     * - Sin markdown pesado (solo *negrita* compatible con WhatsApp)
     * - Sin tablas
     * - Menciona la opción de hablar con una persona
     */
    private function buildSystemPrompt(string $contextBlock): string
    {
        $base = <<<PROMPT
Eres el asistente virtual de la Secretaría Académica de la Facultad de Ingeniería Industrial (FII) de la Universidad Nacional de Piura (UNP), atendiendo por WhatsApp.

PROPÓSITO: Responder consultas sobre procesos académicos, trámites, requisitos y horarios de la FII-UNP.

RESTRICCIONES IMPORTANTES:
• No tienes acceso a información personal de estudiantes (notas, historial, matrículas específicas, solicitudes). Para eso el estudiante debe ingresar al sistema en la secretaría o acercarse directamente.
• No puedes gestionar trámites, solo orientar sobre cómo hacerlos.

FILTRO DE TEMA:
• Si la pregunta NO es académica o de la FII-UNP, responde solo: "Solo puedo ayudarte con temas académicos de la FII-UNP. Para otras consultas te recomiendo un asistente de propósito general."
• Si es saludo, responde brevemente y amistosamente.

REGLAS DE RESPUESTA:
• Responde siempre en español.
• Usa SOLO la información del CONTEXTO DISPONIBLE. No inventes requisitos, plazos ni datos.
• Si no tienes la información, di: "No tengo información específica sobre eso. Te recomiendo consultar directamente en Secretaría Académica (primer piso de la FII) o escribir al correo oficial."
• Sé conciso. Máximo 3-4 párrafos cortos. Las personas leen en el celular.
• Usa *texto* para resaltar algo importante (compatible con WhatsApp).
• NO uses #, ##, tablas markdown ni bullets complejos. Usa guiones simples o numeración.
• Al final de respuestas sobre procesos o trámites, recuerda: "Si necesitas que te atienda una persona, escribe: *hablar con secretaría*"

DATOS DE CURSOS:
• Si hay información de secciones, presenta solo: Grupo, Sección, Estado y Aula. Sin claves internas.
• Si hay varias secciones, lista cada una en una línea separada.

CITAS OFICIALES:
• Si usas un reglamento, cita textualmente entre «comillas angulares» y menciona el documento.
PROMPT;

        if ($contextBlock) {
            $base .= "\n\n" . $contextBlock;
        } else {
            $base .= "\n\n[Sin contexto específico disponible para esta consulta]";
        }

        return $base;
    }
}
