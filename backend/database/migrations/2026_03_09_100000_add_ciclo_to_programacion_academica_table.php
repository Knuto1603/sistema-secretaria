<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->tinyInteger('ciclo')->nullable()->after('seccion');
        });
    }

    public function down(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->dropColumn('ciclo');
        });
    }
};
