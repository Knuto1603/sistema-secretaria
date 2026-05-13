<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generaciones_documentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('borrador_id')->constrained('borradores_programacion')->cascadeOnDelete();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->string('numero_oficio', 100);
            $table->string('semestre_texto', 50);
            $table->foreignUuid('generado_por')->constrained('users');
            $table->timestamp('generado_at');
            $table->integer('total_documentos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generaciones_documentos');
    }
};
