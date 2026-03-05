<?php

namespace App\Transformers;

use App\DTOs\Usuario\EstudianteResponseDTO;
use App\DTOs\Usuario\UsuarioResponseDTO;
use App\Models\User;
use Illuminate\Support\Collection;

class UsuarioTransformer
{
    /**
     * Transforma un usuario administrativo a DTO
     */
    public function toUsuarioDTO(User $model): UsuarioResponseDTO
    {
        return new UsuarioResponseDTO(
            id: $model->id,
            name: $model->name,
            username: $model->username ?? '',
            email: $model->email,
            tipo_usuario: $model->tipo_usuario,
            activo: $model->activo ?? true,
            roles: $model->getRoleNames(),
            created_at: $model->created_at->toISOString()
        );
    }

    /**
     * Transforma un usuario administrativo a array
     */
    public function toUsuarioArray(User $model): array
    {
        return $this->toUsuarioDTO($model)->toArray();
    }

    /**
     * Transforma colección de administrativos
     */
    public function usuarioCollection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toUsuarioArray($m))->toArray();
    }

    /**
     * Transforma un estudiante a DTO
     */
    public function toEstudianteDTO(User $model, ?\DateTime $ultimoOtp = null): EstudianteResponseDTO
    {
        return new EstudianteResponseDTO(
            id: $model->id,
            name: $model->name,
            codigo_universitario: $model->codigo_universitario ?? '',
            email: $model->getEmailInstitucional(),
            escuela: $model->escuela?->nombre_corto,
            anio_ingreso: $model->anio_ingreso,
            cuenta_activada: $model->hasPasswordSet(),
            activo: $model->activo ?? true,
            password_set_at: $model->password_set_at?->toISOString(),
            ultimo_otp_enviado: $ultimoOtp?->format('c'),
            created_at: $model->created_at->toISOString()
        );
    }

    /**
     * Transforma un estudiante a array
     */
    public function toEstudianteArray(User $model, ?\DateTime $ultimoOtp = null): array
    {
        return $this->toEstudianteDTO($model, $ultimoOtp)->toArray();
    }

    /**
     * Transforma colección de estudiantes
     */
    public function estudianteCollection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toEstudianteArray($m))->toArray();
    }
}
