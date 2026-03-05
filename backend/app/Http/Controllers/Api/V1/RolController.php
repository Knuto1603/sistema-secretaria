<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    public function __construct(
        protected UserRepositoryInterface $repository
    ) {}

    /**
     * Lista todos los roles
     */
    public function index(): JsonResponse
    {
        $roles = $this->repository->getAllRoles();

        $data = $roles->map(function ($rol) {
            return [
                'id' => $rol->id,
                'name' => $rol->name,
                'guard_name' => $rol->guard_name,
                'permissions' => $rol->permissions->pluck('name')->toArray(),
                'created_at' => $rol->created_at->toISOString(),
            ];
        })->toArray();

        return $this->success($data, 'Lista de roles');
    }

    /**
     * Obtiene un rol con sus permisos
     */
    public function show(int $id): JsonResponse
    {
        $rol = \Spatie\Permission\Models\Role::with('permissions')->find($id);

        if (!$rol) {
            return $this->notFound('Rol no encontrado');
        }

        $data = [
            'id' => $rol->id,
            'name' => $rol->name,
            'guard_name' => $rol->guard_name,
            'permissions' => $rol->permissions->pluck('name')->toArray(),
            'created_at' => $rol->created_at->toISOString(),
        ];

        return $this->success($data);
    }
}
