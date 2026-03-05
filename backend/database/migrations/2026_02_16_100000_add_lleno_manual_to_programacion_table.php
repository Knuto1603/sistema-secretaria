<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->boolean('lleno_manual')->default(false)->after('n_inscritos');
        });
    }

    public function down(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->dropColumn('lleno_manual');
        });
    }
};
