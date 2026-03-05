<?php

namespace App\Repositories\Contracts;

use App\Models\Periodo;
use Illuminate\Database\Eloquent\Collection;

interface PeriodoRepositoryInterface
{
    public function all(): Collection;

    public function find(string $id): ?Periodo;

    public function findActive(): ?Periodo;

    public function getActiveId(): ?string;

    public function create(array $data): Periodo;

    public function update(string $id, array $data): ?Periodo;

    public function delete(string $id): bool;

    public function setActive(string $id): ?Periodo;

    public function deactivate(string $id): ?Periodo;
}
