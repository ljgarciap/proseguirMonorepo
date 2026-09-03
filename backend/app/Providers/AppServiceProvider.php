<?php

namespace App\Providers;

use App\Mail\Transport\GraphMailTransport;
use Illuminate\Support\Carbon;
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

        // SCRUM-317 rebote (2026-09-02): config('app.timezone') es 'UTC' —
        // toda fecha/hora que se muestra a un usuario (correos, y cualquier
        // vista futura que renderice server-side) debe convertirse a hora
        // Colombia explícitamente, nunca asumirse. Este macro es el único
        // punto donde vive el string 'America/Bogota' — usarlo en vez de
        // repetir setTimezone('America/Bogota') suelto en cada blade.
        // No cambia config('app.timezone') global a propósito: eso
        // reinterpretaría retroactivamente todo timestamp ya guardado en
        // UTC (activity logs, créditos, etc.) corrido 5h hacia adelante.
        // ->copy() porque Carbon es mutable — sin esto, formatear una fecha
        // para mostrarla mutaría el valor original en memoria.
        Carbon::macro('bogota', function () {
            /** @var Carbon $this */
            return $this->copy()->setTimezone('America/Bogota');
        });
    }
}
