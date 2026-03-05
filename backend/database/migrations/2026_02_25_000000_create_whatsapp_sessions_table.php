<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();

            // Número de teléfono en formato E.164 (ej: "51912345678")
            $table->string('phone', 20)->unique()->index();

            // Nombre del contacto (viene de Evolution API)
            $table->string('nombre', 255)->nullable();

            // Estado de la conversación
            $table->enum('estado', [
                'bot_activo',        // El bot responde automáticamente
                'esperando_humano',  // Usuario solicitó agente humano, nadie tomó aún
                'humano_activo',     // Un agente de secretaría está atendiendo
                'cerrado',           // Conversación terminada (bot puede reactivarse)
            ])->default('bot_activo');

            // Historial de mensajes [{role: user|assistant, content: "..."}]
            // Se mantienen solo los últimos N mensajes para contexto
            $table->json('historial')->nullable();

            // Metadatos adicionales (e.g. nombre del agente que tomó control)
            $table->json('metadata')->nullable();

            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
