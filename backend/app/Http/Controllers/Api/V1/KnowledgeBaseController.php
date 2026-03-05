<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    // GET /knowledge-base
    public function index(Request $request): JsonResponse
    {
        $query = KnowledgeBase::with(['documents' => fn($q) => $q->select('knowledge_base_documents.id', 'titulo', 'es_plantilla', 'activo')])
            ->orderBy('orden')
            ->orderBy('titulo');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }
        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('titulo', 'LIKE', "%{$s}%")->orWhere('contenido', 'LIKE', "%{$s}%"));
        }

        $perPage = (int) ($request->per_page ?? 20);
        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator->items(),
            $paginator,
            'Base de conocimientos'
        );
    }

    // GET /knowledge-base/{id}
    public function show(string $id): JsonResponse
    {
        $article = KnowledgeBase::with(['documents', 'relacionados'])->findOrFail($id);
        return $this->success($article, 'Artículo');
    }

    // POST /knowledge-base
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo'      => 'required|in:proceso,faq,norma,requisito,resolucion',
            'titulo'    => 'required|string|max:255',
            'contenido' => 'required|string',
            'categoria' => 'required|string|max:100',
            'tags'      => 'nullable|array',
            'tags.*'    => 'string|max:50',
            'activo'    => 'boolean',
            'orden'     => 'integer|min:0',
        ]);

        $article = KnowledgeBase::create($data);

        return $this->created($article, 'Artículo creado');
    }

    // PUT /knowledge-base/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $article = KnowledgeBase::findOrFail($id);

        $data = $request->validate([
            'tipo'      => 'sometimes|in:proceso,faq,norma,requisito,resolucion',
            'titulo'    => 'sometimes|string|max:255',
            'contenido' => 'sometimes|string',
            'categoria' => 'sometimes|string|max:100',
            'tags'      => 'nullable|array',
            'tags.*'    => 'string|max:50',
            'activo'    => 'boolean',
            'orden'     => 'integer|min:0',
        ]);

        $article->update($data);

        return $this->success($article->fresh(['documents', 'relacionados']), 'Artículo actualizado');
    }

    // DELETE /knowledge-base/{id}
    public function destroy(string $id): JsonResponse
    {
        KnowledgeBase::findOrFail($id)->delete();
        return $this->success(null, 'Artículo eliminado');
    }

    // PATCH /knowledge-base/{id}/toggle
    public function toggle(string $id): JsonResponse
    {
        $article = KnowledgeBase::findOrFail($id);
        $article->update(['activo' => !$article->activo]);
        return $this->success(['activo' => $article->activo], $article->activo ? 'Activado' : 'Desactivado');
    }

    // POST /knowledge-base/{id}/relations
    public function addRelation(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'target_id' => 'required|uuid|exists:knowledge_base,id',
            'tipo'      => 'in:relacionado,prerrequisito,continua_en',
        ]);

        $source = KnowledgeBase::findOrFail($id);
        $source->relacionados()->syncWithoutDetaching([
            $data['target_id'] => ['tipo' => $data['tipo'] ?? 'relacionado'],
        ]);

        return $this->success(null, 'Relación agregada');
    }

    // DELETE /knowledge-base/{id}/relations/{targetId}
    public function removeRelation(string $id, string $targetId): JsonResponse
    {
        $source = KnowledgeBase::findOrFail($id);
        $source->relacionados()->detach($targetId);

        return $this->success(null, 'Relación eliminada');
    }

    // POST /knowledge-base/{id}/documents/{docId}  — adjuntar documento al artículo
    public function attachDocument(string $id, string $docId): JsonResponse
    {
        $article = KnowledgeBase::findOrFail($id);
        $article->documents()->syncWithoutDetaching([$docId]);

        return $this->success(
            $article->fresh('documents:knowledge_base_documents.id,titulo,es_plantilla,activo'),
            'Documento adjuntado'
        );
    }

    // DELETE /knowledge-base/{id}/documents/{docId}  — desadjuntar documento del artículo
    public function detachDocument(string $id, string $docId): JsonResponse
    {
        $article = KnowledgeBase::findOrFail($id);
        $article->documents()->detach($docId);

        return $this->success(null, 'Documento desvinculado');
    }
}
