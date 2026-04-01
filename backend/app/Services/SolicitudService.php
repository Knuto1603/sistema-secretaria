<?php

namespace App\Services;

use App\DTOs\Solicitud\CreateSolicitudDTO;
use App\Models\Solicitud;
use App\Models\User;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use App\Repositories\Contracts\SolicitudRepositoryInterface;
use App\Repositories\Contracts\TipoSolicitudRepositoryInterface;
use App\Traits\ApiFilterable;
use App\Transformers\SolicitudTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class SolicitudService
{
    use ApiFilterable;

    public function __construct(
        protected SolicitudRepositoryInterface $repository,
        protected TipoSolicitudRepositoryInterface $tipoSolicitudRepository,
        protected ProgramacionRepositoryInterface $programacionRepository,
        protected SolicitudTransformer $transformer
    ) {}

    public function create(CreateSolicitudDTO $dto, User $user): Solicitud
    {
        return DB::transaction(function () use ($dto, $user) {
            // Obtener la programación para conocer el curso
            $programacion = $this->programacionRepository->findById($dto->programacion_id);

            if (!$programacion) {
                throw new Exception('La programación seleccionada no existe.');
            }

            if (!$programacion->curso_id) {
                throw new Exception('La programación no tiene un curso asociado.');
            }

            // Verificar que el periodo esté activo
            if (!$programacion->periodo || !$programacion->periodo->activo) {
                throw new Exception('No se pueden presentar solicitudes para periodos académicos inactivos.');
            }

            // Verificar que la presentación de solicitudes esté abierta
            if (!$programacion->periodo->solicitudes_abiertas) {
                throw new Exception('La presentación de solicitudes está cerrada. No se aceptan nuevas solicitudes en este momento.');
            }

            // Verificar que la sección fue programada para la escuela del estudiante.
            // Se omite si fuera_de_plan = true o inscripcion_escuela = true.
            $esExcepcion = $dto->fuera_de_plan || $dto->inscripcion_escuela;

            if ($user->escuela_id && !$esExcepcion) {
                if ($programacion->escuela_programada_id !== $user->escuela_id) {
                    throw new Exception('Esta sección no fue programada para tu escuela profesional.');
                }
            }

            // Verificar si ya existe una solicitud activa para este curso
            if ($this->repository->existsSolicitudActivaParaCurso($user->id, $programacion->curso_id)) {
                throw new Exception('Ya tienes una solicitud activa para este curso. No puedes presentar otra hasta que sea resuelta.');
            }

            // Elegir el tipo de solicitud según el contexto
            $tipoCodigo = $dto->inscripcion_escuela ? 'INSC_ESCUELA' : 'CUPO_EXT';
            $tipoSolicitud = $this->tipoSolicitudRepository->findByCode($tipoCodigo);

            if (!$tipoSolicitud) {
                throw new Exception('Configuración de tipos de solicitud incompleta.');
            }

            $firmaPath = $this->storeBase64Signature($dto->firma, $user->id);

            $sustentoPath = null;
            $sustentoNombre = null;

            if ($dto->archivo_sustento) {
                $sustentoNombre = $dto->archivo_sustento->getClientOriginalName();
                $sustentoPath = $dto->archivo_sustento->store('sustentos', 'public');
            }

            $solicitud = $this->repository->create([
                'user_id' => $user->id,
                'tipo_solicitud_id' => $tipoSolicitud->id,
                'programacion_id' => $dto->programacion_id,
                'motivo' => $dto->motivo,
                'firma_digital_path' => $firmaPath,
                'archivo_sustento_path' => $sustentoPath,
                'archivo_sustento_nombre' => $sustentoNombre,
                'estado' => 'pendiente',
                'fuera_de_plan' => $dto->fuera_de_plan,
                'metadatos' => [
                    'user_agent' => $dto->user_agent,
                    'ip' => $dto->ip,
                ]
            ]);

            return $this->repository->findById($solicitud->id);
        });
    }

    /**
     * Obtiene solicitudes del usuario (para estudiantes)
     */
    public function getByUser(User $user, Request $request): LengthAwarePaginator
    {
        $perPage = $request->get('per_page', 10);
        return $this->repository->findByUserId($user->id, $perPage);
    }

    /**
     * Obtiene todas las solicitudes con filtros (para admin/secretaria)
     */
    public function getAll(Request $request): LengthAwarePaginator
    {
        $filters = [
            'estado'               => $request->get('estado'),
            'search'               => $request->get('search'),
            'programacion_id'      => $request->get('programacion_id'),
            'tipo'                 => $request->get('tipo'),
            'escuela_id'           => $request->get('escuela_id'),
            'escuela_programada_id'=> $request->get('escuela_programada_id'),
            'sort_order'           => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 10);

        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getAllForExport(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $filters = [
            'estado'               => $request->get('estado'),
            'search'               => $request->get('search'),
            'programacion_id'      => $request->get('programacion_id'),
            'tipo'                 => $request->get('tipo'),
            'escuela_id'           => $request->get('escuela_id'),
            'escuela_programada_id'=> $request->get('escuela_programada_id'),
        ];

        return $this->repository->getAllForExport($filters);
    }

    public function findById(string $id): ?Solicitud
    {
        return $this->repository->findById($id);
    }

    /**
     * Actualiza el estado de una solicitud
     */
    public function updateEstado(string $id, string $estado, ?string $observaciones = null, ?User $asignadoA = null): ?Solicitud
    {
        $data = ['estado' => $estado];

        if ($observaciones !== null) {
            $data['observaciones_admin'] = $observaciones;
        }

        if ($asignadoA !== null) {
            $data['asignado_a'] = $asignadoA->id;
        }

        return $this->repository->update($id, $data);
    }

    protected function storeBase64Signature(string $base64, string $userId): string
    {
        if (Str::contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }

        $decodedData = base64_decode($base64);
        $fileName = "firmas/signature_u{$userId}_" . now()->timestamp . ".png";

        Storage::disk('public')->put($fileName, $decodedData);

        return $fileName;
    }
}
