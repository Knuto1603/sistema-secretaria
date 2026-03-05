<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('tipo', ['proceso', 'faq', 'norma', 'requisito', 'resolucion']);
            $table->string('titulo');
            $table->longText('contenido');
            $table->string('categoria')->default('general');
            $table->json('tags')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['tipo', 'activo']);
            $table->index('categoria');
        });

        // FULLTEXT para búsqueda semántica
        DB::statement('ALTER TABLE knowledge_base ADD FULLTEXT ft_kb_titulo_contenido (titulo, contenido)');
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
