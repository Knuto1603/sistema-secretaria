<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    private string $provider;
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int    $maxTokens;
    private float  $temperature;

    public function __construct()
    {
        $this->provider    = config('chatbot.provider', 'groq');
        $config            = config("chatbot.providers.{$this->provider}");
        $this->apiKey      = $config['api_key'] ?? '';
        $this->baseUrl     = $config['base_url'];
        $this->model       = $config['model'];
        $this->maxTokens   = config('chatbot.max_tokens', 450);
        $this->temperature = config('chatbot.temperature', 0.2);
    }

    /**
     * Envía mensajes al LLM y retorna la respuesta + tokens usados.
     *
     * @param  array  $messages  [['role' => 'user|assistant|system', 'content' => '...']]
     * @return array{content: string, tokens: int}
     */
    public function chat(array $messages): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'messages'    => $messages,
                'max_tokens'  => $this->maxTokens,
                'temperature' => $this->temperature,
            ]);

        if (!$response->successful()) {
            Log::error('LlmService error', [
                'provider' => $this->provider,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException('Error al contactar el servicio de IA. Intenta más tarde.');
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens'  => $data['usage']['total_tokens'] ?? 0,
        ];
    }
}
