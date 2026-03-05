<?php

namespace App\Services;

use App\DTOs\Periodo\CreatePeriodoDTO;
use App\DTOs\Periodo\UpdatePeriodoDTO;
use App\Repositories\Contracts\PeriodoRepositoryInterface;
use App\Transformers\PeriodoTransformer;

class PeriodoService
{
    public function __construct(
        protected PeriodoRepositoryInterface $repository,
        protected PeriodoTransformer $transformer
    ) {}

    public function getAll(): array
    {
        $periodos = $this->repository->all();
        return $this->transformer->collection($periodos);
    }

    public function getById(string $id): ?array
    {
        $periodo = $this->repository->find($id);
        return $periodo ? $this->transformer->toArray($periodo) : null;
    }

    public function getActive(): ?array
    {
        $periodo = $this->repository->findActive();
        return $periodo ? $this->transformer->toArray($periodo) : null;
    }

    public function getActiveId(): ?string
    {
        return $this->repository->getActiveId();
    }

    public function create(CreatePeriodoDTO $dto): array
    {
        $periodo = $this->repository->create($dto->toArray());
        return $this->transformer->toArray($periodo);
    }

    public function update(string $id, UpdatePeriodoDTO $dto): ?array
    {
        $periodo = $this->repository->update($id, $dto->toArray());
        return $periodo ? $this->transformer->toArray($periodo) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function setActive(string $id): ?array
    {
        $periodo = $this->repository->setActive($id);
        return $periodo ? $this->transformer->toArray($periodo) : null;
    }

    public function deactivate(string $id): ?array
    {
        $periodo = $this->repository->deactivate($id);
        return $periodo ? $this->transformer->toArray($periodo) : null;
    }
}
