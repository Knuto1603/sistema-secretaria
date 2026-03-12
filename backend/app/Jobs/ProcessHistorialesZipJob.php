<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\ImportHistorialesZipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessHistorialesZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tiempo máximo de ejecución en segundos (10 minutos).
     */
    public int $timeout = 600;

    /**
     * Sin reintentos automáticos.
     */
    public int $tries = 1;

    public function __construct(
        private readonly string $importJobId,
        private readonly string $storedPath,   // ruta relativa en storage/app
    ) {}

    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob) {
            return;
        }

        $importJob->update(['estado' => 'procesando']);

        try {
            $absolutePath = storage_path('app/' . $this->storedPath);

            if (!file_exists($absolutePath)) {
                throw new \Exception('El archivo ZIP temporal ya no existe.');
            }

            $uploadedFile = new UploadedFile(
                $absolutePath,
                basename($this->storedPath),
                'application/zip',
                null,
                true  // test mode: no valida que sea un archivo "real" del request
            );

            $service = new ImportHistorialesZipService();
            $resumen = $service->import($uploadedFile);

            $importJob->update([
                'estado'    => 'completado',
                'resultado' => $resumen,
            ]);
        } catch (\Throwable $e) {
            $importJob->update([
                'estado'         => 'fallido',
                'error_mensaje'  => $e->getMessage(),
            ]);
        } finally {
            // Eliminar el archivo temporal del storage
            if (Storage::exists($this->storedPath)) {
                Storage::delete($this->storedPath);
            }
        }
    }
}
