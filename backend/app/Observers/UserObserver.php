<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class UserObserver
{
    public function created(User $user): void
    {
        $this->log('user.created', $user, [], $this->safeAttributes($user->getAttributes()));
    }

    public function updated(User $user): void
    {
        $this->log('user.updated', $user, $this->safeAttributes($user->getOriginal()), $this->safeAttributes($user->getChanges()));
    }

    public function deleted(User $user): void
    {
        $this->log('user.deleted', $user, $this->safeAttributes($user->getAttributes()), []);
    }

    /** Elimina campos sensibles antes de persistir */
    private function safeAttributes(array $attrs): array
    {
        unset($attrs['password'], $attrs['remember_token']);
        return $attrs;
    }

    private function log(string $accion, User $model, array $anterior, array $nuevo): void
    {
        ActivityLog::create([
            'user_id'           => Auth::id(),
            'accion'            => $accion,
            'modelo'            => User::class,
            'modelo_id'         => $model->id,
            'valores_anteriores' => empty($anterior) ? null : $anterior,
            'valores_nuevos'    => empty($nuevo) ? null : $nuevo,
            'ip'                => Request::ip(),
            'user_agent'        => Request::userAgent(),
        ]);
    }
}
