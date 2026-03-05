<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Transformers\UsuarioTransformer;

class EstudianteService
{
    public function __construct(
        protected UserRepositoryInterface $repository,
        protected UsuarioTransformer $transformer,
        protected OtpService $otpService
    ) {}

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
