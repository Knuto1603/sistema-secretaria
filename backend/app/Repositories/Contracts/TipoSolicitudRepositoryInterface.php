<?php

namespace App\Repositories\Contracts;

use App\Models\TipoSolicitud;
use Illuminate\Support\Collection;

interface TipoSolicitudRepositoryInterface
{
    public function all(): Collection;

    public function findById(string $id): ?TipoSolicitud;

    public function findByCode(string $codigo): ?TipoSolicitud;

    public function create(array $data): TipoSolicitud;

    public function update(string $id, array $data): ?TipoSolicitud;

    public function delete(string $id): bool;
}
