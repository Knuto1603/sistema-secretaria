<?php

namespace App\Console\Commands;

use App\Models\GrupoHorario;
use App\Services\PlantillaHorarioService;
use Illuminate\Console\Command;

class BackfillDetallesGruposHorario extends Command
{
    protected $signature = 'grupos-horario:backfill-detalles';

    protected $description = 'Genera los horarios (día+hora) faltantes de los grupos horario existentes según la Plantilla Horaria institucional (G1, G1A, G7ABH, etc.)';

    public function handle(PlantillaHorarioService $plantilla): int
    {
        $grupos = GrupoHorario::doesntHave('detalles')->get();

        if ($grupos->isEmpty()) {
            $this->info('No hay grupos sin horario. Nada que hacer.');
            return self::SUCCESS;
        }

        $completados = 0;
        $sinPatron = [];

        foreach ($grupos as $grupo) {
            $detalles = $plantilla->generarDetalles($grupo->nombre);

            if (empty($detalles)) {
                $sinPatron[] = $grupo->nombre;
                continue;
            }

            foreach ($detalles as $detalle) {
                $grupo->detalles()->create($detalle);
            }

            $completados++;
            $this->line("  {$grupo->nombre} -> " . collect($detalles)->pluck('dia_semana')->implode(', '));
        }

        $this->info("Grupos completados: {$completados}");

        if (!empty($sinPatron)) {
            $this->warn('Grupos sin patrón reconocible (revisar a mano): ' . implode(', ', $sinPatron));
        }

        return self::SUCCESS;
    }
}
