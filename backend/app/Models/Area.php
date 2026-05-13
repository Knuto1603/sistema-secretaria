<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasUuids;

    protected $fillable = [
        'nombre',
        'titulo_director',
        'director_nombre',
        'director_cargo',
        'nombre_tabla',
    ];

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function prefijos(): HasMany
    {
        return $this->hasMany(AreaPrefijo::class);
    }
}
