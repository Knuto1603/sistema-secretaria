<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_estudios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('escuela_id')->constrained('escuelas')->cascadeOnDelete();
            $table->foreignUuid('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->tinyInteger('ciclo')->unsigned()->nullable();   // 1-10
            $table->tinyInteger('creditos')->unsigned()->nullable();
            $table->timestamps();

            $table->unique(['escuela_id', 'curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_estudios');
    }
};
