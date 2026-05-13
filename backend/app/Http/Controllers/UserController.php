<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::with('documentType')->orderBy('name')->get());
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
            'roles.*' => 'string|in:superadmin,gerente,operativo,cliente,contable'
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
            'roles.*' => 'string|in:superadmin,gerente,operativo,cliente,contable'
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
            return response()->json(['message' => 'No puedes eliminarte a ti mismo.'], 422);
        }

        $user->delete();
        return response()->json(null, 204);
    }
}
