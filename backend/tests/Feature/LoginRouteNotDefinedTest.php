<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo del 2026-09-03 (investigación de la spec RBAC): un request sin
 * `Accept: application/json` contra un endpoint auth:api sin token
 * disparaba `RouteNotFoundException: Route [login] not defined.` → 500,
 * en vez del 401 esperado. Causa: esta app es 100% API (routes/web.php
 * solo tiene '/'), y el manejador de excepciones de Laravel cae a
 * `route('login')` para requests que no `expectsJson()` — curl sin
 * headers, monitoreo, health-checks. Nunca afectó al frontend real
 * (Angular manda Accept: application/json siempre), pero rompe cualquier
 * chequeo hecho con curl "pelado".
 */
class LoginRouteNotDefinedTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_sin_accept_json_no_revienta_con_500(): void
    {
        // Sin ->getJson() / sin header Accept explícito, replicando un
        // curl sin headers contra un endpoint protegido.
        $response = $this->get('/api/document-types');

        $response->assertStatus(401);
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
