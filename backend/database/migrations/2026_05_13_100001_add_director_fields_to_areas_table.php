<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->string('titulo_director', 20)->nullable()->after('nombre');   // Doctor / Magister
            $table->string('director_nombre', 150)->nullable()->after('titulo_director');
            $table->string('director_cargo', 250)->nullable()->after('director_nombre'); // "Director del Departamento Académico de Matemática"
            $table->string('nombre_tabla', 100)->nullable()->after('director_cargo');    // "MATEMÁTICA"
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn(['titulo_director', 'director_nombre', 'director_cargo', 'nombre_tabla']);
        });
    }
};
