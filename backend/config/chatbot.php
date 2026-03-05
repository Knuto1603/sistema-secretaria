<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor LLM activo
    |--------------------------------------------------------------------------
    | Valores: 'groq', 'gemini', 'openai'
    | Para cambiar de proveedor solo cambia LLM_PROVIDER en .env
    */
    'provider' => env('LLM_PROVIDER', 'groq'),

    'providers' => [
        'groq' => [
            'api_key'  => env('GROQ_API_KEY'),
            'base_url' => 'https://api.groq.com/openai/v1',
            'model'    => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],
        'gemini' => [
            'api_key'  => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model'    => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
        'openai' => [
            'api_key'  => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
            'model'    => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
    ],

    // Parámetros de generación
    'max_tokens'  => (int) env('LLM_MAX_TOKENS', 450),
    'temperature' => (float) env('LLM_TEMPERATURE', 0.2),

    // RAG: cuántos fragmentos de contexto incluir en cada prompt
    'context_chunks' => 5,

    // Historial: cuántos mensajes anteriores incluir (3-4 turnos)
    'history_messages' => 6,

    // Días que se conserva una conversación
    'history_days' => (int) env('CHATBOT_HISTORY_DAYS', 14),

    // Chunking de documentos
    'chunk_words'   => 500,
    'chunk_overlap' => 50,
];
