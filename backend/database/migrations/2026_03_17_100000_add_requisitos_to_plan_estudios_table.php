<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_estudios', function (Blueprint $table) {
            // Códigos de cursos requisito, scopeados al plan (evita contaminación entre escuelas)
            $table->json('requisitos')->nullable()->after('horas_practicas');
        });
    }

    public function down(): void
    {
        Schema::table('plan_estudios', function (Blueprint $table) {
            $table->dropColumn('requisitos');
        });
    }
};
