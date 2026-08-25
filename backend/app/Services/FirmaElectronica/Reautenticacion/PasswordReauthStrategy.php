<?php

namespace App\Services\FirmaElectronica\Reautenticacion;

use App\Contracts\ReautenticacionStrategy;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * SCRUM-245 — Reautenticación con la contraseña del ERP, mismo patrón que
 * AuthController::login() y AuthController::changePassword() ya usan
 * (Hash::check contra $user->password). Elegido como método del MVP: no
 * agrega dependencia de infraestructura nueva y el usuario controla su
 * propia contraseña, lo que simplifica su defensa legal frente a un "yo no
 * firmé esto".
 */
class PasswordReauthStrategy implements ReautenticacionStrategy
{
    public static function metodo(): string
    {
        return 'password_reauth';
    }

    public function validar(User $user, array $credenciales): bool
    {
        return Hash::check($credenciales['password'] ?? '', $user->password);
    }
}
