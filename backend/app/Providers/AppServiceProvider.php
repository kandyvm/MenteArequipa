<?php

namespace App\Providers;

use App\Models\Derivacion;
use App\Models\Solicitud;
use App\Observers\DerivacionObserver;
use App\Observers\SolicitudObserver;
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
        Derivacion::observe(DerivacionObserver::class);
    }
}