<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Transformers\UsuarioTransformer;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class EstudianteService
{
    public function __construct(
        protected UserRepositoryInterface $repository,
        protected UsuarioTransformer $transformer,
        protected OtpService $otpService
    ) {}

    /**
     * Crea un nuevo estudiante y le asigna el rol Spatie correspondiente.
     * La escuela y año de ingreso se derivan automáticamente del código.
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $codigo = $data['codigo_universitario'];

            $user = User::create([
                'name'                 => strtoupper(trim($data['name'])),
                'tipo_usuario'         => 'estudiante',
                'codigo_universitario' => $codigo,
                'email'                => "{$codigo}@alumnos.unp.edu.pe",
                'activo'               => true,
            ]);

            $user->asignarDatosDesdeCodigoUniversitario();

            $rol = Role::where('name', 'estudiante')->first();
            if ($rol) {
                $user->assignRole($rol);
            }

            $user->load('escuela');
            $ultimoOtp = $this->repository->getUltimoOtpEnviado($user->id);

            return $this->transformer->toEstudianteArray($user, $ultimoOtp);
        });
    }

    /**
     * Lista paginada de estudiantes
     */
    public function paginate(array $filters = [], int $perPage = 15): array
    {
        $paginator = $this->repository->paginateEstudiantes($filters, $perPage);

        $items = $paginator->getCollection()->map(function ($estudiante) {
            $ultimoOtp = $this->repository->getUltimoOtpEnviado($estudiante->id);
            return $this->transformer->toEstudianteArray($estudiante, $ultimoOtp);
        })->toArray();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    /**
     * Obtiene un estudiante por ID
     */
    public function getById(string $id): ?array
    {
        $user = $this->repository->findById($id);

        if (!$user || $user->tipo_usuario !== 'estudiante') {
            return null;
        }

        $user->load('escuela');
        $ultimoOtp = $this->repository->getUltimoOtpEnviado($user->id);

        return $this->transformer->toEstudianteArray($user, $ultimoOtp);
    }

    /**
     * Actualiza datos de un estudiante
     */
    public function update(string $id, array $data): ?array
    {
        $user = $this->repository->updateEstudiante($id, $data);

        if (!$user) {
            return null;
        }

        $ultimoOtp = $this->repository->getUltimoOtpEnviado($user->id);
        return $this->transformer->toEstudianteArray($user, $ultimoOtp);
    }

    /**
     * Activa o desactiva un estudiante
     */
    public function toggleActivo(string $id, bool $activo): ?array
    {
        $user = $this->repository->findById($id);

        if (!$user || $user->tipo_usuario !== 'estudiante') {
            return null;
        }

        $user = $this->repository->toggleActivo($id, $activo);
        $ultimoOtp = $this->repository->getUltimoOtpEnviado($user->id);

        return $this->transformer->toEstudianteArray($user, $ultimoOtp);
    }

    /**
     * Reenvía OTP a un estudiante
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function reenviarOtp(string $id): array
    {
        $user = $this->repository->findById($id);

        if (!$user || $user->tipo_usuario !== 'estudiante') {
            return [
                'success' => false,
                'message' => 'Estudiante no encontrado'
            ];
        }

        // Verificar rate limit: máximo 3 OTPs por hora
        if (!$this->puedeEnviarOtp($user->id)) {
            return [
                'success' => false,
                'message' => 'Se ha alcanzado el límite de reenvíos por hora (máximo 3)'
            ];
        }

        // Enviar OTP
        $otp = $this->otpService->send($user, 'activation');

        return [
            'success' => true,
            'message' => 'OTP enviado exitosamente a ' . $user->getEmailInstitucional(),
            'data' => [
                'email' => $user->getEmailInstitucional(),
                'expires_at' => $otp->expires_at->toISOString()
            ]
        ];
    }

    /**
     * Resetea la activación de la cuenta del estudiante:
     * borra password y password_set_at, invalida todos los OTPs existentes.
     * El alumno deberá solicitar un nuevo OTP y establecer contraseña nuevamente.
     */
    public function resetActivacion(string $id): array
    {
        $user = $this->repository->findById($id);

        if (!$user || $user->tipo_usuario !== 'estudiante') {
            return ['success' => false, 'message' => 'Estudiante no encontrado'];
        }

        if (!$user->hasPasswordSet()) {
            return ['success' => false, 'message' => 'La cuenta de este estudiante aún no ha sido activada'];
        }

        DB::transaction(function () use ($user) {
            // Invalidar todos los OTPs existentes
            $user->otpCodes()->update(['usado' => true]);

            // Quitar password y timestamp de activación usando DB directo
            // para evitar que el cast 'hashed' intente hashear null
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password'        => null,
                    'password_set_at' => null,
                ]);
        });

        // Refrescar el modelo para reflejar los cambios
        $user->refresh();

        $user->load('escuela');
        $ultimoOtp = $this->repository->getUltimoOtpEnviado($user->id);

        return [
            'success' => true,
            'message' => 'Cuenta reseteada. El estudiante deberá solicitar un nuevo OTP.',
            'data'    => $this->transformer->toEstudianteArray($user, $ultimoOtp),
        ];
    }

    /**
     * Verifica si se puede enviar un nuevo OTP (máximo 3 por hora)
     */
    protected function puedeEnviarOtp(string $userId): bool
    {
        $otpsUltimaHora = \App\Models\OtpCode::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $otpsUltimaHora < 3;
    }

    /**
     * Obtiene conteo de OTPs enviados en la última hora
     */
    public function getOtpsEnviadosUltimaHora(string $userId): int
    {
        return \App\Models\OtpCode::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }
}
