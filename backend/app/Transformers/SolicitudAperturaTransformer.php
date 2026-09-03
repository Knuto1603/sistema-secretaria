<?php

namespace App\Transformers;

use App\DTOs\SolicitudApertura\SolicitudAperturaResponseDTO;
use App\Models\SolicitudAperturaCurso;
use Illuminate\Support\Collection;

class SolicitudAperturaTransformer
{
    public function toDTO(SolicitudAperturaCurso $model): SolicitudAperturaResponseDTO
    {
        return new SolicitudAperturaResponseDTO(
            id: $model->id,
            user_id: $model->user_id,
            curso_id: $model->curso_id,
            periodo_id: $model->periodo_id,
            escuela_id: $model->escuela_id,
            tipo: $model->tipo,
            programacion_referencia_id: $model->programacion_referencia_id,
            motivo: $model->motivo,
            firma_digital_path: $model->firma_digital_path,
            estado: $model->estado,
            observaciones_admin: $model->observaciones_admin,
            user: $model->user ? [
                'id' => $model->user->id,
                'name' => $model->user->name,
                'codigo_universitario' => $model->user->codigo_universitario,
                'escuela' => $model->user->escuela?->nombre_corto,
                'anio_ingreso' => $model->user->anio_ingreso,
            ] : null,
            curso: $model->curso ? [
                'id' => $model->curso->id,
                'codigo' => $model->curso->codigo,
                'nombre' => $model->curso->nombre,
            ] : null,
            periodo: $model->periodo ? [
                'id' => $model->periodo->id,
                'nombre' => $model->periodo->nombre,
            ] : null,
            escuela: $model->escuela ? [
                'id' => $model->escuela->id,
                'nombre' => $model->escuela->nombre,
                'nombre_corto' => $model->escuela->nombre_corto,
            ] : null,
            programacion_referencia: $model->programacionReferencia ? [
                'id' => $model->programacionReferencia->id,
                'seccion' => $model->programacionReferencia->seccion,
                'grupo' => $model->programacionReferencia->grupoHorario?->nombre ?? $model->programacionReferencia->grupo,
            ] : null,
            created_at: $model->created_at->toISOString(),
            updated_at: $model->updated_at->toISOString()
        );
    }

    public function toArray(SolicitudAperturaCurso $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
