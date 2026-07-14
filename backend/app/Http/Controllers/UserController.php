<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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
            'roles.*' => 'string|in:superadmin,gerente,operativo,cliente,contable,coordinador_comercial,oficial_cumplimiento,comite_credito,tesoreria,ingeniero'
        ]);

        $user = User::create([
            'name' => $request->name,
            'numero_documento' => $request->numero_documento,
            'tipo_documento_id' => $request->tipo_documento_id,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'roles' => $request->roles,
        ]);

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
            'roles.*' => 'string|in:superadmin,gerente,operativo,cliente,contable,coordinador_comercial,oficial_cumplimiento,comite_credito,tesoreria,ingeniero'
        ]);

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

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        // Prevent deleting the last superadmin or yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
        }

        $user->delete();
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        return response()->json($user);
    }
}
