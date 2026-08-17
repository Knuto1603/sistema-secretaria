<?php

namespace Database\Seeders;

use App\Models\TipoSolicitud;
use Illuminate\Database\Seeder;

class TipoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        TipoSolicitud::updateOrCreate(
            ['codigo' => 'CUPO_EXT'],
            [
                'nombre' => 'Cupo Extra',
                'descripcion' => 'Solicitud de cupo en una asignatura que ya alcanzó su capacidad máxima.',
                'activo' => true
            ]
        );

        TipoSolicitud::updateOrCreate(
            ['codigo' => 'INSC_ESCUELA'],
            [
                'nombre' => 'Inscripción entre Escuelas',
                'descripcion' => 'Solicitud para inscribirse en una sección programada por otra escuela profesional que tiene cupos disponibles.',
                'activo' => true
            ]
        );

        TipoSolicitud::updateOrCreate(
            ['codigo' => 'RETIRO_CURSO'],
            [
                'nombre' => 'Anulación de Matrícula',
                'descripcion' => 'Constancia para gestionar ante la Secretaría Académica la anulación de la matrícula del alumno en un curso.',
                'requiere_archivo' => false,
                'activo' => true
            ]
        );
    }
}
