<?php

namespace App\Services;

use App\DTOs\Auth\AuthenticatedUserDTO;
use App\DTOs\Auth\LoginAdminDTO;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\LoginResponseDTO;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * Login para administrativos y developer (por username)
     */
    public function loginAdmin(LoginAdminDTO $dto): LoginResponseDTO
    {
        $user = $this->userRepository->findByUsername($dto->username);

        // Verificar que el usuario existe
        if (!$user) {
            throw ValidationException::withMessages([
                'username' => ['El usuario no existe.'],
            ]);
        }

        // Verificar que es administrativo o developer
        if (!$user->usesUsernameAuth()) {
            throw ValidationException::withMessages([
                'username' => ['Este usuario no puede iniciar sesión con username.'],
            ]);
        }

        // Verificar contraseña
        if (!Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña es incorrecta.'],
            ]);
        }

        $token = $user->createToken($dto->device_name)->plainTextToken;

        return new LoginResponseDTO(
            status: 'success',
            access_token: $token,
            token_type: 'Bearer',
            user: AuthenticatedUserDTO::fromUser($user)
        );
    }

    /**
     * Login legacy por email (mantener compatibilidad)
     * @deprecated Usar loginAdmin() para administrativos
     */
    public function login(LoginDTO $dto): LoginResponseDTO
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (!$user || !Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken($dto->device_name)->plainTextToken;

        return new LoginResponseDTO(
            status: 'success',
            access_token: $token,
            token_type: 'Bearer',
            user: AuthenticatedUserDTO::fromUser($user)
        );
    }

    /**
     * Obtiene el usuario autenticado
     */
    public function getAuthenticatedUser(User $user): AuthenticatedUserDTO
    {
        return AuthenticatedUserDTO::fromUser($user);
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
