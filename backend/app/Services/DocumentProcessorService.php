<?php

namespace App\Services;

use App\Models\KnowledgeBaseChunk;
use App\Models\KnowledgeBaseDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentProcessorService
{
    private int $chunkWords;
    private int $chunkOverlap;

    public function __construct()
    {
        $this->chunkWords   = config('chatbot.chunk_words', 500);
        $this->chunkOverlap = config('chatbot.chunk_overlap', 50);
    }

    /**
     * Guarda el archivo en disco, extrae el texto y genera los chunks.
     */
    public function process(
        UploadedFile $file,
        string $titulo,
        ?string $descripcion,
        bool $esPlantilla
    ): KnowledgeBaseDocument {
        $folder   = $esPlantilla ? 'plantillas' : 'docs';
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = "knowledge-base/{$folder}/{$filename}";

        Storage::disk('public')->putFileAs(
            "knowledge-base/{$folder}",
            $file,
            $filename
        );

        $doc = KnowledgeBaseDocument::create([
            'titulo'            => $titulo,
            'descripcion'       => $descripcion,
            'filename'          => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'size_bytes'        => $file->getSize(),
            'es_plantilla'      => $esPlantilla,
            'procesado'         => false,
        ]);

        // Extraer texto y chunkar (solo documentos oficiales, no plantillas)
        if (!$esPlantilla) {
            $text = $this->extractText($file, $path);

            if ($text) {
                $doc->update(['extracted_text' => $text, 'procesado' => true]);
                $this->generateChunks($doc, $text);
            }
        }

        return $doc;
    }

    /**
     * Re-procesa un documento existente (extrae texto y regenera chunks).
     */
    public function reprocess(KnowledgeBaseDocument $doc): void
    {
        $storagePath = Storage::disk('public')->path($doc->getStoragePath());

        if (!file_exists($storagePath)) {
            return;
        }

        $text = $this->extractTextFromPath($storagePath, $doc->mime_type);

        if ($text) {
            $doc->chunks()->delete();
            $doc->update(['extracted_text' => $text, 'procesado' => true]);
            $this->generateChunks($doc, $text);
        }
    }

    // =========================================================================
    // PRIVADO
    // =========================================================================

    private function extractText(UploadedFile $file, string $storagePath): ?string
    {
        return $this->extractTextFromPath($file->getRealPath(), $file->getMimeType());
    }

    private function extractTextFromPath(string $realPath, string $mimeType): ?string
    {
        try {
            if ($mimeType === 'application/pdf') {
                return $this->extractFromPdf($realPath);
            }

            if (in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword',
            ])) {
                return $this->extractFromWord($realPath);
            }

            if (str_starts_with($mimeType, 'text/')) {
                return file_get_contents($realPath);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('DocumentProcessorService: error extrayendo texto', [
                'error' => $e->getMessage(),
                'path'  => $realPath,
            ]);
        }

        return null;
    }

    private function extractFromPdf(string $path): string
    {
        $parser   = new \Smalot\PdfParser\Parser();
        $pdf      = $parser->parseFile($path);
        return $pdf->getText();
    }

    private function extractFromWord(string $path): string
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $text    = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $text .= $child->getText() . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        return trim($text);
    }

    private function generateChunks(KnowledgeBaseDocument $doc, string $text): void
    {
        // Dividir en palabras y agrupar en chunks con solapamiento
        $words      = preg_split('/\s+/', trim($text));
        $total      = count($words);
        $step       = $this->chunkWords - $this->chunkOverlap;
        $chunkIndex = 0;
        $chunks     = [];

        for ($i = 0; $i < $total; $i += $step) {
            $slice   = array_slice($words, $i, $this->chunkWords);
            $content = implode(' ', $slice);

            if (mb_strlen(trim($content)) < 50) {
                continue; // descarta fragmentos demasiado cortos
            }

            $chunks[] = [
                'document_id' => $doc->id,
                'chunk_index' => $chunkIndex++,
                'contenido'   => $content,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        if (!empty($chunks)) {
            // Insertar con UUID manual ya que usamos HasUuids
            foreach ($chunks as &$c) {
                $c['id'] = (string) Str::uuid();
            }
            KnowledgeBaseChunk::insert($chunks);
        }
    }
}
