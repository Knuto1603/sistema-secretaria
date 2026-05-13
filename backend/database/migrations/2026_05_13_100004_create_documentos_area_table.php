<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_area', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generacion_id')
                ->constrained('generaciones_documentos')
                ->cascadeOnDelete();
            $table->foreignUuid('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('nombre_archivo', 255);
            $table->string('ruta', 500);
            $table->integer('cursos_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_area');
    }
};
