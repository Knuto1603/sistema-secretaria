<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    use HasUuids;

    protected $fillable = ['nombre_completo'];

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class);
    }
}
