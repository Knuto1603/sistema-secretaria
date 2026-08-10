<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\ImportHistorialesZipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessHistorialesZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tiempo máximo de ejecución: 30 minutos. */
    public int $timeout = 1800;

    /** Sin reintentos automáticos. */
    public int $tries = 1;

    public function __construct(
        private readonly string $importJobId,
        private readonly string $storedPath,  // path relativo al disco 'local' (storage/app/)
    ) {}

    public function handle(): void
    {
        // El php.ini del contenedor limita max_execution_time/memory_limit pensando
        // en requests web; este proceso corre en el worker de cola y necesita más margen.
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob) {
            return;
        }

        $importJob->update(['estado' => 'procesando']);

        try {
            // Obtener la ruta absoluta via Storage para que sea consistente
            // independientemente del contenedor que lo ejecute
            $absolutePath = Storage::disk('local')->path($this->storedPath);

            if (!file_exists($absolutePath)) {
                throw new \Exception(
                    "Archivo ZIP no encontrado en: {$absolutePath}. " .
                    "Verifica que el volumen 'backend-storage' esté montado en el servicio queue."
                );
            }

            $service = new ImportHistorialesZipService();
            $resumen = $service->importFromPath($absolutePath);

            $importJob->update([
                'estado'    => 'completado',
                'resultado' => $resumen,
            ]);
        } catch (\Throwable $e) {
            $importJob->update([
                'estado'        => 'fallido',
                'error_mensaje' => $e->getMessage(),
            ]);
        } finally {
            // Eliminar el archivo temporal del storage
            if (Storage::disk('local')->exists($this->storedPath)) {
                Storage::disk('local')->delete($this->storedPath);
            }
        }
    }

    /**
     * Se invoca cuando Laravel mata el job por timeout (pcntl) o por agotar
     * los intentos. Sin esto, el ImportJob se queda colgado en "procesando"
     * para siempre porque el proceso muere antes de llegar al catch de handle().
     */
    public function failed(\Throwable $exception): void
    {
        ImportJob::find($this->importJobId)?->update([
            'estado'        => 'fallido',
            'error_mensaje' => $exception->getMessage(),
        ]);

        if (Storage::disk('local')->exists($this->storedPath)) {
            Storage::disk('local')->delete($this->storedPath);
        }
    }
}
