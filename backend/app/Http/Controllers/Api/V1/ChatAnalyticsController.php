<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatMessageTopic;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatAnalyticsController extends Controller
{
    // GET /chatbot/analytics/top-topics
    // Temas más consultados en el chatbot (agrupados por artículo KB)
    public function topTopics(Request $request): JsonResponse
    {
        $days  = (int) ($request->days ?? 30);
        $limit = (int) ($request->limit ?? 10);

        $topics = ChatMessageTopic::select('knowledge_base_id', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('knowledge_base_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('article:id,titulo,tipo,categoria')
            ->get()
            ->map(fn($t) => [
                'knowledge_base_id' => $t->knowledge_base_id,
                'titulo'            => $t->article?->titulo ?? 'Artículo eliminado',
                'tipo'              => $t->article?->tipo,
                'categoria'         => $t->article?->categoria,
                'consultas'         => $t->total,
            ]);

        return $this->success($topics, "Top {$limit} temas más consultados (últimos {$days} días)");
    }

    // GET /chatbot/analytics/knowledge-gaps
    // Respuestas del asistente donde no hubo contexto KB = brechas de conocimiento
    public function knowledgeGaps(Request $request): JsonResponse
    {
        $days    = (int) ($request->days ?? 30);
        $perPage = (int) ($request->per_page ?? 20);

        // Buscamos mensajes del asistente sin contexto, luego recuperamos la pregunta del usuario
        $paginator = ChatMessage::select('id', 'conversation_id', 'created_at')
            ->where('role', 'assistant')
            ->where('tuvo_contexto', false)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        // Para cada respuesta sin contexto, buscamos la pregunta anterior del usuario
        $items = collect($paginator->items())->map(function ($msg) {
            $pregunta = ChatMessage::where('conversation_id', $msg->conversation_id)
                ->where('role', 'user')
                ->where('created_at', '<=', $msg->created_at)
                ->orderByDesc('created_at')
                ->value('contenido');

            return [
                'assistant_message_id' => $msg->id,
                'pregunta'             => $pregunta ?? '(no encontrada)',
                'fecha'                => $msg->created_at->toISOString(),
            ];
        });

        return $this->paginated(
            $items->toArray(),
            $paginator,
            'Preguntas sin cobertura en KB'
        );
    }

    // GET /chatbot/analytics/summary
    // Resumen general de uso del chatbot
    public function summary(Request $request): JsonResponse
    {
        $days = (int) ($request->days ?? 30);
        $from = now()->subDays($days);

        $totalMessages   = ChatMessage::where('role', 'user')->where('created_at', '>=', $from)->count();
        $withContext     = ChatMessage::where('role', 'assistant')->where('tuvo_contexto', true)->where('created_at', '>=', $from)->count();
        $withoutContext  = ChatMessage::where('role', 'assistant')->where('tuvo_contexto', false)->where('created_at', '>=', $from)->count();
        $totalTokens     = ChatMessage::where('role', 'assistant')->where('created_at', '>=', $from)->sum('tokens_used');

        $responseRate = ($withContext + $withoutContext) > 0
            ? round($withContext / ($withContext + $withoutContext) * 100, 1)
            : 0;

        return $this->success([
            'periodo_dias'       => $days,
            'total_preguntas'    => $totalMessages,
            'respondidas_con_kb' => $withContext,
            'sin_contexto_kb'    => $withoutContext,
            'tasa_cobertura_pct' => $responseRate,
            'tokens_usados'      => $totalTokens,
        ], 'Resumen de uso del chatbot');
    }
}
