<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Docente;
use App\Models\Curso;
use App\Models\Periodo;
use App\Models\ProgramacionAcademica;

class AcademicProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear Periodo Activo
        $periodo = Periodo::updateOrCreate(
            ['nombre' => '2026-I'],
            ['activo' => true]
        );

        // 2. Crear Áreas
        $areaCiencias = Area::firstOrCreate(['nombre' => 'CIENCIAS']);
        $areaIngenieria = Area::firstOrCreate(['nombre' => 'INGENIERÍA']);

        // 3. Crear Docentes
        $docente1 = Docente::firstOrCreate(['nombre_completo' => 'DR. ALBERT EINSTEIN']);
        $docente2 = Docente::firstOrCreate(['nombre_completo' => 'ING. ADA LOVELACE']);
        $docente3 = Docente::firstOrCreate(['nombre_completo' => 'MAG. ISAAC NEWTON']);

        // 4. Crear Cursos (Catálogo)
        $cursoMat = Curso::firstOrCreate(
            ['codigo' => 'MAT101'],
            ['nombre' => 'CÁLCULO DIFERENCIAL', 'area_id' => $areaCiencias->id]
        );

        $cursoProg = Curso::firstOrCreate(
            ['codigo' => 'INF202'],
            ['nombre' => 'PROGRAMACIÓN ORIENTADA A OBJETOS', 'area_id' => $areaIngenieria->id]
        );

        // 5. Crear Programación Académica (Grupos específicos)

        // Caso 1: Grupo sin cupo (Capacidad == Inscritos)
        ProgramacionAcademica::updateOrCreate(
            ['clave' => '1001', 'grupo' => 'A'],
            [
                'curso_id' => $cursoMat->id,
                'periodo_id' => $periodo->id,
                'docente_id' => $docente3->id,
                'seccion' => '01',
                'aula' => 'Aula 204',
                'capacidad' => 40,
                'n_inscritos' => 40
            ]
        );

        // Caso 2: Grupo con poco cupo
        ProgramacionAcademica::updateOrCreate(
            ['clave' => '1002', 'grupo' => 'B'],
            [
                'curso_id' => $cursoProg->id,
                'periodo_id' => $periodo->id,
                'docente_id' => $docente2->id,
                'seccion' => '02',
                'aula' => 'Laboratorio L1',
                'capacidad' => 25,
                'n_inscritos' => 24
            ]
        );

        // Caso 3: Grupo con vacantes
        ProgramacionAcademica::updateOrCreate(
            ['clave' => '1003', 'grupo' => 'C'],
            [
                'curso_id' => $cursoMat->id,
                'periodo_id' => $periodo->id,
                'docente_id' => $docente1->id,
                'seccion' => '01',
                'aula' => 'Aula Virtual',
                'capacidad' => 60,
                'n_inscritos' => 45
            ]
        );
    }
}
