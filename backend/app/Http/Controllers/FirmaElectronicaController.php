<?php

namespace App\Http\Controllers;

use App\Contracts\Firmable;
use App\Models\FirmaElectronica;
use App\Services\FirmaElectronica\FirmaElectronicaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * SCRUM-245 — API genérica de firma electrónica. No hay ninguna ruta ni
 * lógica específica de Actas de Comité acá todavía: {tipo} se resuelve por
 * un allowlist fijo (self::TIPOS_FIRMABLES) y hoy ese allowlist está
 * vacío a propósito, porque este ticket no conecta ningún módulo — solo
 * deja la arquitectura lista. Conectar Actas de Comité es agregar UNA
 * línea acá ('acta-comite' => ActaComite::class) una vez que ActaComite
 * implemente Firmable, no tocar este controller.
 */
class FirmaElectronicaController extends Controller
{
    /**
     * Nunca resolver {tipo} aceptando un nombre de clase PHP directo del
     * frontend — sería inyección de tipo / IDOR sobre morphTo. Este mapa
     * es la única fuente de verdad de qué se puede firmar.
     *
     * @var array<string, class-string<Model&Firmable>>
     */
    private const TIPOS_FIRMABLES = [
        // 'acta-comite' => \App\Models\ActaComite::class,
    ];

    public function __construct(private FirmaElectronicaService $service)
    {
    }

    public function firmar(Request $request, string $tipo, int $id)
    {
        $documento = $this->resolverDocumento($tipo, $id);

        $validado = $request->validate([
            'metodo_validacion' => 'required|string',
            'password' => 'required_if:metodo_validacion,password_reauth|string',
        ]);

        $firma = $this->service->firmar(
            documento: $documento,
            usuario: $request->user(),
            metodoValidacion: $validado['metodo_validacion'],
            credenciales: $validado,
            direccionIp: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($firma, 201);
    }

    public function index(string $tipo, int $id)
    {
        $documento = $this->resolverDocumento($tipo, $id);

        $firmas = FirmaElectronica::where('firmable_type', get_class($documento))
            ->where('firmable_id', $documento->getKey())
            ->orderBy('created_at')
            ->get();

        return response()->json($firmas);
    }

    public function verificar(FirmaElectronica $firma)
    {
        return response()->json([
            'firma_id' => $firma->id,
            'valido' => $this->service->verificar($firma),
        ]);
    }

    /**
     * @return Model&Firmable
     */
    private function resolverDocumento(string $tipo, int $id): Firmable
    {
        $clase = self::TIPOS_FIRMABLES[$tipo] ?? null;

        if (! $clase) {
            throw new NotFoundHttpException("Tipo de documento firmable '{$tipo}' no reconocido.");
        }

        return $clase::findOrFail($id);
    }
}
