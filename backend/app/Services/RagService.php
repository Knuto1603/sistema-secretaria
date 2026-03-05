<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseChunk;
use App\Models\KnowledgeBaseDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RagService
{
    private int $maxChunks;

    public function __construct()
    {
        $this->maxChunks = config('chatbot.context_chunks', 5);
    }

    /**
     * Busca contexto relevante para una pregunta.
     * Retorna artículos KB, fragmentos de documentos y plantillas relacionadas.
     */
    public function search(string $query): array
    {
        $articles  = $this->searchArticles($query);
        $chunks    = $this->searchChunks($query);
        $templates = $this->findRelevantTemplates($articles);
        $related   = $this->getRelatedArticles($articles);

        return compact('articles', 'chunks', 'templates', 'related');
    }

    /**
     * Construye el bloque de CONTEXTO para el prompt del LLM.
     */
    public function buildContextBlock(array $ragResult): string
    {
        $blocks = [];

        foreach ($ragResult['articles'] as $article) {
            $tags = implode(', ', $article->tags ?? []);
            $block  = "[ARTÍCULO KB - ID:{$article->id}]\n";
            $block .= "TIPO: {$article->tipo} | CATEGORÍA: {$article->categoria}\n";
            $block .= "TÍTULO: {$article->titulo}\n";
            if ($tags) {
                $block .= "TEMAS: {$tags}\n";
            }
            $block .= "CONTENIDO:\n{$article->contenido}";
            $blocks[] = $block;
        }

        foreach ($ragResult['chunks'] as $chunk) {
            $docTitle = $chunk->document->titulo ?? 'Documento oficial';
            $block    = "[DOCUMENTO OFICIAL - DOC_ID:{$chunk->document_id}]\n";
            $block   .= "FUENTE: {$docTitle}\n";
            $block   .= "FRAGMENTO:\n{$chunk->contenido}";
            $blocks[] = $block;
        }

        foreach ($ragResult['templates'] as $tmpl) {
            $blocks[] = "[PLANTILLA DISPONIBLE - DOC_ID:{$tmpl->id}]\n"
                      . "NOMBRE: {$tmpl->titulo}\n"
                      . "DESCRIPCIÓN: " . ($tmpl->descripcion ?? 'Formulario descargable para el proceso.');
        }

        foreach ($ragResult['related'] as $rel) {
            $blocks[] = "[PROCESO RELACIONADO - ID:{$rel->id}]\n"
                      . "TÍTULO: {$rel->titulo} | TIPO: {$rel->tipo}";
        }

        return empty($blocks)
            ? ''
            : "=== CONTEXTO DISPONIBLE ===\n\n" . implode("\n\n---\n\n", $blocks);
    }

    private function searchArticles(string $query): Collection
    {
        $results = collect();

        // 1. FULLTEXT search
        try {
            $ft = KnowledgeBase::activos()
                ->selectRaw(
                    '*, MATCH(titulo, contenido) AGAINST(? IN BOOLEAN MODE) AS score',
                    [$query]
                )
                ->whereRaw('MATCH(titulo, contenido) AGAINST(? IN BOOLEAN MODE)', [$query])
                ->orderByDesc('score')
                ->limit($this->maxChunks)
                ->get();
            $results = $results->merge($ft);
        } catch (\Exception) {
            // FULLTEXT puede fallar con palabras muy cortas
        }

        // 2. LIKE search como complemento (solo si FULLTEXT no encontró nada)
        if ($results->count() < 1) {
            $stopwords = ['que', 'como', 'cual', 'para', 'una', 'los', 'las',
                          'del', 'con', 'por', 'hay', 'tengo', 'puedo', 'sobre',
                          'este', 'esta', 'esto', 'mas', 'toca', 'aula', 'curso'];
            $words = collect(explode(' ', mb_strtolower($query)))
                ->filter(fn($w) => mb_strlen($w) >= 5 && !in_array($w, $stopwords))
                ->take(3);

            if ($words->isNotEmpty()) {
                $like = KnowledgeBase::activos()
                    ->where(function ($q) use ($words) {
                        foreach ($words as $word) {
                            $q->orWhere('titulo', 'LIKE', "%{$word}%")
                              ->orWhere('contenido', 'LIKE', "%{$word}%");
                        }
                    })
                    ->whereNotIn('id', $results->pluck('id'))
                    ->orderBy('orden')
                    ->limit(3)
                    ->get();

                $results = $results->merge($like);
            }
        }

        return $results->unique('id')->take($this->maxChunks);
    }

    private function searchChunks(string $query): Collection
    {
        try {
            return KnowledgeBaseChunk::selectRaw(
                    '*, MATCH(contenido) AGAINST(? IN BOOLEAN MODE) AS score',
                    [$query]
                )
                ->whereRaw('MATCH(contenido) AGAINST(? IN BOOLEAN MODE)', [$query])
                ->with('document')
                ->whereHas('document', function ($q) {
                    $q->where('activo', true)
                      ->where('es_plantilla', false)
                      ->whereHas('knowledgeBases'); // Solo documentos asociados a al menos un artículo KB
                })
                ->orderByDesc('score')
                ->limit(3)
                ->get();
        } catch (\Exception) {
            return collect();
        }
    }

    private function findRelevantTemplates(Collection $articles): Collection
    {
        if ($articles->isEmpty()) {
            return collect();
        }

        $articleIds = $articles->pluck('id');

        return KnowledgeBaseDocument::where('es_plantilla', true)
            ->where('activo', true)
            ->whereHas('knowledgeBases', fn($q) => $q->whereIn('id', $articleIds))
            ->get();
    }

    private function getRelatedArticles(Collection $articles): Collection
    {
        if ($articles->isEmpty()) {
            return collect();
        }

        $ids = $articles->pluck('id')->toArray();

        // Envolver ambas condiciones en where() para que el whereNotIn aplique a todo
        return KnowledgeBase::activos()
            ->where(function ($q) use ($ids) {
                $q->whereHas('referenciadoPor', fn($r) => $r->whereIn('source_id', $ids))
                  ->orWhereHas('relacionados', fn($r) => $r->whereIn('target_id', $ids));
            })
            ->whereNotIn('id', $ids)
            ->limit(3)
            ->get();
    }
}
