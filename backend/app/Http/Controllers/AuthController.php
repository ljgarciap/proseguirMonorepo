<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\ConfiguracionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

class AuthController extends Controller
{
    // SCRUM-161: fallback SOLO para cuando ConfiguracionService::get() no
    // encuentra fila en `configuraciones` o la lectura falla (ver try/catch
    // en ConfiguracionService::get) — mismo patrón que GeminiService,
    // MistralService y AnalisisFinancieroController::index(). En operación
    // normal, el valor real viene siempre de la tabla `configuraciones`
    // (grupo 'sesion', sembrada por ConfiguracionSeeder) y es ajustable por
    // un superadmin desde /configuraciones sin deploy.
    private const DEFAULT_SESSION_DURATION_OPTIONS = [
        ['value' => 30, 'label' => '30 minutos'],
        ['value' => 60, 'label' => '1 hora'],
        ['value' => 240, 'label' => '4 horas'],
        ['value' => 480, 'label' => '8 horas'],
        ['value' => 1440, 'label' => '24 horas'],
    ];

    private function getSessionDurationOptions(): array
    {
        $raw = ConfiguracionService::get(
            'SESSION_DURATION_OPTIONS',
            json_encode(self::DEFAULT_SESSION_DURATION_OPTIONS)
        );

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : self::DEFAULT_SESSION_DURATION_OPTIONS;
    }

    private function getSessionDurationDefault(): int
    {
        return (int) ConfiguracionService::get('SESSION_DURATION_DEFAULT', 480);
    }

    private function getSessionDurationMax(): int
    {
        return (int) ConfiguracionService::get('SESSION_DURATION_MAX', 1440);
    }

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
        $minutes = $user->session_duration_minutes ?? $this->getSessionDurationDefault();
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
        // El máximo permitido vive en configuración (tabla `configuraciones`,
        // no solo como comentario): cualquier opción de la lista que en el
        // futuro supere el techo configurado queda excluida acá, no depende
        // de mantener sincronizadas a mano SESSION_DURATION_OPTIONS y
        // SESSION_DURATION_MAX.
        $max = $this->getSessionDurationMax();
        $allowedDurations = array_filter(
            array_column($this->getSessionDurationOptions(), 'value'),
            fn ($minutes) => $minutes <= $max
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

    /**
     * SCRUM-161: expone al frontend SOLO las opciones de duración de sesión
     * (no-secretas) para renderizar el selector en /perfil, en vez de tener
     * esa lista hardcodeada y duplicada en el componente Angular. Cualquier
     * usuario autenticado puede leerlo (no requiere rol superadmin como
     * /configuraciones) — no expone ninguna otra fila de la tabla
     * `configuraciones` (ej. API keys).
     */
    public function sessionDurationOptions(Request $request)
    {
        $max = $this->getSessionDurationMax();

        $options = array_values(array_filter(
            $this->getSessionDurationOptions(),
            fn ($opt) => ($opt['value'] ?? null) <= $max
        ));

        return response()->json([
            'options' => $options,
            'default' => $this->getSessionDurationDefault(),
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
