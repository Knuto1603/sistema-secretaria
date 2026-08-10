<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\Auth\LoginAdminDTO;
use App\DTOs\Auth\LoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginAdminRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Login para administrativos y developer (por username)
     * POST /auth/admin/login
     */
    public function loginAdmin(LoginAdminRequest $request): JsonResponse
    {
        $dto = LoginAdminDTO::fromRequest($request->validated());
        $response = $this->authService->loginAdmin($dto);

        return $this->success($response->toArray(), 'Inicio de sesión exitoso');
    }

    /**
     * Login legacy por email (mantener compatibilidad temporal)
     * POST /login
     * @deprecated Usar loginAdmin() para administrativos
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginDTO::fromRequest($request->validated());
        $response = $this->authService->login($dto);

        return $this->success($response->toArray(), 'Inicio de sesión exitoso');
    }

    /**
     * Obtiene el usuario autenticado actual
     * GET /me
     */
    public function me(Request $request): JsonResponse
    {
        $userDTO = $this->authService->getAuthenticatedUser($request->user());

        return $this->success($userDTO->toArray(), 'Usuario autenticado');
    }

    /**
     * Cierra la sesión actual
     * POST /logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Sesión cerrada exitosamente');
    }

    /**
     * Cambia la contraseña del usuario autenticado
     * PATCH /me/password
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_actual'        => ['required', 'string'],
            'password_nuevo'         => ['required', 'string', 'min:8', 'confirmed'],
            'password_nuevo_confirmation' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->password_actual, $user->password)) {
            return $this->error('La contraseña actual es incorrecta.', 422);
        }

        $user->update([
            'password' => Hash::make($request->password_nuevo),
            'must_change_password' => false,
        ]);

        return $this->success(null, 'Contraseña actualizada correctamente.');
    }
}
