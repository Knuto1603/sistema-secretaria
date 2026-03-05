<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function findByUsername(string $username): ?User;

    public function findByCodigoUniversitario(string $codigo): ?User;

    // Métodos CRUD para administrativos
    public function paginateAdministrativos(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function createAdministrativo(array $data): User;

    public function updateAdministrativo(string $id, array $data): ?User;

    public function deleteAdministrativo(string $id): bool;

    public function toggleActivo(string $id, bool $activo): ?User;

    // Métodos para estudiantes
    public function paginateEstudiantes(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function updateEstudiante(string $id, array $data): ?User;

    public function getUltimoOtpEnviado(string $userId): ?\DateTime;

    // Métodos para roles
    public function syncRoles(string $userId, array $roles): ?User;

    public function getAllRoles(): Collection;
}
