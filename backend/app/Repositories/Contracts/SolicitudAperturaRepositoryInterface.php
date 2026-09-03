<?php

namespace App\Repositories\Contracts;

use App\Models\SolicitudAperturaCurso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SolicitudAperturaRepositoryInterface
{
    public function create(array $data): SolicitudAperturaCurso;

    public function update(string $id, array $data): ?SolicitudAperturaCurso;

    public function findById(string $id): ?SolicitudAperturaCurso;

    public function findByUserId(string $userId, int $perPage = 10): LengthAwarePaginator;

    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Todas las solicitudes que matchean los filtros (sin paginar), para agrupar por curso
     * y calcular indicadores de prioridad en IndicadoresAperturaService.
     */
    public function getAllForAgrupado(array $filters = []): Collection;

    /**
     * Verifica si existe una solicitud activa (pendiente/en_revision/aprobada) del usuario
     * para el mismo curso dentro del periodo indicado, sin importar el tipo.
     */
    public function existsSolicitudActivaParaCurso(string $userId, string $cursoId, string $periodoId): bool;
}
