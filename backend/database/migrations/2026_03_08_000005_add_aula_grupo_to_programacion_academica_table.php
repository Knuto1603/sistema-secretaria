<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->foreignUuid('aula_id')
                ->nullable()
                ->after('docente_id')
                ->constrained('aulas')
                ->nullOnDelete();

            $table->foreignUuid('grupo_horario_id')
                ->nullable()
                ->after('aula_id')
                ->constrained('grupos_horario')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->dropForeign(['aula_id']);
            $table->dropForeign(['grupo_horario_id']);
            $table->dropColumn(['aula_id', 'grupo_horario_id']);
        });
    }
};
