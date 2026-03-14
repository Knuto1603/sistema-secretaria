<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_estudios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 100);
            $table->foreignUuid('escuela_id')->constrained('escuelas')->cascadeOnDelete();
            $table->boolean('activo')->default(false);
            $table->integer('total_creditos_obligatorios')->default(0);
            $table->integer('creditos_electivos_requeridos')->default(0);
            $table->timestamps();

            $table->unique(['escuela_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_estudios');
    }
};
