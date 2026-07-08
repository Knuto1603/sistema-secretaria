<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modificaciones_programacion', function (Blueprint $table) {
            $table->foreignUuid('borrador_id')
                ->nullable()
                ->after('periodo_id')
                ->constrained('borradores_programacion')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modificaciones_programacion', function (Blueprint $table) {
            $table->dropForeign(['borrador_id']);
            $table->dropColumn('borrador_id');
        });
    }
};
