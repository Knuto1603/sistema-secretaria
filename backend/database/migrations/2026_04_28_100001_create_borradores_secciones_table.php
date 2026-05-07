<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borradores_secciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('borrador_id')->constrained('borradores_programacion')->cascadeOnDelete();
            $table->foreignUuid('curso_id')->constrained('cursos');
            $table->foreignUuid('escuela_id')->constrained('escuelas');
            $table->tinyInteger('ciclo')->unsigned(); // 1–10
            $table->char('tipo', 1)->default('O');   // O = Obligatorio, E = Electivo
            $table->char('seccion', 2);              // A, B, C... o A1, A2
            $table->foreignUuid('docente_id')->nullable()->constrained('docentes')->nullOnDelete();
            $table->foreignUuid('aula_id')->nullable()->constrained('aulas')->nullOnDelete();
            $table->foreignUuid('grupo_horario_id')->nullable()->constrained('grupos_horario')->nullOnDelete();
            $table->unsignedSmallInteger('capacidad')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borradores_secciones');
    }
};
