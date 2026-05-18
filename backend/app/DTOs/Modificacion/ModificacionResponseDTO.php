<?php

namespace App\DTOs\Modificacion;

class ModificacionResponseDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $tipo,
        public readonly string  $estado,
        public readonly string  $motivo,
        public readonly array   $datos_anteriores,
        public readonly array   $datos_nuevos,
        public readonly ?array  $secciones_origen_ids,
        public readonly array   $periodo,
        public readonly ?array  $programacion,
        public readonly array   $usuario,
        public readonly string  $created_at,
    ) {}
}
