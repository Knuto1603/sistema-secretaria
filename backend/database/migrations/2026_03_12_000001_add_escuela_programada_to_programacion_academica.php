<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->foreignUuid('escuela_programada_id')
                  ->nullable()
                  ->after('periodo_id')
                  ->constrained('escuelas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->dropForeign(['escuela_programada_id']);
            $table->dropColumn('escuela_programada_id');
        });
    }
};
