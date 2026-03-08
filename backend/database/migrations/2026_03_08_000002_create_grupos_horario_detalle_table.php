<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_horario_detalle', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grupo_id')
                ->constrained('grupos_horario')
                ->cascadeOnDelete();
            $table->enum('dia_semana', ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado']);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_horario_detalle');
    }
};
