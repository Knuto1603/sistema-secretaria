<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixEstudiantesDatos extends Command
{
    protected $signature = 'estudiantes:fix-datos
                            {--force : Reasignar incluso si ya tienen datos}';

    protected $description = 'Asigna escuela_id y anio_ingreso a estudiantes que aún no los tienen, derivándolos del código universitario';

    public function handle(): int
    {
        $query = User::where('tipo_usuario', 'estudiante')
            ->whereNotNull('codigo_universitario');

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('escuela_id')->orWhereNull('anio_ingreso');
            });
        }

        $estudiantes = $query->get();

        if ($estudiantes->isEmpty()) {
            $this->info('No hay estudiantes con datos faltantes.');
            return Command::SUCCESS;
        }

        $this->info("Procesando {$estudiantes->count()} estudiante(s)...");

        $ok = 0;
        $err = 0;

        foreach ($estudiantes as $user) {
            $result = $user->asignarDatosDesdeCodigoUniversitario();
            if ($result) {
                $ok++;
            } else {
                $this->warn("No se pudo procesar: {$user->codigo_universitario} ({$user->name})");
                $err++;
            }
        }

        $this->info("Completado: {$ok} actualizados, {$err} con error.");

        return Command::SUCCESS;
    }
}
