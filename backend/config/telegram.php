<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot Token
    |--------------------------------------------------------------------------
    | Token entregado por @BotFather al crear el bot.
    | Configura en .env: TELEGRAM_BOT_TOKEN=123456789:ABC-...
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Token secreto que Telegram incluye en el header
    | X-Telegram-Bot-Api-Secret-Token de cada petición al webhook.
    | Se registra junto con la URL del webhook via setWebhook().
    | Configura en .env: TELEGRAM_WEBHOOK_SECRET=tu-clave-secreta
    */
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Bot Username
    |--------------------------------------------------------------------------
    | Username del bot (sin @), usado para construir el deep link
    | https://t.me/{bot_username}?start={codigo} que se muestra en el frontend.
    */
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),
];
