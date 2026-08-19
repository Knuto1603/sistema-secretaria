<?php

namespace Database\Seeders;

use App\Models\GrupoHorario;
use Illuminate\Database\Seeder;

class GrupoHorarioSeeder extends Seeder
{
    /**
     * Combinaciones de letras válidas según la Plantilla Horaria institucional
     * (PlantillaHorarioService: a=lunes/jueves, b=martes/viernes, h=miércoles).
     */
    private const COMBINACIONES_LETRAS = ['A', 'B', 'H', 'AB', 'AH', 'BH', 'ABH'];

    public function run(): void
    {
        $this->command->info('Creando grupos horario G1-G14 con sus combinaciones de letras (A/B/H)...');

        $creados = 0;
        for ($i = 1; $i <= 14; $i++) {
            foreach (self::COMBINACIONES_LETRAS as $letras) {
                // resolverPorCodigo crea el grupo y le genera el detalle de
                // horario (día+hora) en el mismo paso, según PlantillaHorarioService.
                GrupoHorario::resolverPorCodigo('G' . $i . $letras);
                $creados++;
            }
        }

        $this->command->info("  ✓ {$creados} grupos horario creados (G1A..G14ABH), con su detalle de horario.");
    }
}
