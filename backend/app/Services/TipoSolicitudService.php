<?php

namespace App\Services;

use App\DTOs\TipoSolicitud\CreateTipoSolicitudDTO;
use App\DTOs\TipoSolicitud\UpdateTipoSolicitudDTO;
use App\Repositories\Contracts\TipoSolicitudRepositoryInterface;
use App\Transformers\TipoSolicitudTransformer;

class TipoSolicitudService
{
    public function __construct(
        protected TipoSolicitudRepositoryInterface $repository,
        protected TipoSolicitudTransformer $transformer
    ) {}

    public function getAll(): array
    {
        $tipos = $this->repository->all();
        return $this->transformer->collection($tipos);
    }

    public function getById(string $id): ?array
    {
        $tipo = $this->repository->findById($id);
        return $tipo ? $this->transformer->toArray($tipo) : null;
    }

    public function create(CreateTipoSolicitudDTO $dto): array
    {
        $tipo = $this->repository->create($dto->toArray());
        return $this->transformer->toArray($tipo);
    }

    public function update(string $id, UpdateTipoSolicitudDTO $dto): ?array
    {
        $tipo = $this->repository->update($id, $dto->toArray());
        return $tipo ? $this->transformer->toArray($tipo) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function toggleActivo(string $id, bool $activo): ?array
    {
        $tipo = $this->repository->update($id, ['activo' => $activo]);
        return $tipo ? $this->transformer->toArray($tipo) : null;
    }
}
