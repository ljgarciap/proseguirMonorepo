<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

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

        // SCRUM-161: el usuario puede personalizar la duración de su sesión
        // (ver AuthController::updateProfile). Passport::personalAccessTokensExpireIn()
        // se lee de nuevo cada vez que se resuelve PersonalAccessTokenFactory
        // (binding contextual, no singleton — ver PassportServiceProvider::registerAuthorizationServer),
        // así que fijarlo acá, justo antes de createToken(), solo afecta al
        // token que se emite en ESTE login. No hay riesgo de fuga entre
        // requests: en PHP-FPM (sin Octane/Swoole en este proyecto) las
        // propiedades estáticas se reinician en cada request.
        $minutes = $user->session_duration_minutes ?? config('auth.session_duration_default');
        Passport::personalAccessTokensExpireIn(now()->addMinutes($minutes));

        return response()->json([
            'token' => $user->createToken('authToken')->accessToken,
            'user' => $user,
            'roles' => $user->roles
        ]);
    }

    /**
     * SCRUM-161: actualiza nombre completo y/o preferencia de duración de
     * sesión del usuario autenticado. Ambos campos son opcionales — se
     * puede llamar para actualizar solo uno de los dos.
     */
    public function updateProfile(Request $request)
    {
        // El máximo permitido vive en configuración, no solo como comentario:
        // cualquier opción de la lista que en el futuro supere el techo
        // configurado queda excluida acá, no depende de mantener las dos
        // constantes sincronizadas a mano.
        $allowedDurations = array_filter(
            config('auth.session_duration_options'),
            fn ($minutes) => $minutes <= config('auth.session_duration_max')
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'session_duration_minutes' => [
                'sometimes',
                'required',
                'integer',
                Rule::in($allowedDurations),
            ],
        ]);

        if (empty($validated)) {
            throw ValidationException::withMessages([
                'name' => ['No se envió ningún campo para actualizar.'],
            ]);
        }

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user->fresh(),
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
