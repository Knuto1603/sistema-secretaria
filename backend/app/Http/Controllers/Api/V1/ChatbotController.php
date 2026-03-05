<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot) {}

    // GET /chatbot/conversations
    public function conversations(): JsonResponse
    {
        $user = Auth::user();
        $convs = ChatConversation::delUsuario($user->id)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'titulo'         => $c->titulo ?? 'Nueva conversación',
                'messages_count' => $c->messages_count,
                'expires_at'     => $c->expires_at->toISOString(),
                'created_at'     => $c->created_at->toISOString(),
                'updated_at'     => $c->updated_at->toISOString(),
            ]);

        return $this->success($convs, 'Conversaciones');
    }

    // POST /chatbot/conversations
    public function newConversation(): JsonResponse
    {
        $conv = $this->chatbot->nuevaConversacion(Auth::user());

        return $this->created([
            'id'         => $conv->id,
            'titulo'     => 'Nueva conversación',
            'expires_at' => $conv->expires_at->toISOString(),
            'created_at' => $conv->created_at->toISOString(),
        ], 'Conversación creada');
    }

    // GET /chatbot/conversations/{id}
    public function conversation(string $id): JsonResponse
    {
        $user = Auth::user();
        $conv = ChatConversation::delUsuario($user->id)->findOrFail($id);

        $messages = $conv->messages()->get()->map(fn($m) => [
            'id'                 => $m->id,
            'role'               => $m->role,
            'contenido'          => $m->contenido,
            'context_articles'   => $m->context_articles ?? [],
            'context_documents'  => $m->context_documents ?? [],
            'templates_sugeridos' => $m->templates_sugeridos ?? [],
            'created_at'         => $m->created_at->toISOString(),
        ]);

        return $this->success([
            'conversation' => [
                'id'         => $conv->id,
                'titulo'     => $conv->titulo ?? 'Nueva conversación',
                'expires_at' => $conv->expires_at->toISOString(),
            ],
            'messages' => $messages,
        ], 'Conversación');
    }

    // POST /chatbot/conversations/{id}/messages
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'pregunta' => 'required|string|min:3|max:1000',
        ]);

        try {
            $result = $this->chatbot->responder($id, $request->input('pregunta'), Auth::user());
            return $this->success($result, 'Respuesta generada');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 503);
        }
    }

    // DELETE /chatbot/conversations/{id}
    public function deleteConversation(string $id): JsonResponse
    {
        $user = Auth::user();
        $conv = ChatConversation::delUsuario($user->id)->findOrFail($id);
        $conv->delete();

        return $this->success(null, 'Conversación eliminada');
    }
}
