<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Áreas Académicas
        Schema::create('areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        // 2. Docentes
        Schema::create('docentes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre_completo');
            $table->timestamps();
        });

        // 3. Catálogo de Cursos (La materia como tal)
        Schema::create('cursos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->foreignUuid('area_id')->constrained('areas');
            $table->timestamps();
        });

        // 4. Periodos Académicos (2026-0, 2026-1, etc.)
        Schema::create('periodos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(false);
            $table->timestamps();
        });

        // 5. Programación Académica (La unión de todo lo anterior + datos del Excel)
        Schema::create('programacion_academica', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('curso_id')->constrained('cursos');
            $table->foreignUuid('periodo_id')->constrained('periodos');
            $table->foreignUuid('docente_id')->nullable()->constrained('docentes');

            $table->string('clave');
            $table->string('grupo');
            $table->string('seccion')->nullable();
            $table->string('aula')->nullable();
            $table->string('n_acta')->nullable();

            $table->integer('capacidad')->default(0);
            $table->integer('n_inscritos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programacion_academica');
        Schema::dropIfExists('periodos');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('areas');
    }
};
