<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escuelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 1)->unique(); // 0, 1, 2, 3
            $table->string('nombre');
            $table->string('nombre_corto', 20);
            $table->timestamps();
        });

        // Insertar las 4 escuelas de la FII
        DB::table('escuelas')->insert([
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'codigo' => '0',
                'nombre' => 'Ingeniería Industrial',
                'nombre_corto' => 'Industrial',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'codigo' => '1',
                'nombre' => 'Ingeniería Informática',
                'nombre_corto' => 'Informática',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'codigo' => '2',
                'nombre' => 'Ingeniería Agroindustrial e Industrias Alimentarias',
                'nombre_corto' => 'Agroindustrial',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'codigo' => '3',
                'nombre' => 'Ingeniería Mecatrónica',
                'nombre_corto' => 'Mecatrónica',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('escuelas');
    }
};
