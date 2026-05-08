<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'numero_documento' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('numero_documento', $request->numero_documento)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'numero_documento' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('authToken')->accessToken,
            'user' => $user,
            'roles' => $user->roles
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
