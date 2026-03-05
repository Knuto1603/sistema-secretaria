<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\Auth\AuthenticatedUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\EstablecerPasswordRequest;
use App\Http\Requests\Estudiante\LoginEstudianteRequest;
use App\Http\Requests\Estudiante\SolicitarOtpRequest;
use App\Http\Requests\Estudiante\VerificarCodigoRequest;
use App\Http\Requests\Estudiante\VerificarOtpRequest;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\OtpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controlador de autenticación para estudiantes
 *
 * Recursos REST:
 * - verificacion: Estado del código universitario
 * - otp: Código de verificación temporal
 * - password: Contraseña del estudiante
 * - sesion: Token de acceso (login)
 * - recuperacion: Proceso de recuperación de contraseña
 */
class StudentAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected OtpService $otpService
    ) {}

    // =========================================================================
    // RECURSO: VERIFICACION
    // =========================================================================

    /**
     * POST /auth/estudiante/verificacion
     *
     * Verifica si un código universitario existe y su estado de activación.
     */
    public function verificarCodigo(VerificarCodigoRequest $request): JsonResponse
    {
        $codigo = $request->validated()['codigo'];
        $user = $this->userRepository->findByCodigoUniversitario($codigo);

        if (!$user) {
            return $this->error('Código universitario no registrado en el sistema.', 404);
        }

        if (!$user->isEstudiante()) {
            return $this->error('Este código no corresponde a un estudiante.', 400);
        }

        return $this->success([
            'codigo' => $codigo,
            'nombre' => $user->name,
            'tiene_password' => $user->hasPasswordSet(),
            'email_institucional' => $this->maskEmail($user->getEmailInstitucional()),
        ], 'Código verificado correctamente.');
    }

    // =========================================================================
    // RECURSO: OTP
    // =========================================================================

    /**
     * POST /auth/estudiante/otp
     *
     * Crea y envía un nuevo código OTP al correo institucional.
     */
    public function solicitarOtp(SolicitarOtpRequest $request): JsonResponse
    {
        $codigo = $request->validated()['codigo'];
        $user = $this->userRepository->findByCodigoUniversitario($codigo);

        if (!$user || !$user->isEstudiante()) {
            return $this->error('Código universitario no válido.', 404);
        }

        if (!$this->otpService->canRequestNew($user, 'password_setup')) {
            $seconds = $this->otpService->secondsUntilCanRequest($user, 'password_setup');
            return $this->error(
                "Debes esperar {$seconds} segundos antes de solicitar otro código.",
                429
            );
        }

        $this->otpService->send($user, 'password_setup');

        return $this->success([
            'email_enviado' => $this->maskEmail($user->getEmailInstitucional()),
            'expira_en_minutos' => 10,
        ], 'Código de verificación enviado a tu correo institucional.');
    }

    /**
     * PATCH /auth/estudiante/otp
     *
     * Valida un código OTP y genera token temporal para establecer contraseña.
     */
    public function verificarOtp(VerificarOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->findByCodigoUniversitario($data['codigo']);

        if (!$user || !$user->isEstudiante()) {
            return $this->error('Código universitario no válido.', 404);
        }

        if (!$this->otpService->verify($user, $data['otp'], 'password_setup')) {
            return $this->error('El código de verificación es incorrecto o ha expirado.', 400);
        }

        $tempToken = Str::random(64);
        Cache::put("password_setup:{$user->id}", $tempToken, now()->addMinutes(10));

        return $this->success([
            'temp_token' => $tempToken,
            'expira_en_minutos' => 10,
        ], 'Código verificado correctamente. Ahora puedes establecer tu contraseña.');
    }

    // =========================================================================
    // RECURSO: PASSWORD
    // =========================================================================

    /**
     * POST /auth/estudiante/password
     *
     * Establece la contraseña por primera vez (activación de cuenta).
     */
    public function establecerPassword(EstablecerPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->findByCodigoUniversitario($data['codigo']);

        if (!$user || !$user->isEstudiante()) {
            return $this->error('Código universitario no válido.', 404);
        }

        $storedToken = Cache::get("password_setup:{$user->id}");
        if (!$storedToken || $storedToken !== $data['temp_token']) {
            return $this->error(
                'El token de verificación es inválido o ha expirado. Solicita un nuevo código OTP.',
                400
            );
        }

        $user->password = Hash::make($data['password']);
        $user->password_set_at = now();
        $user->save();

        // Asignar escuela y año de ingreso desde el código universitario
        $user->asignarDatosDesdeCodigoUniversitario();

        Cache::forget("password_setup:{$user->id}");

        $token = $user->createToken('angular_web')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => AuthenticatedUserDTO::fromUser($user)->toArray(),
        ], 'Contraseña establecida correctamente. Has iniciado sesión.');
    }

    /**
     * PUT /auth/estudiante/password
     *
     * Restablece la contraseña (usuario ya activado, proceso de recuperación).
     */
    public function restablecerPassword(EstablecerPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->findByCodigoUniversitario($data['codigo']);

        if (!$user || !$user->isEstudiante()) {
            return $this->error('Código universitario no válido.', 404);
        }

        $storedToken = Cache::get("password_recovery:{$user->id}");
        if (!$storedToken || $storedToken !== $data['temp_token']) {
            return $this->error('El token de verificación es inválido o ha expirado.', 400);
        }

        $user->password = Hash::make($data['password']);
        $user->password_set_at = now();
        $user->save();

        // Revocar tokens anteriores por seguridad
        $user->tokens()->delete();

        Cache::forget("password_recovery:{$user->id}");

        $token = $user->createToken('angular_web')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => AuthenticatedUserDTO::fromUser($user)->toArray(),
        ], 'Contraseña restablecida correctamente. Has iniciado sesión.');
    }

    // =========================================================================
    // RECURSO: SESION
    // =========================================================================

    /**
     * POST /auth/estudiante/sesion
     *
     * Crea una nueva sesión (login con código + contraseña).
     */
    public function login(LoginEstudianteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->findByCodigoUniversitario($data['codigo']);

        if (!$user) {
            return $this->error('Código universitario no registrado.', 401);
        }

        if (!$user->isEstudiante()) {
            return $this->error('Este código no corresponde a un estudiante.', 401);
        }

        if (!$user->hasPasswordSet()) {
            return $this->error('Debes activar tu cuenta primero.', 401, [
                'requires_activation' => true,
            ]);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return $this->error('La contraseña es incorrecta.', 401);
        }

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => AuthenticatedUserDTO::fromUser($user)->toArray(),
        ], 'Inicio de sesión exitoso.');
    }

    // =========================================================================
    // RECURSO: RECUPERACION
    // =========================================================================

    /**
     * POST /auth/estudiante/recuperacion
     *
     * Inicia el proceso de recuperación de contraseña enviando OTP.
     */
    public function solicitarRecuperacion(SolicitarOtpRequest $request): JsonResponse
    {
        $codigo = $request->validated()['codigo'];
        $user = $this->userRepository->findByCodigoUniversitario($codigo);

        if (!$user || !$user->isEstudiante()) {
            // Por seguridad, no revelar si el código existe
            return $this->success([
                'email_enviado' => $this->maskEmail($codigo . '@alumnos.unp.edu.pe'),
            ], 'Si el código existe, recibirás un correo con instrucciones.');
        }

        if (!$user->hasPasswordSet()) {
            return $this->error('Tu cuenta no ha sido activada. Usa la opción de activación.', 400);
        }

        if (!$this->otpService->canRequestNew($user, 'password_recovery')) {
            $seconds = $this->otpService->secondsUntilCanRequest($user, 'password_recovery');
            return $this->error(
                "Debes esperar {$seconds} segundos antes de solicitar otro código.",
                429
            );
        }

        $this->otpService->send($user, 'password_recovery');

        return $this->success([
            'email_enviado' => $this->maskEmail($user->getEmailInstitucional()),
            'expira_en_minutos' => 10,
        ], 'Código de recuperación enviado a tu correo institucional.');
    }

    /**
     * PATCH /auth/estudiante/recuperacion
     *
     * Valida el OTP de recuperación y genera token temporal.
     */
    public function verificarRecuperacion(VerificarOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->findByCodigoUniversitario($data['codigo']);

        if (!$user || !$user->isEstudiante()) {
            return $this->error('Código universitario no válido.', 404);
        }

        if (!$this->otpService->verify($user, $data['otp'], 'password_recovery')) {
            return $this->error('El código de verificación es incorrecto o ha expirado.', 400);
        }

        $tempToken = Str::random(64);
        Cache::put("password_recovery:{$user->id}", $tempToken, now()->addMinutes(10));

        return $this->success([
            'temp_token' => $tempToken,
            'expira_en_minutos' => 10,
        ], 'Código verificado. Ahora puedes establecer una nueva contraseña.');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Enmascara un email para mostrar parcialmente.
     * Ejemplo: 2024000001@alumnos.unp.edu.pe → 2*******01@a***s.pe
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $maskedLocal = strlen($local) > 2
            ? $local[0] . str_repeat('*', strlen($local) - 2) . $local[strlen($local) - 1]
            : str_repeat('*', strlen($local));

        $domainParts = explode('.', $domain);
        $maskedDomain = $domainParts[0][0] . str_repeat('*', strlen($domainParts[0]) - 1);

        return $maskedLocal . '@' . $maskedDomain . '.' . end($domainParts);
    }
}
