<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_academico', function (Blueprint $table) {
            // MySQL no permite eliminar un índice usado por una FK sin soltar la FK primero
            $table->dropForeign(['user_id']);
            $table->dropForeign(['curso_id']);
            $table->dropUnique(['user_id', 'curso_id']);

            // Re-crear las foreign keys (sin índice unique anterior)
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();

            // Nuevos campos
            $table->string('semestre', 10)->nullable()->after('fuente');
            $table->char('tipo', 1)->nullable()->after('semestre');
            $table->unsignedSmallInteger('creditos')->nullable()->after('tipo');
            $table->decimal('nota', 4, 2)->nullable()->after('creditos');

            // Nueva unique: permite el mismo curso en distintos semestres
            $table->unique(['user_id', 'curso_id', 'semestre']);
        });
    }

    public function down(): void
    {
        Schema::table('historial_academico', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['curso_id']);
            $table->dropUnique(['user_id', 'curso_id', 'semestre']);
            $table->dropColumn(['semestre', 'tipo', 'creditos', 'nota']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $table->unique(['user_id', 'curso_id']);
        });
    }
};
