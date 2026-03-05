<?php

namespace App\Repositories\Eloquent;

use App\Models\OtpCode;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        protected User $model
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findById(string $id): ?User
    {
        return $this->model->find($id);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->model->where('username', $username)->first();
    }

    public function findByCodigoUniversitario(string $codigo): ?User
    {
        return $this->model->where('codigo_universitario', $codigo)->first();
    }

    // =============================================
    // MÉTODOS PARA ADMINISTRATIVOS
    // =============================================

    public function paginateAdministrativos(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->whereIn('tipo_usuario', ['administrativo', 'developer'])
            ->with('roles');

        // Filtro de búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por rol
        if (!empty($filters['rol'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['rol']);
            });
        }

        // Filtro por estado activo
        if (isset($filters['activo'])) {
            $query->where('activo', $filters['activo']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function createAdministrativo(array $data): User
    {
        return $this->model->create($data);
    }

    public function updateAdministrativo(string $id, array $data): ?User
    {
        $user = $this->findById($id);

        if (!$user || !in_array($user->tipo_usuario, ['administrativo', 'developer'])) {
            return null;
        }

        $user->update($data);
        return $user->fresh();
    }

    public function deleteAdministrativo(string $id): bool
    {
        $user = $this->findById($id);

        if (!$user || !in_array($user->tipo_usuario, ['administrativo', 'developer'])) {
            return false;
        }

        // Soft delete: desactivar en lugar de eliminar
        $user->update(['activo' => false]);
        return true;
    }

    public function toggleActivo(string $id, bool $activo): ?User
    {
        $user = $this->findById($id);

        if (!$user) {
            return null;
        }

        $user->update(['activo' => $activo]);
        return $user->fresh();
    }

    // =============================================
    // MÉTODOS PARA ESTUDIANTES
    // =============================================

    public function paginateEstudiantes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->where('tipo_usuario', 'estudiante')
            ->with('escuela');

        // Filtro de búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('codigo_universitario', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por escuela (filtra por codigo, no por UUID)
        if (isset($filters['escuela_codigo']) && $filters['escuela_codigo'] !== '') {
            $query->whereHas('escuela', fn($q) => $q->where('codigo', $filters['escuela_codigo']));
        }

        // Filtro por estado de activación (cuenta_activada = tiene password)
        if (isset($filters['cuenta_activada'])) {
            if ($filters['cuenta_activada']) {
                $query->whereNotNull('password_set_at');
            } else {
                $query->whereNull('password_set_at');
            }
        }

        // Filtro por estado activo
        if (isset($filters['activo'])) {
            $query->where('activo', $filters['activo']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function updateEstudiante(string $id, array $data): ?User
    {
        $user = $this->findById($id);

        if (!$user || $user->tipo_usuario !== 'estudiante') {
            return null;
        }

        // No permitir cambiar código universitario
        unset($data['codigo_universitario']);

        $user->update($data);
        return $user->fresh()->load('escuela');
    }

    public function getUltimoOtpEnviado(string $userId): ?\DateTime
    {
        $lastOtp = OtpCode::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastOtp?->created_at;
    }

    // =============================================
    // MÉTODOS PARA ROLES
    // =============================================

    public function syncRoles(string $userId, array $roles): ?User
    {
        $user = $this->findById($id = $userId);

        if (!$user) {
            return null;
        }

        $user->syncRoles($roles);
        return $user->fresh()->load('roles');
    }

    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->orderBy('name')->get();
    }
}
