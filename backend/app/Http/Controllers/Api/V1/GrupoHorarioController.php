<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GrupoHorario;
use App\Models\GrupoHorarioDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrupoHorarioController extends Controller
{
    public function index(): JsonResponse
    {
        $grupos = GrupoHorario::with('detalles')
            ->orderByRaw("CAST(SUBSTRING(nombre, 2) AS UNSIGNED)")
            ->get()
            ->map(fn($g) => $this->format($g));

        return $this->success($grupos, 'Lista de grupos horario');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:10|unique:grupos_horario,nombre',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $grupo = GrupoHorario::resolverPorCodigo($data['nombre']);
        if ($data['descripcion'] ?? null) {
            $grupo->update(['descripcion' => $data['descripcion']]);
        }
        $grupo->load('detalles');

        return $this->success($this->format($grupo), 'Grupo creado', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $grupo = GrupoHorario::find($id);
        if (!$grupo) return $this->notFound('Grupo no encontrado');

        $data = $request->validate([
            'nombre'      => 'sometimes|string|max:10|unique:grupos_horario,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
        ]);

        $grupo->update($data);
        $grupo->load('detalles');

        return $this->success($this->format($grupo), 'Grupo actualizado');
    }

    public function destroy(string $id): JsonResponse
    {
        $grupo = GrupoHorario::find($id);
        if (!$grupo) return $this->notFound('Grupo no encontrado');

        if ($grupo->programaciones()->exists()) {
            return $this->error('No se puede eliminar: tiene programaciones asociadas.', 422);
        }

        $grupo->delete();

        return $this->success(null, 'Grupo eliminado');
    }

    public function toggle(string $id): JsonResponse
    {
        $grupo = GrupoHorario::find($id);
        if (!$grupo) return $this->notFound('Grupo no encontrado');

        $grupo->update(['activo' => !$grupo->activo]);
        $grupo->load('detalles');

        return $this->success($this->format($grupo), $grupo->activo ? 'Grupo activado' : 'Grupo desactivado');
    }

    public function addDetalle(Request $request, string $id): JsonResponse
    {
        $grupo = GrupoHorario::find($id);
        if (!$grupo) return $this->notFound('Grupo no encontrado');

        $data = $request->validate([
            'dia_semana'  => 'required|in:lunes,martes,miercoles,jueves,viernes,sabado',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i|after:hora_inicio',
        ]);

        if ($grupo->detalles()->where('dia_semana', $data['dia_semana'])->exists()) {
            return $this->error("Este grupo ya tiene un horario para el día {$data['dia_semana']}.", 422);
        }

        $detalle = $grupo->detalles()->create($data);
        $grupo->load('detalles');

        return $this->success($this->format($grupo), 'Horario agregado');
    }

    public function updateDetalle(Request $request, string $id, string $detalleId): JsonResponse
    {
        $detalle = GrupoHorarioDetalle::where('grupo_id', $id)->find($detalleId);
        if (!$detalle) return $this->notFound('Horario no encontrado');

        $data = $request->validate([
            'dia_semana'  => 'required|in:lunes,martes,miercoles,jueves,viernes,sabado',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i|after:hora_inicio',
        ]);

        $existe = GrupoHorarioDetalle::where('grupo_id', $id)
            ->where('dia_semana', $data['dia_semana'])
            ->where('id', '!=', $detalleId)
            ->exists();

        if ($existe) {
            return $this->error("Este grupo ya tiene un horario para el día {$data['dia_semana']}.", 422);
        }

        $detalle->update($data);

        $grupo = GrupoHorario::with('detalles')->find($id);

        return $this->success($this->format($grupo), 'Horario actualizado');
    }

    public function removeDetalle(string $id, string $detalleId): JsonResponse
    {
        $detalle = GrupoHorarioDetalle::where('grupo_id', $id)->find($detalleId);
        if (!$detalle) return $this->notFound('Horario no encontrado');

        $detalle->delete();

        $grupo = GrupoHorario::with('detalles')->find($id);

        return $this->success($this->format($grupo), 'Horario eliminado');
    }

    private function format(GrupoHorario $g): array
    {
        return [
            'id'          => $g->id,
            'nombre'      => $g->nombre,
            'descripcion' => $g->descripcion,
            'activo'      => $g->activo,
            'detalles'    => $g->detalles->map(fn($d) => [
                'id'          => $d->id,
                'dia_semana'  => $d->dia_semana,
                'hora_inicio' => $d->hora_inicio,
                'hora_fin'    => $d->hora_fin,
            ])->toArray(),
        ];
    }
}
