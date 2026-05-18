<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modificaciones_programacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->restrictOnDelete();
            $table->enum('tipo', [
                'cerrar_curso',
                'abrir_seccion',
                'cambio_aula',
                'cambio_grupo',
                'cambio_aula_y_grupo',
                'unificacion_secciones',
            ]);
            // Null solo cuando tipo = 'abrir_seccion' (la sección se crea en esta operación)
            $table->foreignUuid('programacion_id')
                ->nullable()
                ->constrained('programacion_academica')
                ->nullOnDelete();
            // Para unificacion_secciones: UUIDs de secciones absorbidas
            $table->json('secciones_origen_ids')->nullable();
            $table->json('datos_anteriores');
            $table->json('datos_nuevos');
            $table->text('motivo');
            $table->enum('estado', ['pendiente', 'documentado'])->default('pendiente');
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // periodo_id, programacion_id y user_id ya tienen índice por ser FKs (InnoDB)
            $table->index('tipo');
            $table->index('estado');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificaciones_programacion');
    }
};
