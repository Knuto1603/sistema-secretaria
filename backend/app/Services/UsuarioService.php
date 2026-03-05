<?php

namespace App\Services;

use App\DTOs\Usuario\CreateUsuarioDTO;
use App\DTOs\Usuario\UpdateUsuarioDTO;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Transformers\UsuarioTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UsuarioService
{
    public function __construct(
        protected UserRepositoryInterface $repository,
        protected UsuarioTransformer $transformer
    ) {}

    /**
     * Lista paginada de usuarios administrativos
     */
    public function paginate(array $filters = [], int $perPage = 15): array
    {
        $paginator = $this->repository->paginateAdministrativos($filters, $perPage);

        return [
            'items' => $this->transformer->usuarioCollection($paginator->getCollection()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    /**
     * Obtiene un usuario por ID
     */
    public function getById(string $id): ?array
    {
        $user = $this->repository->findById($id);

        if (!$user || !in_array($user->tipo_usuario, ['administrativo', 'developer'])) {
            return null;
        }

        return $this->transformer->toUsuarioArray($user);
    }

    /**
     * Crea un nuevo usuario administrativo
     */
    public function create(CreateUsuarioDTO $dto): array
    {
        $user = $this->repository->createAdministrativo($dto->toArray());

        // Asignar roles si se proporcionaron
        if (!empty($dto->roles)) {
            $user->syncRoles($dto->roles);
            $user = $user->fresh()->load('roles');
        }

        return $this->transformer->toUsuarioArray($user);
    }

    /**
     * Actualiza un usuario administrativo
     */
    public function update(string $id, UpdateUsuarioDTO $dto): ?array
    {
        $user = $this->repository->updateAdministrativo($id, $dto->toArray());

        if (!$user) {
            return null;
        }

        // Actualizar roles si se proporcionaron
        if ($dto->roles !== null) {
            $user->syncRoles($dto->roles);
            $user = $user->fresh()->load('roles');
        }

        return $this->transformer->toUsuarioArray($user);
    }

    /**
     * Elimina (desactiva) un usuario
     */
    public function delete(string $id): bool
    {
        return $this->repository->deleteAdministrativo($id);
    }

    /**
     * Activa o desactiva un usuario
     */
    public function toggleActivo(string $id, bool $activo): ?array
    {
        $user = $this->repository->toggleActivo($id, $activo);
        return $user ? $this->transformer->toUsuarioArray($user) : null;
    }

    /**
     * Asigna roles a un usuario
     */
    public function asignarRoles(string $id, array $roles): ?array
    {
        $user = $this->repository->syncRoles($id, $roles);
        return $user ? $this->transformer->toUsuarioArray($user) : null;
    }

    /**
     * Verifica si un username ya está en uso
     */
    public function usernameExists(string $username, ?string $excludeId = null): bool
    {
        $user = $this->repository->findByUsername($username);

        if (!$user) {
            return false;
        }

        // Si se proporciona un ID a excluir, verificar que no sea el mismo usuario
        return $excludeId ? $user->id !== $excludeId : true;
    }

    /**
     * Verifica si un email ya está en uso
     */
    public function emailExists(string $email, ?string $excludeId = null): bool
    {
        $user = $this->repository->findByEmail($email);

        if (!$user) {
            return false;
        }

        return $excludeId ? $user->id !== $excludeId : true;
    }
}
