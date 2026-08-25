<?php

namespace App\Services\FirmaElectronica;

use App\Contracts\Firmable;
use App\Contracts\ReautenticacionStrategy;
use App\Models\FirmaElectronica;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\FirmaElectronica\Reautenticacion\PasswordReauthStrategy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * SCRUM-245 — Orquesta el flujo completo de firma:
 * reautenticar → validar rol → congelar PDF en disco → hashear → registrar.
 *
 * Es el único punto de entrada para crear una FirmaElectronica — ningún
 * controller debe llamar FirmaElectronica::create() directo, o se salta la
 * reautenticación/el congelamiento del PDF y el registro deja de ser
 * defendible legalmente.
 */
class FirmaElectronicaService
{
    /**
     * Métodos de reautenticación disponibles. Agregar OTP por correo a
     * futuro es sumar una línea acá (y la clase que la implemente) — el
     * resto del servicio no cambia.
     */
    private const ESTRATEGIAS = [
        'password_reauth' => PasswordReauthStrategy::class,
    ];

    public function __construct(private ActivityLogService $activityLog)
    {
    }

    public function firmar(
        Firmable $documento,
        User $usuario,
        string $metodoValidacion,
        array $credenciales,
        string $direccionIp,
        ?string $userAgent,
    ): FirmaElectronica {
        $estrategia = $this->resolverEstrategia($metodoValidacion);

        if (! $estrategia->validar($usuario, $credenciales)) {
            throw ValidationException::withMessages([
                'credenciales' => ['La reautenticación falló. No se registró ninguna firma.'],
            ]);
        }

        $this->verificarRolAutorizado($documento, $usuario);

        $pdfBytes = $documento->generarPdfParaFirma();
        $hash = hash('sha256', $pdfBytes);
        $rutaRelativa = $this->guardarPdfCongelado($documento, $pdfBytes);

        $firma = FirmaElectronica::create([
            'firmable_type' => get_class($documento),
            'firmable_id' => $documento->getKey(),
            'usuario_id' => $usuario->id,
            'nombre_firmante' => $usuario->name,
            'numero_documento_firmante' => $usuario->numero_documento,
            'rol_firmante' => implode(',', $usuario->roles ?? []),
            'metodo_validacion' => $metodoValidacion,
            'direccion_ip' => $direccionIp,
            'user_agent' => $userAgent,
            'documento_path' => $rutaRelativa,
            'documento_hash_sha256' => $hash,
            'hash_algoritmo' => 'sha256',
        ]);

        // SCRUM-246: entrada en el feed de actividad para que la firma
        // aparezca en la vista unificada de superadmin — firmas_electronicas
        // sigue siendo la fuente de verdad legal (con su propio trigger
        // append-only), esto es solo un puntero para la UI de auditoría.
        $this->activityLog->registrar(
            accion: 'firma_electronica.creada',
            descripcion: "{$usuario->name} firmó electrónicamente un documento ({$documento::firmableSlug()} #{$documento->getKey()}).",
            usuario: $usuario,
            entidad: $firma,
        );

        return $firma;
    }

    /**
     * Re-hashea el PDF tal como está HOY en disco y lo compara contra el
     * hash persistido en el momento de la firma. false = el archivo fue
     * alterado o borrado desde que se firmó.
     */
    public function verificar(FirmaElectronica $firma): bool
    {
        if (! Storage::disk('public')->exists($firma->documento_path)) {
            return false;
        }

        $bytesActuales = Storage::disk('public')->get($firma->documento_path);
        $hashActual = hash($firma->hash_algoritmo, $bytesActuales);

        return hash_equals($firma->documento_hash_sha256, $hashActual);
    }

    private function resolverEstrategia(string $metodo): ReautenticacionStrategy
    {
        $clase = self::ESTRATEGIAS[$metodo] ?? null;

        if (! $clase) {
            throw ValidationException::withMessages([
                'metodo_validacion' => ["Método de reautenticación '{$metodo}' no soportado."],
            ]);
        }

        return app($clase);
    }

    private function verificarRolAutorizado(Firmable $documento, User $usuario): void
    {
        $rolesAutorizados = $documento->rolesAutorizadosParaFirmar();

        if (empty($rolesAutorizados)) {
            return;
        }

        if (empty(array_intersect($usuario->roles ?? [], $rolesAutorizados))) {
            throw ValidationException::withMessages([
                'usuario' => ['Tu rol no está autorizado a firmar este documento.'],
            ]);
        }
    }

    private function guardarPdfCongelado(Firmable $documento, string $pdfBytes): string
    {
        $slug = $documento::firmableSlug();
        $carpeta = "firmas/{$slug}/{$documento->getKey()}";
        $nombreArchivo = $documento->nombreArchivoFirma() . '-' . now()->format('YmdHis') . '.pdf';
        $rutaRelativa = "{$carpeta}/{$nombreArchivo}";

        Storage::disk('public')->put($rutaRelativa, $pdfBytes);

        return $rutaRelativa;
    }
}
