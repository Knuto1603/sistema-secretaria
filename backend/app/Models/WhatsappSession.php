<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'phone',
        'nombre',
        'estado',
        'historial',
        'metadata',
        'ultimo_mensaje_at',
    ];

    protected $casts = [
        'historial'         => 'array',
        'metadata'          => 'array',
        'ultimo_mensaje_at' => 'datetime',
    ];

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeEsperandoHumano($query)
    {
        return $query->where('estado', 'esperando_humano');
    }

    public function scopeConHumano($query)
    {
        return $query->where('estado', 'humano_activo');
    }

    public function scopeActivas($query)
    {
        return $query->whereIn('estado', ['bot_activo', 'esperando_humano', 'humano_activo']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function esBotActivo(): bool
    {
        return $this->estado === 'bot_activo';
    }

    public function estaEsperandoHumano(): bool
    {
        return $this->estado === 'esperando_humano';
    }

    public function tieneHumanoActivo(): bool
    {
        return $this->estado === 'humano_activo';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrado';
    }

    /**
     * Agrega un mensaje al historial y lo mantiene en el límite máximo.
     */
    public function agregarMensaje(string $role, string $content, int $maxMessages = 20): void
    {
        $historial = $this->historial ?? [];

        $historial[] = [
            'role'    => $role,
            'content' => $content,
        ];

        // Mantener solo los últimos N mensajes para no crecer indefinidamente
        if (count($historial) > $maxMessages) {
            $historial = array_slice($historial, -$maxMessages);
        }

        $this->historial = $historial;
        $this->ultimo_mensaje_at = now();
        $this->save();
    }

    /**
     * Retorna los últimos N mensajes del historial para el LLM.
     */
    public function historialReciente(int $limit = 6): array
    {
        $historial = $this->historial ?? [];
        return array_slice($historial, -$limit);
    }
}
