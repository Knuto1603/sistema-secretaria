<?php

namespace App\Jobs;

use App\Imports\EstudianteReporteMatriculaImport;
use App\Models\ImportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessEstudianteReporteMatriculaImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tiempo máximo de ejecución: 15 minutos. */
    public int $timeout = 900;

    /** Sin reintentos automáticos. */
    public int $tries = 1;

    public function __construct(
        private readonly string $importJobId,
        private readonly string $storedPath,  // path relativo al disco 'local' (storage/app/)
    ) {}

    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob) {
            return;
        }

        $importJob->update(['estado' => 'procesando']);

        try {
            $absolutePath = Storage::disk('local')->path($this->storedPath);

            if (!file_exists($absolutePath)) {
                throw new \Exception(
                    "Archivo no encontrado en: {$absolutePath}. " .
                    "Verifica que el volumen 'backend-storage' esté montado en el servicio queue."
                );
            }

            $sheets = Excel::toArray([], $absolutePath);
            $rows   = $sheets[0] ?? [];

            $import = new EstudianteReporteMatriculaImport();
            $import->procesar($rows);

            $importJob->update([
                'estado'    => 'completado',
                'resultado' => [
                    'resumen'    => $import->getResumen(),
                    'resultados' => $import->getResultados(),
                ],
            ]);
        } catch (\Throwable $e) {
            $importJob->update([
                'estado'        => 'fallido',
                'error_mensaje' => $e->getMessage(),
            ]);
        } finally {
            if (Storage::disk('local')->exists($this->storedPath)) {
                Storage::disk('local')->delete($this->storedPath);
            }
        }
    }
}
