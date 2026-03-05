<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SolicitudObserver
{
    public function created(Solicitud $solicitud): void
    {
        $this->log('solicitud.created', $solicitud, [], $solicitud->getAttributes());
    }

    public function updated(Solicitud $solicitud): void
    {
        $this->log('solicitud.updated', $solicitud, $solicitud->getOriginal(), $solicitud->getChanges());
    }

    public function deleted(Solicitud $solicitud): void
    {
        $this->log('solicitud.deleted', $solicitud, $solicitud->getAttributes(), []);
    }

    private function log(string $accion, Solicitud $model, array $anterior, array $nuevo): void
    {
        ActivityLog::create([
            'user_id'           => Auth::id(),
            'accion'            => $accion,
            'modelo'            => Solicitud::class,
            'modelo_id'         => $model->id,
            'valores_anteriores' => empty($anterior) ? null : $anterior,
            'valores_nuevos'    => empty($nuevo) ? null : $nuevo,
            'ip'                => Request::ip(),
            'user_agent'        => Request::userAgent(),
        ]);
    }
}
