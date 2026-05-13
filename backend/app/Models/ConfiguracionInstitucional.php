<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionInstitucional extends Model
{
    protected $table = 'configuracion_institucional';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    public static function get(string $clave, string $default = ''): string
    {
        return static::find($clave)?->valor ?? $default;
    }

    public static function getAll(): array
    {
        return static::all()->pluck('valor', 'clave')->toArray();
    }
}
