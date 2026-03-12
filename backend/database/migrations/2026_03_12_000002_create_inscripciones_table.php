<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('programacion_id')->constrained('programacion_academica')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('periodo_id')->constrained('periodos')->cascadeOnDelete();
            $table->enum('fuente', ['siga', 'manual'])->default('siga');
            $table->timestamps();

            $table->unique(['programacion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
