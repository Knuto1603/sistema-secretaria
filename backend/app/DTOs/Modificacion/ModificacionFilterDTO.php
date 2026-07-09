<?php

namespace App\DTOs\Modificacion;

class ModificacionFilterDTO
{
    public function __construct(
        public readonly ?string $periodo_id   = null,
        public readonly ?string $borrador_id  = null,
        public readonly ?string $tipo         = null,
        public readonly ?string $area_id      = null,
        public readonly ?string $estado       = null,
        public readonly ?string $fecha_desde  = null,
        public readonly ?string $fecha_hasta  = null,
        public readonly ?string $search       = null,
        public readonly int     $per_page     = 20,
    ) {}

    public static function fromRequest(array $data): self
    {
        $tiposValidos  = ['cerrar_curso','abrir_seccion','cambio_aula','cambio_grupo','cambio_aula_y_grupo','unificacion_secciones'];
        $estadosValidos = ['pendiente', 'documentado'];

        return new self(
            periodo_id:  $data['periodo_id']  ?? null,
            borrador_id: $data['borrador_id'] ?? null,
            tipo:        isset($data['tipo']) && in_array($data['tipo'], $tiposValidos) ? $data['tipo'] : null,
            area_id:     $data['area_id'] ?? null,
            estado:      isset($data['estado']) && in_array($data['estado'], $estadosValidos) ? $data['estado'] : null,
            fecha_desde: $data['fecha_desde'] ?? null,
            fecha_hasta: $data['fecha_hasta'] ?? null,
            search:      $data['search'] ?? null,
            per_page:    isset($data['per_page']) ? (int) $data['per_page'] : 20,
        );
    }
}
