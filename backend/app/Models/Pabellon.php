<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pabellon extends Model
{
    use HasUuids;

    protected $table = 'pabellones';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class)->orderBy('nombre');
    }
}
