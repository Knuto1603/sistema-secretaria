<?php

namespace App\Providers;

use App\Repositories\Contracts\CursoRepositoryInterface;
use App\Repositories\Contracts\ModificacionRepositoryInterface;
use App\Repositories\Contracts\PeriodoRepositoryInterface;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use App\Repositories\Contracts\SolicitudAperturaRepositoryInterface;
use App\Repositories\Contracts\SolicitudRepositoryInterface;
use App\Repositories\Contracts\TipoSolicitudRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\CursoRepository;
use App\Repositories\Eloquent\ModificacionRepository;
use App\Repositories\Eloquent\PeriodoRepository;
use App\Repositories\Eloquent\ProgramacionRepository;
use App\Repositories\Eloquent\SolicitudAperturaRepository;
use App\Repositories\Eloquent\SolicitudRepository;
use App\Repositories\Eloquent\TipoSolicitudRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(SolicitudRepositoryInterface::class, SolicitudRepository::class);
        $this->app->bind(SolicitudAperturaRepositoryInterface::class, SolicitudAperturaRepository::class);
        $this->app->bind(ProgramacionRepositoryInterface::class, ProgramacionRepository::class);
        $this->app->bind(CursoRepositoryInterface::class, CursoRepository::class);
        $this->app->bind(PeriodoRepositoryInterface::class, PeriodoRepository::class);
        $this->app->bind(TipoSolicitudRepositoryInterface::class, TipoSolicitudRepository::class);
        $this->app->bind(ModificacionRepositoryInterface::class, ModificacionRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
