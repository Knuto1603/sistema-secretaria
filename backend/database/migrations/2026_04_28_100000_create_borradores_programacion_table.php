<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borradores_programacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->string('nombre', 50); // Ej: "2026-I", "2026-II"
            $table->enum('ciclo_tipo', ['par', 'impar']);
            $table->enum('estado', ['borrador', 'publicado'])->default('borrador');
            $table->foreignUuid('creado_por')->constrained('users');
            $table->foreignUuid('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borradores_programacion');
    }
};
