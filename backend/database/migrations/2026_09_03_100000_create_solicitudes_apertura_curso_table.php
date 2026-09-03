<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_apertura_curso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignUuid('periodo_id')->constrained('periodos')->restrictOnDelete();
            $table->foreignUuid('escuela_id')->constrained('escuelas')->restrictOnDelete();

            $table->enum('tipo', ['nueva_apertura', 'cambio_grupo'])->default('nueva_apertura');
            $table->foreignUuid('programacion_referencia_id')->nullable()
                ->constrained('programacion_secciones')->nullOnDelete();

            $table->text('motivo');
            $table->string('firma_digital_path')->nullable();
            $table->enum('estado', ['pendiente', 'en_revision', 'aprobada', 'rechazada', 'anulada'])
                ->default('pendiente');
            $table->text('observaciones_admin')->nullable();

            $table->timestamps();

            $table->index(['curso_id', 'periodo_id']);
            $table->index(['user_id', 'periodo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_apertura_curso');
    }
};
