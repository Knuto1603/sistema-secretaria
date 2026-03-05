<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use App\Services\WhatsappBotService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WhatsappBotService $bot,
    ) {}

    // =========================================================================
    // WEBHOOK PÚBLICO (llamado por n8n)
    // =========================================================================

    /**
     * POST /api/whatsapp/message
     *
     * Recibe un mensaje desde n8n (que a su vez lo recibe de Evolution API).
     * Protegido por X-Webhook-Secret en el header.
     *
     * Body esperado:
     * {
     *   "phone":   "51912345678",    // Número en formato E.164
     *   "message": "Hola, necesito ayuda",
     *   "name":    "Juan Pérez"      // Opcional
     * }
     *
     * Respuesta:
     * {
     *   "action":  "respond|handoff|ignore",
     *   "reply":   "Texto del bot",  // null si action=ignore
     *   "estado":  "bot_activo|esperando_humano|humano_activo|cerrado",
     *   "phone":   "51912345678"
     * }
     */
    public function receiveMessage(Request $request): JsonResponse
    {
        // Validar secret key del webhook
        if (!$this->validarWebhookSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'phone'   => 'required|string|max:20',
            'message' => 'required|string|max:4096',
            'name'    => 'nullable|string|max:255',
        ]);

        Log::info('WhatsApp mensaje recibido', [
            'phone' => $data['phone'],
            'msg'   => mb_substr($data['message'], 0, 100),
        ]);

        try {
            $result = $this->bot->procesarMensaje(
                phone:   $data['phone'],
                mensaje: $data['message'],
                nombre:  $data['name'] ?? null,
            );

            return response()->json([
                'action' => $result['action'],
                'reply'  => $result['reply'],
                'estado' => $result['estado'],
                'phone'  => $data['phone'],
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp error procesando mensaje', [
                'phone' => $data['phone'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'action' => 'respond',
                'reply'  => 'Lo siento, tuve un error al procesar tu mensaje. Por favor intenta nuevamente.',
                'estado' => 'bot_activo',
                'phone'  => $data['phone'],
            ]);
        }
    }

    /**
     * POST /api/whatsapp/agent/take/{phone}
     *
     * N8n notifica que un agente humano tomó el control de la conversación.
     * Protegido por X-Webhook-Secret.
     */
    public function agentTakeControl(Request $request, string $phone): JsonResponse
    {
        if (!$this->validarWebhookSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'agente' => 'nullable|string|max:255',
        ]);

        try {
            $session = $this->bot->tomarControl($phone, $data['agente'] ?? null);
            return response()->json([
                'success' => true,
                'estado'  => $session->estado,
                'phone'   => $session->phone,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Sesión no encontrada'], 404);
        }
    }

    /**
     * POST /api/whatsapp/agent/release/{phone}
     *
     * El agente devuelve el control al bot.
     * Protegido por X-Webhook-Secret.
     */
    public function agentRelease(Request $request, string $phone): JsonResponse
    {
        if (!$this->validarWebhookSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $session = $this->bot->devolverAlBot($phone);
            return response()->json([
                'success' => true,
                'estado'  => $session->estado,
                'phone'   => $session->phone,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Sesión no encontrada'], 404);
        }
    }

    // =========================================================================
    // PANEL DE SECRETARÍA (protegido por Sanctum)
    // =========================================================================

    /**
     * GET /api/whatsapp/queue
     *
     * Lista las conversaciones que esperan atención humana.
     * Solo para admin/secretaria/developer.
     */
    public function queue(): JsonResponse
    {
        $sesiones = $this->bot->colaDeEspera();

        $data = $sesiones->map(fn($s) => [
            'phone'              => $s->phone,
            'nombre'             => $s->nombre,
            'estado'             => $s->estado,
            'ultimo_mensaje'     => $s->historialReciente(1)[0]['content'] ?? null,
            'ultimo_mensaje_at'  => $s->ultimo_mensaje_at?->toISOString(),
            'agente'             => $s->metadata['agente'] ?? null,
        ]);

        return $this->successResponse($data, 'Cola de espera');
    }

    /**
     * GET /api/whatsapp/sessions
     *
     * Lista todas las sesiones activas con paginación.
     * Solo para admin/secretaria/developer.
     */
    public function sessions(Request $request): JsonResponse
    {
        $query = WhatsappSession::activas()
            ->orderBy('ultimo_mensaje_at', 'desc');

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $sesiones = $query->paginate(20);

        $items = collect($sesiones->items())->map(fn($s) => [
            'phone'             => $s->phone,
            'nombre'            => $s->nombre,
            'estado'            => $s->estado,
            'total_mensajes'    => count($s->historial ?? []),
            'ultimo_mensaje_at' => $s->ultimo_mensaje_at?->toISOString(),
            'agente'            => $s->metadata['agente'] ?? null,
        ]);

        return $this->successResponse([
            'items'      => $items,
            'pagination' => [
                'total'        => $sesiones->total(),
                'per_page'     => $sesiones->perPage(),
                'current_page' => $sesiones->currentPage(),
                'last_page'    => $sesiones->lastPage(),
            ],
        ], 'Sesiones WhatsApp');
    }

    /**
     * GET /api/whatsapp/sessions/{phone}
     *
     * Detalle de una conversación (incluyendo historial completo).
     * Solo para admin/secretaria/developer.
     */
    public function session(string $phone): JsonResponse
    {
        $session = WhatsappSession::where('phone', $phone)->firstOrFail();

        return $this->successResponse([
            'phone'             => $session->phone,
            'nombre'            => $session->nombre,
            'estado'            => $session->estado,
            'historial'         => $session->historial ?? [],
            'metadata'          => $session->metadata,
            'ultimo_mensaje_at' => $session->ultimo_mensaje_at?->toISOString(),
            'created_at'        => $session->created_at->toISOString(),
        ], 'Detalle de sesión');
    }

    /**
     * PATCH /api/whatsapp/sessions/{phone}/close
     *
     * Cierra una conversación desde el panel.
     */
    public function closeSession(string $phone): JsonResponse
    {
        try {
            $session = $this->bot->cerrarConversacion($phone);
            return $this->successResponse(['estado' => $session->estado], 'Conversación cerrada');
        } catch (\Throwable $e) {
            return $this->errorResponse('Sesión no encontrada', 404);
        }
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function validarWebhookSecret(Request $request): bool
    {
        $secret = config('whatsapp.webhook_secret');

        if (!$secret) {
            // Si no hay secret configurado, solo en local se permite
            return app()->environment('local');
        }

        return $request->header('X-Webhook-Secret') === $secret;
    }
}
