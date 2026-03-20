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
     * Clasifica la intención del mensaje para determinar qué contextos cargar.
     * Llamada rápida (~120 tokens) antes de construir el contexto completo.
     *
     * @return array{historial:bool, inscripciones:bool, comparacion:bool,
     *               docente:bool, cursos:bool, plan_estudios:bool, alumno:bool}
     */
    public function classify(string $query, string $userType = 'estudiante'): array
    {
        $prompt = <<<PROMPT
Eres un clasificador de intención para un asistente académico universitario.
Analiza el mensaje y determina qué contextos de base de datos necesita cargar.
Responde ÚNICAMENTE con JSON válido, sin explicaciones ni markdown.

Tipo de usuario: {$userType}
Mensaje: "{$query}"

JSON requerido (true = necesario, false = no necesario):
{
  "historial": bool,      // notas, cursos aprobados, créditos acumulados, historial académico, promedio
  "inscripciones": bool,  // cursos en los que está matriculado/inscrito en el ciclo actual
  "comparacion": bool,    // comparar historial con plan de estudios, qué cursos faltan para egresar, avance académico
  "docente": bool,        // información de un profesor específico: sus cursos, horarios
  "cursos": bool,         // programación académica, horarios, aulas, disponibilidad de cupos, cursos ofertados/aperturados en un ciclo, qué cursos se dictan, secciones disponibles
  "plan_estudios": bool,  // malla curricular, cursos obligatorios y electivos por ciclo, plan de carrera
  "alumno": bool          // (solo admin) consultar perfil de un estudiante por nombre o código
}

Ejemplos de clasificación:
- "¿qué cursos se aperturan en el ciclo 2026-1?" → cursos: true
- "¿qué materias hay disponibles este semestre?" → cursos: true
- "¿cuántos alumnos tiene cálculo I?" → cursos: true
- "¿en qué aula es álgebra?" → cursos: true
- "¿qué cursos me faltan para egresar?" → comparacion: true
- "¿cuántos créditos llevo?" → historial: true
- "¿en qué cursos estoy matriculado?" → inscripciones: true
PROMPT;

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 120,
                    'temperature' => 0.0,
                ]);

            if (!$response->successful()) {
                return $this->defaultIntent();
            }

            $content = $response->json()['choices'][0]['message']['content'] ?? '{}';

            // Extraer JSON aunque venga con texto extra
            if (preg_match('/\{.*?\}/s', $content, $m)) {
                $intent = json_decode($m[0], true) ?? [];
            } else {
                $intent = json_decode($content, true) ?? [];
            }

            return array_merge($this->defaultIntent(), array_map('boolval', $intent));
        } catch (\Throwable) {
            return $this->defaultIntent();
        }
    }

    private function defaultIntent(): array
    {
        return [
            'historial'     => false,
            'inscripciones' => false,
            'comparacion'   => false,
            'docente'       => false,
            'cursos'        => false,
            'plan_estudios' => false,
            'alumno'        => false,
        ];
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
