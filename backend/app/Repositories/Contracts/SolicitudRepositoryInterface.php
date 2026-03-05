<?php

namespace App\Repositories\Contracts;

use App\Models\Solicitud;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface SolicitudRepositoryInterface
{
    public function create(array $data): Solicitud;

    public function update(string $id, array $data): ?Solicitud;

    public function findById(string $id): ?Solicitud;

    public function findByUserId(string $userId, int $perPage = 10): LengthAwarePaginator;

    public function getBaseQuery(): Builder;

    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Verifica si existe una solicitud activa (no rechazada) del usuario para el mismo curso
     */
    public function existsSolicitudActivaParaCurso(string $userId, string $cursoId): bool;
}
