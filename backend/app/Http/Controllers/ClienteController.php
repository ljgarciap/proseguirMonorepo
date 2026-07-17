<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use App\Models\TipoPersona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::select('clientes.*')
            ->leftJoin('tipo_personas', 'clientes.tipo_persona_id', '=', 'tipo_personas.id')
            ->with(['tipoPersona', 'documentType', 'repDocumentType']);

        // Filtering
        if ($request->filled('nombre')) {
            $query->where('clientes.nombre', 'like', "%{$request->nombre}%");
        }
        if ($request->filled('tipo_persona')) {
            $query->where('tipo_personas.nombre', 'like', "%{$request->tipo_persona}%");
        }
        if ($request->filled('identificacion')) {
            $query->where('clientes.numero_documento', 'like', "%{$request->identificacion}%");
        }
        if ($request->filled('activo')) {
            $val = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->where('clientes.activo', $val);
            }
        }

        // Búsqueda combinada para autocompletar (SCRUM-134): nombre O número de
        // documento, con límite para no traer cientos de filas por tecleo.
        $isAutocomplete = $request->filled('q');
        if ($isAutocomplete) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('clientes.nombre', 'like', "%{$q}%")
                    ->orWhere('clientes.numero_documento', 'like', "%{$q}%");
            });
        }

        // Sorting: Alphabetical by Name/Razón Social, and then by Tipo de Persona
        $query->orderBy('clientes.nombre', 'asc')
              ->orderBy('tipo_personas.nombre', 'asc');

        if ($isAutocomplete) {
            $query->limit(20);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $cliente = Cliente::with(['tipoPersona', 'documentType', 'repDocumentType'])->findOrFail($id);
        return response()->json($cliente);
    }

    public function store(Request $request)
    {
        $tipoPersonaId = $request->tipo_persona_id;
        $tipoPersona = $tipoPersonaId ? TipoPersona::find($tipoPersonaId) : null;
        $codigo = $tipoPersona ? strtoupper($tipoPersona->codigo) : '';

        $rules = [
            'tipo_persona_id' => 'required|exists:tipo_personas,id',
            'tipo_documento_id' => 'required|exists:document_types,id',
            'numero_documento' => 'required|string|unique:clientes,numero_documento',
            'pais' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'ciudad_id' => [
                'required',
                Rule::exists('ciudades', 'id')->where(function ($query) use ($request) {
                    $query->where('departamento_id', $request->departamento_id);
                }),
            ],
            'activo' => 'required|boolean',
        ];

        if ($codigo === 'NATURAL') {
            $rules['nombres'] = 'required|string';
            $rules['primer_apellido'] = 'required|string';
            $rules['segundo_apellido'] = 'nullable|string';
            $rules['correo_electronico'] = 'nullable|email';
            $rules['telefono'] = 'nullable|string';
            $rules['direccion'] = 'nullable|string';
            $rules['ocupacion'] = 'nullable|string';
        } else {
            $rules['nombre_razon_social'] = 'required|string';
            $rules['tipo_empresa'] = 'nullable|string';
            $rules['actividad_economica'] = 'nullable|string';
            $rules['correo_electronico_empresarial'] = 'nullable|email';
            $rules['pagina_web'] = 'nullable|string';
            $rules['telefono'] = 'nullable|string';
            $rules['direccion'] = 'nullable|string';

            // Representante Legal Info
            $rules['rep_tipo_documento_id'] = 'required|exists:document_types,id';
            $rules['rep_numero_documento'] = 'required|string';
            $rules['rep_nombres'] = 'required|string';
            $rules['rep_primer_apellido'] = 'required|string';
            $rules['rep_segundo_apellido'] = 'nullable|string';
            $rules['rep_cargo'] = 'nullable|string';
            $rules['rep_correo_electronico'] = 'nullable|email';
            $rules['rep_telefono'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $codigo) {
            $cliente = Cliente::create($validated);
            $this->syncUserAccess($cliente, $codigo);
            return response()->json($cliente, 201);
        });
    }

    /**
     * Alta rápida de cliente (SCRUM-134): solo los datos mínimos para poder
     * registrar una visita o solicitud en el momento. El resto del perfil
     * (dirección, ubicación, representante legal, etc.) se completa después
     * desde el formulario completo de Clientes.
     */
    public function quickStore(Request $request)
    {
        $tipoPersonaId = $request->tipo_persona_id;
        $tipoPersona = $tipoPersonaId ? TipoPersona::find($tipoPersonaId) : null;
        $codigo = $tipoPersona ? strtoupper($tipoPersona->codigo) : '';

        $rules = [
            'tipo_persona_id' => 'required|exists:tipo_personas,id',
            'tipo_documento_id' => 'required|exists:document_types,id',
            'numero_documento' => 'required|string|unique:clientes,numero_documento',
        ];

        if ($codigo === 'NATURAL') {
            $rules['nombres'] = 'required|string';
            $rules['primer_apellido'] = 'required|string';
        } else {
            $rules['nombre_razon_social'] = 'required|string';
        }

        $validated = $request->validate($rules);
        $validated['activo'] = true;

        return DB::transaction(function () use ($validated, $codigo) {
            $cliente = Cliente::create($validated);
            $this->syncUserAccess($cliente, $codigo);
            return response()->json($cliente, 201);
        });
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);
        $tipoPersonaId = $request->tipo_persona_id;
        $tipoPersona = $tipoPersonaId ? TipoPersona::find($tipoPersonaId) : null;
        $codigo = $tipoPersona ? strtoupper($tipoPersona->codigo) : '';

        $rules = [
            'tipo_persona_id' => 'required|exists:tipo_personas,id',
            'tipo_documento_id' => 'required|exists:document_types,id',
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('clientes', 'numero_documento')->ignore($cliente->id)
            ],
            'pais' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'ciudad_id' => [
                'required',
                Rule::exists('ciudades', 'id')->where(function ($query) use ($request) {
                    $query->where('departamento_id', $request->departamento_id);
                }),
            ],
            'activo' => 'required|boolean',
        ];

        if ($codigo === 'NATURAL') {
            $rules['nombres'] = 'required|string';
            $rules['primer_apellido'] = 'required|string';
            $rules['segundo_apellido'] = 'nullable|string';
            $rules['correo_electronico'] = 'nullable|email';
            $rules['telefono'] = 'nullable|string';
            $rules['direccion'] = 'nullable|string';
            $rules['ocupacion'] = 'nullable|string';
        } else {
            $rules['nombre_razon_social'] = 'required|string';
            $rules['tipo_empresa'] = 'nullable|string';
            $rules['actividad_economica'] = 'nullable|string';
            $rules['correo_electronico_empresarial'] = 'nullable|email';
            $rules['pagina_web'] = 'nullable|string';
            $rules['telefono'] = 'nullable|string';
            $rules['direccion'] = 'nullable|string';

            // Representante Legal Info
            $rules['rep_tipo_documento_id'] = 'required|exists:document_types,id';
            $rules['rep_numero_documento'] = 'required|string';
            $rules['rep_nombres'] = 'required|string';
            $rules['rep_primer_apellido'] = 'required|string';
            $rules['rep_segundo_apellido'] = 'nullable|string';
            $rules['rep_cargo'] = 'nullable|string';
            $rules['rep_correo_electronico'] = 'nullable|email';
            $rules['rep_telefono'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($cliente, $validated, $codigo) {
            $cliente->update($validated);
            $this->syncUserAccess($cliente, $codigo);
            return response()->json($cliente);
        });
    }

    public function destroy($id)
    {
        $cliente = Cliente::findOrFail($id);
        
        DB::transaction(function () use ($cliente) {
            $cliente->update(['activo' => false]);
            // Soft-delete corresponding user as well
            $user = User::where('numero_documento', $cliente->numero_documento)->first();
            if ($user) {
                $user->delete();
            }
        });

        return response()->json(['message' => 'Cliente desactivado correctamente']);
    }

    /**
     * Provision or sync User account for the client.
     */
    protected function syncUserAccess(Cliente $cliente, $codigo)
    {
        // Clean password for NIT (remove hyphens)
        $cleanPassword = str_replace('-', '', $cliente->numero_documento);

        $email = ($codigo === 'NATURAL') 
            ? $cliente->correo_electronico 
            : $cliente->correo_electronico_empresarial;

        // Find existing user by document
        $user = User::withTrashed()->where('numero_documento', $cliente->numero_documento)->first();

        // Keep existing roles if user exists, otherwise default to ['cliente']
        $roles = ['cliente'];
        if ($user) {
            $existingRoles = is_array($user->roles) ? $user->roles : (json_decode($user->roles, true) ?: []);
            if (!in_array('cliente', $existingRoles)) {
                $existingRoles[] = 'cliente';
            }
            $roles = $existingRoles;
        }

        // If email exists on another user, prevent collision (use document-based mock email if empty)
        $email = $email ?: ($cliente->numero_documento . '@noreply.proseguir.local');

        $userData = [
            'name' => $cliente->nombre,
            'email' => $email,
            'tipo_documento_id' => $cliente->tipo_documento_id,
            'roles' => $roles,
        ];

        if (!$user) {
            $userData['numero_documento'] = $cliente->numero_documento;
            $userData['password'] = Hash::make($cleanPassword);
            $user = User::create($userData);
        } else {
            $user->update($userData);
        }

        // Handle active/inactive status
        if ($cliente->activo) {
            if ($user->trashed()) {
                $user->restore();
            }
        } else {
            if (!$user->trashed()) {
                $user->delete();
            }
        }
    }
}
