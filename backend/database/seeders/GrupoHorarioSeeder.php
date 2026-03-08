<?php

namespace Database\Seeders;

use App\Models\GrupoHorario;
use Illuminate\Database\Seeder;

class GrupoHorarioSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando grupos horario G1-G14...');

        for ($i = 1; $i <= 14; $i++) {
            GrupoHorario::firstOrCreate(
                ['nombre' => 'G' . $i],
                ['descripcion' => 'Grupo horario ' . $i, 'activo' => true]
            );
        }

        $this->command->info('  ✓ Grupos G1-G14 creados.');
    }
}
