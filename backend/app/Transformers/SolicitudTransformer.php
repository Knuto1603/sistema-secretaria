<?php

namespace App\Transformers;

use App\DTOs\Solicitud\SolicitudResponseDTO;
use App\Models\Solicitud;
use Illuminate\Support\Collection;

class SolicitudTransformer
{
    public function toDTO(Solicitud $model): SolicitudResponseDTO
    {
        return new SolicitudResponseDTO(
            id: $model->id,
            user_id: $model->user_id,
            tipo_solicitud_id: $model->tipo_solicitud_id,
            programacion_id: $model->programacion_id,
            motivo: $model->motivo,
            estado: $model->estado,
            firma_digital_path: $model->firma_digital_path,
            archivo_sustento_path: $model->archivo_sustento_path,
            archivo_sustento_nombre: $model->archivo_sustento_nombre,
            asignado_a: $model->asignado_a,
            observaciones_admin: $model->observaciones_admin,
            fuera_de_plan: (bool) $model->fuera_de_plan,
            metadatos: $model->metadatos,
            user: $model->user ? [
                'id' => $model->user->id,
                'name' => $model->user->name,
                'email' => $model->user->email,
                'codigo_universitario' => $model->user->codigo_universitario,
                'escuela' => $model->user->escuela?->nombre_corto,
                'anio_ingreso' => $model->user->anio_ingreso,
            ] : null,
            tipo_solicitud: $model->tipoSolicitud ? [
                'id' => $model->tipoSolicitud->id,
                'codigo' => $model->tipoSolicitud->codigo,
                'nombre' => $model->tipoSolicitud->nombre,
            ] : null,
            programacion: $model->programacion ? [
                'id'               => $model->programacion->id,
                'clave'            => $model->programacion->clave,
                'grupo'            => $model->programacion->grupo,
                'seccion'          => $model->programacion->seccion,
                'capacidad'        => $model->programacion->capacidad,
                'n_inscritos'      => $model->programacion->n_inscritos,
                'escuela_programada' => $model->programacion->escuelaProgramada ? [
                    'id'          => $model->programacion->escuelaProgramada->id,
                    'nombre'      => $model->programacion->escuelaProgramada->nombre,
                    'nombre_corto'=> $model->programacion->escuelaProgramada->nombre_corto,
                ] : null,
                'curso' => $model->programacion->curso ? [
                    'id'     => $model->programacion->curso->id,
                    'nombre' => $model->programacion->curso->nombre,
                    'codigo' => $model->programacion->curso->codigo,
                ] : null,
                'docente' => $model->programacion->docente ? [
                    'nombre' => $model->programacion->docente->nombre_completo,
                ] : null,
                'aula' => $model->programacion->aula ? [
                    'nombre' => $model->programacion->aula->nombre,
                ] : null,
            ] : null,
            created_at: $model->created_at->toISOString(),
            updated_at: $model->updated_at->toISOString()
        );
    }

    public function toArray(Solicitud $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
