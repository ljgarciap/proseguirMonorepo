<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * SCRUM-294 (NTF-02): notifica a los usuarios activos con Rol Coordinador
 * Comercial que Gerencia aprobó el Registro de Crédito en CYF — informativa,
 * independiente de NTF-01 (RegistroCyfAprobadoMail, a Operativo). Disparada
 * desde GestionCreditoController::aprobacionRegistroCyf() en la misma
 * transición a 'desembolso_ingreso'.
 *
 * Rebote 2026-08-31 (comentario de Juan): faltaban cliente, fecha de
 * registro CYF, radicado CYF y observaciones de Gerencia en el resumen.
 * $observaciones es opcional en la aprobación (solo es obligatoria al
 * rechazar) — la vista muestra "Sin observaciones" cuando viene null.
 */
class RegistroCyfAprobadoCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public ?Carbon $fechaRegistroCyf;
    public string $radicadoCyf;
    public string $nombreGerente;
    public ?string $observaciones;
    public string $urlIngreso;

    public function __construct(
        CreditoOrdinario $credito,
        string $nombreCliente,
        ?Carbon $fechaRegistroCyf,
        string $radicadoCyf,
        string $nombreGerente,
        ?string $observaciones,
        string $urlIngreso
    ) {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->fechaRegistroCyf = $fechaRegistroCyf;
        $this->radicadoCyf = $radicadoCyf;
        $this->nombreGerente = $nombreGerente;
        $this->observaciones = $observaciones;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registro de Crédito en CYF aprobado - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registro_cyf_aprobado_coordinador');
    }

    public function attachments(): array
    {
        return [];
    }
}
