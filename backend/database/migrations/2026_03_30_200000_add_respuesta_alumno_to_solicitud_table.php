<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud', function (Blueprint $table) {
            $table->text('respuesta_alumno')->nullable()->after('observaciones_admin');
            $table->timestamp('fecha_respuesta')->nullable()->after('respuesta_alumno');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud', function (Blueprint $table) {
            $table->dropColumn(['respuesta_alumno', 'fecha_respuesta']);
        });
    }
};
