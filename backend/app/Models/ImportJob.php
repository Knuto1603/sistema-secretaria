<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImportJob extends Model
{
    use HasUuids;

    protected $table = 'import_jobs';

    protected $fillable = [
        'tipo',
        'estado',
        'resultado',
        'error_mensaje',
    ];

    protected $casts = [
        'resultado' => 'array',
    ];
}
