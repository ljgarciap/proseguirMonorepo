<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ResolvesActiveRole
{
    private function resolveActiveRole(Request $request): string
    {
        $user      = Auth::user();
        $userRoles = is_array($user->roles) ? $user->roles : [];
        $header    = $request->header('X-Active-Role');

        if ($header && (in_array($header, $userRoles) || in_array('superadmin', $userRoles))) {
            return $header;
        }

        return $userRoles[0] ?? 'cliente';
    }
}
