<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configura los ajustes de CORS para tu aplicación. Esto determina qué
    | orígenes externos pueden acceder a tu API.
    |
    */

    // Rutas donde se aplica CORS (todas las rutas de API)
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Métodos HTTP permitidos
    'allowed_methods' => ['*'],

    // Orígenes permitidos (dominios del frontend)
    // En desarrollo: localhost:4200 (Angular default)
    // En producción: cambiar por el dominio real
    'allowed_origins' => [
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'http://20.121.70.122',
        'http://20.121.70.122:80',
    ],

    // Patrones de orígenes (regex) - alternativa a allowed_origins
    'allowed_origins_patterns' => [],

    // Headers permitidos en las peticiones
    'allowed_headers' => ['*'],

    // Headers expuestos en las respuestas
    'exposed_headers' => [],

    // Tiempo de cache para preflight requests (en segundos)
    'max_age' => 0,

    // Permitir credenciales (cookies, authorization headers)
    // Necesario para Sanctum
    'supports_credentials' => true,

];
