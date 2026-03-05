<?php

namespace App\Providers;

use App\Models\Periodo;
use App\Models\Solicitud;
use App\Models\User;
use App\Observers\PeriodoObserver;
use App\Observers\SolicitudObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Solicitud::observe(SolicitudObserver::class);
        User::observe(UserObserver::class);
        Periodo::observe(PeriodoObserver::class);
    }
}
