<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private ActivityLogService $activityLog)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $query = User::with('documentType')->orderBy('name');

        if ($status === 'inactive') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('numero_documento', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'numero_documento' => 'required|string|unique:users',
            'tipo_documento_id' => 'required|exists:document_types,id',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,slug'
        ], [
            // SCRUM-337: mensaje por defecto de Laravel ("The numero documento
            // has already been taken.") quedaba sin traducir en Gestión de Usuarios.
            'numero_documento.unique' => 'El número de documento ingresado ya se encuentra registrado en el sistema.',
            'email.unique' => 'El correo electrónico ingresado ya se encuentra registrado en el sistema.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'numero_documento' => $request->numero_documento,
            'tipo_documento_id' => $request->tipo_documento_id,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'roles' => $request->roles,
        ]);

        $this->activityLog->registrar(
            'usuario_creado',
            "Se creó el usuario \"{$user->name}\" ({$user->numero_documento}).",
            Auth::user(),
            $user,
            ['roles' => $user->roles],
            $request,
        );

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('users')->ignore($user->id),
            ],
            'tipo_documento_id' => 'required|exists:document_types,id',
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,slug'
        ], [
            // SCRUM-337: mismo mensaje traducido que store().
            'numero_documento.unique' => 'El número de documento ingresado ya se encuentra registrado en el sistema.',
            'email.unique' => 'El correo electrónico ingresado ya se encuentra registrado en el sistema.',
        ]);

        // Se guarda ANTES del update — no hay otra forma de saber qué cambió
        // (ver hallazgo 2026-09-09: cambios de rol hechos por la UI no dejaban
        // ningún rastro en activity_logs porque este controller nunca llamaba
        // a ActivityLogService).
        $rolesAnteriores = $user->roles;
        $documentoAnterior = $user->numero_documento;

        $data = [
            'name' => $request->name,
            'numero_documento' => $request->numero_documento,
            'tipo_documento_id' => $request->tipo_documento_id,
            'email' => $request->email,
            'roles' => $request->roles,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $this->activityLog->registrar(
            'usuario_actualizado',
            "Se actualizó el usuario \"{$user->name}\" ({$user->numero_documento}).",
            Auth::user(),
            $user,
            [
                'roles_anteriores' => $rolesAnteriores,
                'roles_nuevos' => $user->roles,
                'numero_documento_anterior' => $documentoAnterior,
                'password_cambiada' => $request->filled('password'),
            ],
            $request,
        );

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        // Prevent deleting the last superadmin or yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
        }

        $user->delete();

        $this->activityLog->registrar(
            'usuario_desactivado',
            "Se desactivó el usuario \"{$user->name}\" ({$user->numero_documento}).",
            Auth::user(),
            $user,
            [],
            $request,
        );

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        $this->activityLog->registrar(
            'usuario_restaurado',
            "Se restauró el usuario \"{$user->name}\" ({$user->numero_documento}).",
            Auth::user(),
            $user,
            [],
            $request,
        );

        return response()->json($user);
    }
}
