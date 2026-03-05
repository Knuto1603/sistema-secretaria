<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'role',
        'contenido',
        'tokens_used',
        'context_articles',
        'context_documents',
        'templates_sugeridos',
        'tuvo_contexto',
    ];

    protected function casts(): array
    {
        return [
            'context_articles'   => 'array',
            'context_documents'  => 'array',
            'templates_sugeridos' => 'array',
            'tuvo_contexto'      => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(ChatMessageTopic::class, 'message_id');
    }
}
