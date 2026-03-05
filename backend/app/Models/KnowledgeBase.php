<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeBase extends Model
{
    use HasUuids;

    protected $table = 'knowledge_base';

    protected $fillable = [
        'tipo',
        'titulo',
        'contenido',
        'categoria',
        'tags',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'tags'   => 'array',
            'activo' => 'boolean',
        ];
    }

    /** Documentos adjuntos (PDFs oficiales y plantillas) — muchos a muchos */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeBaseDocument::class,
            'knowledge_base_article_documents',
            'knowledge_base_id',
            'document_id'
        )->withTimestamps();
    }

    /** Artículos relacionados (relaciones salientes) */
    public function relacionados(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeBase::class,
            'knowledge_base_relations',
            'source_id',
            'target_id'
        )->withPivot('tipo')->withTimestamps();
    }

    /** Artículos que apuntan a este (relaciones entrantes) */
    public function referenciadoPor(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeBase::class,
            'knowledge_base_relations',
            'target_id',
            'source_id'
        )->withPivot('tipo')->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
