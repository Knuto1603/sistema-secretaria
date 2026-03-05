<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Token secreto que n8n debe incluir en el header X-Webhook-Secret
    | de cada petición al endpoint de WhatsApp.
    | Configura en .env: WHATSAPP_WEBHOOK_SECRET=tu-clave-secreta
    */
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Evolution API
    |--------------------------------------------------------------------------
    | Datos de la instancia de Evolution API para enviar mensajes de respuesta
    | directamente desde Laravel (si decides no hacerlo desde n8n).
    */
    'evolution_url'      => env('EVOLUTION_API_URL'),
    'evolution_key'      => env('EVOLUTION_API_KEY'),
    'evolution_instance' => env('EVOLUTION_INSTANCE'),
];
