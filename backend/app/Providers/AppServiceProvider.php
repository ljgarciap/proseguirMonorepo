<?php

namespace App\Providers;

use App\Mail\Transport\GraphMailTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Envío de correo vía Microsoft Graph API (Azure AD App Registration,
        // OAuth2 client-credentials) — MAIL_MAILER=graph en .env lo activa.
        // Las credenciales las lee el transport de la tabla `configuraciones`
        // en tiempo de envío, no de acá (ver GraphMailTransport).
        Mail::extend('graph', fn () => new GraphMailTransport());
    }
}
