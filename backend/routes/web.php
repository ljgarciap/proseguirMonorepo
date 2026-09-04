<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Esta app es 100% API — no hay login basado en sesión/vista. Sin esta
 * ruta nombrada, cualquier request a un endpoint auth:api sin token que
 * NO mande Accept: application/json (curl "pelado", monitoreo,
 * health-checks) revienta con RouteNotFoundException: "Route [login] not
 * defined." → 500. Laravel siempre cae a route('login') como fallback
 * cuando Request::expectsJson() es false (Illuminate\Foundation\Exceptions
 * \Handler::unauthenticated()), sin importar qué devuelva
 * Authenticate::redirectTo(). El frontend real (Angular) nunca pisa este
 * camino — siempre manda ese header — pero cualquier chequeo externo sin
 * headers sí.
 */
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
