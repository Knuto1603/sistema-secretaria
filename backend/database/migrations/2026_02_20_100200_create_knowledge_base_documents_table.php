<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Puede estar vinculado a un artículo o ser documento standalone
            $table->foreignUuid('knowledge_base_id')->nullable()->constrained('knowledge_base')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('filename');           // nombre guardado en disco
            $table->string('original_filename');  // nombre original del upload
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes')->default(0);
            // true = formulario descargable / false = documento oficial de lectura/cita
            $table->boolean('es_plantilla')->default(false);
            $table->longText('extracted_text')->nullable(); // texto extraído del PDF/Word
            $table->boolean('procesado')->default(false);   // texto extraído?
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['knowledge_base_id', 'activo']);
            $table->index('es_plantilla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_documents');
    }
};
