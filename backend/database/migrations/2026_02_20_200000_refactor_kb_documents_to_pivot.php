<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia la relación documento→artículo de uno-a-muchos a muchos-a-muchos.
 *
 * Antes: knowledge_base_documents.knowledge_base_id  (FK → knowledge_base)
 * Ahora: pivot knowledge_base_article_documents       (knowledge_base_id + document_id)
 *
 * Un documento puede ahora estar asociado a varios artículos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear tabla pivot
        Schema::create('knowledge_base_article_documents', function (Blueprint $table) {
            $table->foreignUuid('knowledge_base_id')->constrained('knowledge_base')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('knowledge_base_documents')->cascadeOnDelete();
            $table->primary(['knowledge_base_id', 'document_id']);
            $table->timestamps();
        });

        // 2. Migrar asociaciones existentes al pivot
        DB::statement('
            INSERT INTO knowledge_base_article_documents (knowledge_base_id, document_id, created_at, updated_at)
            SELECT knowledge_base_id, id, NOW(), NOW()
            FROM knowledge_base_documents
            WHERE knowledge_base_id IS NOT NULL
        ');

        // 3. Eliminar FK e índice antiguo, luego la columna
        Schema::table('knowledge_base_documents', function (Blueprint $table) {
            $table->dropForeign(['knowledge_base_id']);
            $table->dropIndex(['knowledge_base_id', 'activo']);
            $table->dropColumn('knowledge_base_id');
        });

        // 4. Recrear índice de activo sin knowledge_base_id
        Schema::table('knowledge_base_documents', function (Blueprint $table) {
            $table->index('activo');
        });
    }

    public function down(): void
    {
        // 1. Restaurar columna
        Schema::table('knowledge_base_documents', function (Blueprint $table) {
            $table->dropIndex(['activo']);
            $table->foreignUuid('knowledge_base_id')
                ->nullable()
                ->constrained('knowledge_base')
                ->nullOnDelete()
                ->after('id');
        });

        // 2. Restaurar datos desde pivot (solo primera asociación por documento)
        DB::statement('
            UPDATE knowledge_base_documents d
            INNER JOIN (
                SELECT document_id, MIN(knowledge_base_id) AS knowledge_base_id
                FROM knowledge_base_article_documents
                GROUP BY document_id
            ) p ON p.document_id = d.id
            SET d.knowledge_base_id = p.knowledge_base_id
        ');

        // 3. Restaurar índice compuesto
        Schema::table('knowledge_base_documents', function (Blueprint $table) {
            $table->index(['knowledge_base_id', 'activo']);
        });

        // 4. Eliminar pivot
        Schema::dropIfExists('knowledge_base_article_documents');
    }
};
