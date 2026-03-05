<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Periodo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PeriodoObserver
{
    public function created(Periodo $periodo): void
    {
        $this->log('periodo.created', $periodo, [], $periodo->getAttributes());
    }

    public function updated(Periodo $periodo): void
    {
        $this->log('periodo.updated', $periodo, $periodo->getOriginal(), $periodo->getChanges());
    }

    private function log(string $accion, Periodo $model, array $anterior, array $nuevo): void
    {
        ActivityLog::create([
            'user_id'           => Auth::id(),
            'accion'            => $accion,
            'modelo'            => Periodo::class,
            'modelo_id'         => $model->id,
            'valores_anteriores' => empty($anterior) ? null : $anterior,
            'valores_nuevos'    => empty($nuevo) ? null : $nuevo,
            'ip'                => Request::ip(),
            'user_agent'        => Request::userAgent(),
        ]);
    }
}
