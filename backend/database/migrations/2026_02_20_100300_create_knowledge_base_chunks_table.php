<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('knowledge_base_documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index');
            $table->text('contenido');
            $table->timestamps();

            $table->index(['document_id', 'chunk_index']);
        });

        DB::statement('ALTER TABLE knowledge_base_chunks ADD FULLTEXT ft_chunks_contenido (contenido)');
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_chunks');
    }
};
