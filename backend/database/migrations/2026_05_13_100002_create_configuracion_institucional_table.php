<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_institucional', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->text('valor')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        // Valores por defecto
        $now = now();
        DB::table('configuracion_institucional')->insert([
            ['clave' => 'universidad',          'valor' => 'UNIVERSIDAD NACIONAL DE PIURA',              'descripcion' => 'Nombre de la universidad',            'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'facultad',             'valor' => 'FACULTAD DE INGENIERÍA INDUSTRIAL',           'descripcion' => 'Nombre de la facultad',               'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'dependencia',          'valor' => 'SECRETARIA ACADEMICA',                        'descripcion' => 'Nombre de la dependencia',            'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'secretario_titulo',    'valor' => 'Dr.',                                          'descripcion' => 'Título del secretario académico',     'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'secretario_nombre',    'valor' => 'Jonathan David Nima Ramos',                   'descripcion' => 'Nombre completo del secretario',      'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'secretario_cargo',     'valor' => 'Secretario Académico',                        'descripcion' => 'Cargo del secretario',                'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'institucion_firma',    'valor' => 'Facultad de Ingeniería Industrial - UNP',     'descripcion' => 'Institución en el bloque de firma',   'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'ciudad',               'valor' => 'Piura',                                        'descripcion' => 'Ciudad del documento',                'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'anio_lema',            'valor' => 'AÑO DE LA ESPERANZA Y EL FORTALECIMIENTO DE LA DEMOCRACIA', 'descripcion' => 'Lema del año', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_institucional');
    }
};
