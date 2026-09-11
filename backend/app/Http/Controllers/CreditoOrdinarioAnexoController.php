<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\CreditoOrdinario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SCRUM-345 (rebote): "Anexos" — archivos sueltos que el Director de
 * Crédito puede cargar en cualquier etapa del proceso, sin preset ni
 * nombre/clave predefinida (foto de tirilla, Word de valoración, PDF de un
 * externo, lo que sea), quedando asociados al crédito para consulta
 * interna. Deliberadamente separado de CreditoOrdinarioController::
 * transition() — ese método ya mezcla máquina de estados + 3 modos de
 * carga de archivo atados a una clave fija; los anexos no encajan en ese
 * modelo (ver investigación previa a este ticket) y no participan de
 * ninguna transición de estado.
 */
class CreditoOrdinarioAnexoController extends Controller
{
    use ResolvesActiveRole;

    /**
     * Mismo set que CreditoOrdinarioController::ROLES_AUTORIZADOS, sin
     * 'cliente': los anexos son de uso interno del proceso ("verse
     * internamente", pedido explícito de Luis) — el cliente no tiene
     * acceso a este listado ni al de carga, a diferencia del resto de
     * documentos del expediente.
     */
    private const ROLES_CON_ACCESO = [
        'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito',
        'operativo', 'tesoreria', 'gerente', 'superadmin',
    ];

    /** Solo el Director de Crédito carga anexos (pedido explícito). */
    private const ROLES_QUE_CARGAN = ['coordinador_comercial', 'superadmin'];

    public function index(Request $request, $id)
    {
        $credito = CreditoOrdinario::findOrFail($id);
        $activeRole = $this->resolveActiveRole($request);

        if (!in_array($activeRole, self::ROLES_CON_ACCESO, true)) {
            abort(403, 'No tienes autorización para ver los anexos de este crédito.');
        }

        return $credito->anexos()->with('subidoPor:id,name')->get();
    }

    public function store(Request $request, $id)
    {
        $credito = CreditoOrdinario::findOrFail($id);
        $activeRole = $this->resolveActiveRole($request);

        if (!in_array($activeRole, self::ROLES_QUE_CARGAN, true)) {
            abort(403, 'Solo el Director de Crédito puede cargar anexos.');
        }

        // Sin restricción de tipo documental: puede ser una foto, un Word,
        // un PDF externo, lo que sea — solo se acotan formatos con riesgo
        // de ejecución de código (no .exe/.php/etc) y un tamaño máximo,
        // mismo límite que el resto de cargas de este módulo.
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp,heic|max:102400',
        ]);

        $file = $request->file('archivo');
        $nombreOriginal = $file->getClientOriginalName();
        // Prefijo único: 2 anexos distintos pueden llegar con el mismo
        // nombre de archivo (ej. "IMG_0001.jpg" de 2 celulares distintos)
        // — storeAs con el nombre tal cual pisaría el primero.
        $nombreAlmacenado = uniqid() . '_' . $nombreOriginal;
        $path = $file->storeAs("credito_documentos/{$credito->id}/anexos", $nombreAlmacenado, 'public');

        $anexo = $credito->anexos()->create([
            'nombre_original'     => $nombreOriginal,
            'path'                => $path,
            'mime'                => $file->getClientMimeType(),
            'tamano'              => $file->getSize(),
            'subido_por_user_id'  => Auth::id(),
            'subido_por_rol'      => $activeRole,
        ]);

        return response()->json($anexo->load('subidoPor:id,name'), 201);
    }
}
