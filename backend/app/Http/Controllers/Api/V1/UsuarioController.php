<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Usuario\CreateUsuarioDTO;
use App\DTOs\Usuario\UpdateUsuarioDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\AsignarRolesRequest;
use App\Http\Requests\Usuario\CreateUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(
        protected UsuarioService $service
    ) {}

    /**
     * Lista usuarios administrativos con paginación y filtros
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'rol' => $request->get('rol'),
            'activo' => $request->has('activo') ? filter_var($request->get('activo'), FILTER_VALIDATE_BOOLEAN) : null,
        ];

        $perPage = (int) $request->get('per_page', 15);

        $result = $this->service->paginate($filters, $perPage);

        return $this->success($result, 'Lista de usuarios administrativos');
    }

    /**
     * Obtiene un usuario por ID
     */
    public function show(string $id): JsonResponse
    {
        $usuario = $this->service->getById($id);

        if (!$usuario) {
            return $this->notFound('Usuario no encontrado');
        }

        return $this->success($usuario);
    }

    /**
     * Crea un nuevo usuario administrativo
     */
    public function store(CreateUsuarioRequest $request): JsonResponse
    {
        $dto = CreateUsuarioDTO::fromRequest($request->validated());
        $usuario = $this->service->create($dto);

        return $this->created($usuario, 'Usuario creado exitosamente');
    }

    /**
     * Actualiza un usuario administrativo
     */
    public function update(UpdateUsuarioRequest $request, string $id): JsonResponse
    {
        // Verificar que no sea developer intentando ser modificado por no-developer
        $usuario = $this->service->getById($id);
        if (!$usuario) {
            return $this->notFound('Usuario no encontrado');
        }

        if ($usuario['tipo_usuario'] === 'developer' && !auth()->user()->isDeveloper()) {
            return $this->forbidden('No tiene permisos para modificar este usuario');
        }

        $dto = UpdateUsuarioDTO::fromRequest($request->validated());
        $result = $this->service->update($id, $dto);

        if (!$result) {
            return $this->notFound('Usuario no encontrado');
        }

        return $this->success($result, 'Usuario actualizado exitosamente');
    }

    /**
     * Elimina (desactiva) un usuario
     */
    public function destroy(string $id): JsonResponse
    {
        // Verificar que no se intente eliminar a sí mismo
        if ($id === auth()->id()) {
            return $this->error('No puede eliminarse a sí mismo', 400);
        }

        // Verificar que no sea developer
        $usuario = $this->service->getById($id);
        if (!$usuario) {
            return $this->notFound('Usuario no encontrado');
        }

        if ($usuario['tipo_usuario'] === 'developer') {
            return $this->forbidden('No se puede eliminar un usuario developer');
        }

        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->notFound('Usuario no encontrado');
        }

        return $this->success(null, 'Usuario eliminado exitosamente');
    }

    /**
     * Activa o desactiva un usuario
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'activo' => 'required|boolean'
        ]);

        // Verificar que no se intente desactivar a sí mismo
        if ($id === auth()->id() && !$request->activo) {
            return $this->error('No puede desactivarse a sí mismo', 400);
        }

        // Verificar que no sea developer
        $usuario = $this->service->getById($id);
        if (!$usuario) {
            return $this->notFound('Usuario no encontrado');
        }

        if ($usuario['tipo_usuario'] === 'developer' && !auth()->user()->isDeveloper()) {
            return $this->forbidden('No tiene permisos para modificar este usuario');
        }

        $result = $this->service->toggleActivo($id, $request->activo);

        if (!$result) {
            return $this->notFound('Usuario no encontrado');
        }

        $message = $request->activo ? 'Usuario activado' : 'Usuario desactivado';
        return $this->success($result, $message);
    }

    /**
     * Asigna roles a un usuario
     */
    public function asignarRoles(AsignarRolesRequest $request, string $id): JsonResponse
    {
        // Verificar que no sea developer modificado por no-developer
        $usuario = $this->service->getById($id);
        if (!$usuario) {
            return $this->notFound('Usuario no encontrado');
        }

        if ($usuario['tipo_usuario'] === 'developer' && !auth()->user()->isDeveloper()) {
            return $this->forbidden('No tiene permisos para modificar roles de este usuario');
        }

        $result = $this->service->asignarRoles($id, $request->roles);

        if (!$result) {
            return $this->notFound('Usuario no encontrado');
        }

        return $this->success($result, 'Roles asignados exitosamente');
    }
}
