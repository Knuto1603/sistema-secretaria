<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registra qué artículos de KB se usaron en cada respuesta del asistente.
        // Permite analytics: "temas más consultados", "brechas de conocimiento".
        Schema::create('chat_message_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignUuid('knowledge_base_id')->constrained('knowledge_base')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'knowledge_base_id']);
            $table->index('knowledge_base_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_topics');
    }
};
