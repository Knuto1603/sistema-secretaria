<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_requisitos', function (Blueprint $table) {
            $table->foreignUuid('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignUuid('requisito_curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->primary(['curso_id', 'requisito_curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_requisitos');
    }
};
