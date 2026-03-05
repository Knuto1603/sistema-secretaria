<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseChunk extends Model
{
    use HasUuids;

    protected $table = 'knowledge_base_chunks';

    protected $fillable = [
        'document_id',
        'chunk_index',
        'contenido',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseDocument::class, 'document_id');
    }
}
