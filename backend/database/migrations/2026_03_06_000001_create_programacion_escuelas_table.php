<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot: relaciona cada sección de programación con las escuelas
     * que pueden inscribirse en ella.
     * Un grupo (clave) puede pertenecer a varias escuelas simultáneamente.
     */
    public function up(): void
    {
        Schema::create('programacion_escuelas', function (Blueprint $table) {
            $table->foreignUuid('programacion_id')
                ->constrained('programacion_academica')
                ->cascadeOnDelete();

            $table->foreignUuid('escuela_id')
                ->constrained('escuelas')
                ->cascadeOnDelete();

            $table->primary(['programacion_id', 'escuela_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programacion_escuelas');
    }
};
