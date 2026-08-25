<?php

namespace App\Contracts;

use App\Models\User;

/**
 * SCRUM-245 — Segundo factor exigido justo antes de firmar (la sesión
 * abierta por sí sola no basta para el requisito de autenticidad de Ley
 * 527/1999 art. 7). MVP implementa solo PasswordReauthStrategy; agregar
 * OTP por correo a futuro es una clase nueva que implemente esto y una
 * línea en FirmaElectronicaService::ESTRATEGIAS — no un rediseño.
 */
interface ReautenticacionStrategy
{
    /** Valor persistido en firmas_electronicas.metodo_validacion. */
    public static function metodo(): string;

    /**
     * Devuelve true si $credenciales reautentica válidamente a $user.
     * $credenciales trae lo que el frontend haya mandado para este método
     * (ej. ['password' => '...'] o ['codigo' => '123456']).
     */
    public function validar(User $user, array $credenciales): bool;
}
