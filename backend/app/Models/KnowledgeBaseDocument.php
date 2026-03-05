<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseDocument extends Model
{
    use HasUuids;

    protected $table = 'knowledge_base_documents';

    protected $fillable = [
        'titulo',
        'descripcion',
        'filename',
        'original_filename',
        'mime_type',
        'size_bytes',
        'es_plantilla',
        'extracted_text',
        'procesado',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_plantilla' => 'boolean',
            'procesado'    => 'boolean',
            'activo'       => 'boolean',
        ];
    }

    /** Artículos KB a los que pertenece este documento — muchos a muchos */
    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeBase::class,
            'knowledge_base_article_documents',
            'document_id',
            'knowledge_base_id'
        )->withTimestamps();
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeBaseChunk::class, 'document_id');
    }

    public function getStoragePath(): string
    {
        $folder = $this->es_plantilla ? 'plantillas' : 'docs';
        return "knowledge-base/{$folder}/{$this->filename}";
    }
}
