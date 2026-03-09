<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaPrefijo extends Model
{
    use HasUuids;

    protected $table = 'area_prefijos';

    protected $fillable = ['area_id', 'prefijo'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
