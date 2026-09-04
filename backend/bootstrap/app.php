<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // CheckUserRole (alias 'checkrole') se eliminó — RBAC Fase 2
            // (docs/specs/rbac-fase2-enforcement.md) migró TODAS las rutas
            // que lo usaban a CheckPermission, verificado con la suite
            // completa antes de borrarlo.
            'checkpermission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Global Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
        });

        // Esta app es 100% API — nunca hay una página de login basada en
        // sesión a la cual redirigir. Sin esto, el manejo default de
        // Laravel cae a redirect()->guest(route('login')) para cualquier
        // request no autenticado que no mande Accept: application/json
        // (curl sin headers, monitoreo, health-checks) — antes eso
        // reventaba 500 (route('login') no existía); con la ruta nombrada
        // de routes/web.php ya no revienta, pero sigue siendo un redirect
        // innecesario. Responder 401 JSON directo siempre, sin importar
        // qué "espera" el request — es la única respuesta correcta que
        // esta API da.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
