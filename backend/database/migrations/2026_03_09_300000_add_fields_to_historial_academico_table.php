<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_academico', function (Blueprint $table) {
            // Eliminar unique anterior (user_id, curso_id) para permitir cursos repetidos
            $table->dropUnique(['user_id', 'curso_id']);

            // Datos del semestre
            $table->string('semestre', 10)->nullable()->after('fuente');  // "2021-1", "2024-0"
            $table->char('tipo', 1)->nullable()->after('semestre');       // O=Obligatorio, E=Electivo
            $table->unsignedSmallInteger('creditos')->nullable()->after('tipo');
            $table->decimal('nota', 4, 2)->nullable()->after('creditos'); // 0.00 - 20.00

            // Nueva unique: un alumno no puede tener el mismo curso en el mismo semestre dos veces
            $table->unique(['user_id', 'curso_id', 'semestre']);
        });
    }

    public function down(): void
    {
        Schema::table('historial_academico', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'curso_id', 'semestre']);
            $table->dropColumn(['semestre', 'tipo', 'creditos', 'nota']);
            $table->unique(['user_id', 'curso_id']);
        });
    }
};
