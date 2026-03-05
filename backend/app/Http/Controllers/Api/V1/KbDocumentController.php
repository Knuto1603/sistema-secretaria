<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseDocument;
use App\Services\DocumentProcessorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KbDocumentController extends Controller
{
    public function __construct(private readonly DocumentProcessorService $processor) {}

    // GET /knowledge-base/documents
    public function index(Request $request): JsonResponse
    {
        $query = KnowledgeBaseDocument::with(['knowledgeBases:id,titulo'])
            ->orderByDesc('created_at');

        // Filtrar documentos asociados a un artículo específico
        if ($request->filled('knowledge_base_id')) {
            $query->whereHas('knowledgeBases', fn($q) => $q->where('id', $request->knowledge_base_id));
        }
        if ($request->filled('es_plantilla')) {
            $query->where('es_plantilla', filter_var($request->es_plantilla, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('titulo', 'LIKE', "%{$s}%")->orWhere('original_filename', 'LIKE', "%{$s}%"));
        }

        $perPage = (int) ($request->per_page ?? 20);
        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator->items(),
            $paginator,
            'Documentos'
        );
    }

    // POST /knowledge-base/documents
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'             => 'required|string|max:255',
            'descripcion'        => 'nullable|string|max:1000',
            'es_plantilla'       => 'boolean',
            'archivo'            => 'required|file|mimes:pdf,doc,docx,txt|max:20480', // 20MB
            'knowledge_base_ids' => 'nullable|array',
            'knowledge_base_ids.*' => 'uuid|exists:knowledge_base,id',
        ]);

        $doc = $this->processor->process(
            file:        $request->file('archivo'),
            titulo:      $data['titulo'],
            descripcion: $data['descripcion'] ?? null,
            esPlantilla: (bool) ($data['es_plantilla'] ?? false),
        );

        // Adjuntar a artículos si se proporcionaron
        if (!empty($data['knowledge_base_ids'])) {
            $doc->knowledgeBases()->sync($data['knowledge_base_ids']);
        }

        return $this->created(
            $doc->load('knowledgeBases:id,titulo'),
            'Documento subido'
        );
    }

    // GET /knowledge-base/documents/{id}
    public function show(string $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::with('knowledgeBases:id,titulo')->findOrFail($id);
        return $this->success($doc, 'Documento');
    }

    // PUT /knowledge-base/documents/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);

        $data = $request->validate([
            'titulo'               => 'sometimes|string|max:255',
            'descripcion'          => 'nullable|string|max:1000',
            'activo'               => 'boolean',
            'knowledge_base_ids'   => 'nullable|array',
            'knowledge_base_ids.*' => 'uuid|exists:knowledge_base,id',
        ]);

        $doc->update(\Arr::except($data, ['knowledge_base_ids']));

        // Sincronizar artículos si se proporcionaron (null = limpiar, array = reemplazar)
        if (array_key_exists('knowledge_base_ids', $data)) {
            $doc->knowledgeBases()->sync($data['knowledge_base_ids'] ?? []);
        }

        return $this->success(
            $doc->fresh('knowledgeBases:id,titulo'),
            'Documento actualizado'
        );
    }

    // DELETE /knowledge-base/documents/{id}
    public function destroy(string $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);

        // Eliminar chunks y archivo físico
        $doc->chunks()->delete();
        Storage::disk('public')->delete($doc->getStoragePath());
        $doc->delete();

        return $this->success(null, 'Documento eliminado');
    }

    // PATCH /knowledge-base/documents/{id}/toggle
    public function toggle(string $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);
        $doc->update(['activo' => !$doc->activo]);
        return $this->success(['activo' => $doc->activo], $doc->activo ? 'Activado' : 'Desactivado');
    }

    // POST /knowledge-base/documents/{id}/reprocess
    public function reprocess(string $id): JsonResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);

        if ($doc->es_plantilla) {
            return $this->error('Las plantillas no se procesan (no se extrae texto de ellas)', 422);
        }

        $this->processor->reprocess($doc);

        return $this->success([
            'procesado'    => $doc->fresh()->procesado,
            'chunks_count' => $doc->chunks()->count(),
        ], 'Documento reprocesado');
    }

    // GET /knowledge-base/documents/{id}/download
    public function download(string $id): StreamedResponse
    {
        $doc = KnowledgeBaseDocument::findOrFail($id);

        abort_if(
            !Storage::disk('public')->exists($doc->getStoragePath()),
            404,
            'Archivo no encontrado'
        );

        return Storage::disk('public')->download(
            $doc->getStoragePath(),
            $doc->original_filename
        );
    }
}
