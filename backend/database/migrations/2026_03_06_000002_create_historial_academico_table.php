<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de cursos aprobados por el alumno (autoreportado o importado)
        Schema::create('historial_academico', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('curso_id')
                ->constrained('cursos')
                ->cascadeOnDelete();

            // Fuente del dato
            $table->enum('fuente', ['autoreporte', 'importado'])->default('autoreporte');

            $table->timestamps();

            // Un alumno no puede tener duplicados del mismo curso
            $table->unique(['user_id', 'curso_id']);
        });

        // Fecha en que el alumno actualizó por última vez su historial
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ultima_actualizacion_historial')->nullable()->after('anio_ingreso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ultima_actualizacion_historial');
        });

        Schema::dropIfExists('historial_academico');
    }
};
