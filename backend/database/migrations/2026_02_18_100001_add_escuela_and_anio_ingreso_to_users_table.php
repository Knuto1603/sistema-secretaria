<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('escuela_id')->nullable()->after('codigo_universitario');
            $table->year('anio_ingreso')->nullable()->after('escuela_id');

            $table->foreign('escuela_id')
                ->references('id')
                ->on('escuelas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['escuela_id']);
            $table->dropColumn(['escuela_id', 'anio_ingreso']);
        });
    }
};
