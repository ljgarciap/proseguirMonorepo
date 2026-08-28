<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-294 (NTF-03): notifica a los usuarios activos con Rol Coordinador
 * Comercial que Gerencia rechazó el Registro de Crédito en CYF, con las
 * observaciones que motivan el ajuste (obligatorias, validadas en
 * GestionCreditoController::aprobacionRegistroCyf() antes de guardar). El
 * botón lleva directo a la pantalla de registro-cyf (mismo destino que
 * FormalizacionGarantiasCoordinadorMail) porque el crédito vuelve a
 * 'pendiente_registro_cyf' para que Coordinador registre de nuevo. No se
 * envía NTF-01 en este resultado (solo aplica a la aprobación).
 */
class RegistroCyfRechazadoCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $observaciones;
    public string $urlIngreso;

    public function __construct(CreditoOrdinario $credito, string $observaciones, string $urlIngreso)
    {
        $this->credito = $credito;
        $this->observaciones = $observaciones;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registro de Crédito en CYF rechazado - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registro_cyf_rechazado_coordinador');
    }

    public function attachments(): array
    {
        return [];
    }
}
