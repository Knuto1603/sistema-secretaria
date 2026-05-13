<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->string('clave')->nullable()->change();
            $table->string('grupo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('programacion_academica', function (Blueprint $table) {
            $table->string('clave')->nullable(false)->change();
            $table->string('grupo')->nullable(false)->change();
        });
    }
};
