<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE modificaciones_programacion MODIFY tipo ENUM(
            'cerrar_curso',
            'abrir_seccion',
            'reabrir_seccion',
            'cambio_aula',
            'cambio_grupo',
            'cambio_aula_y_grupo',
            'unificacion_secciones',
            'actualizar_cupo'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE modificaciones_programacion MODIFY tipo ENUM(
            'cerrar_curso',
            'abrir_seccion',
            'reabrir_seccion',
            'cambio_aula',
            'cambio_grupo',
            'cambio_aula_y_grupo',
            'unificacion_secciones'
        ) NOT NULL");
    }
};
