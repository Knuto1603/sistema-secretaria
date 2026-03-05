<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('contenido');
            $table->unsignedSmallInteger('tokens_used')->nullable();
            // IDs de artículos KB y documentos usados como contexto
            $table->json('context_articles')->nullable();
            $table->json('context_documents')->nullable();
            $table->json('templates_sugeridos')->nullable();
            $table->boolean('tuvo_contexto')->default(false); // si se encontró contexto relevante
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
