<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-284: todas las garantías del preset quedaron Aprobadas — se avisa al
 * Coordinador Comercial asignado a la solicitud (solicitudCredito->
 * usuarioRegistra, único rol de este flujo con modelo de asignación real,
 * ver ValidacionDocumentalNotificationService) para que continúe con el
 * Registro de Crédito en CYF. Disparado desde
 * GestionCreditoController::guardarFormalizacionGarantias() junto con (pero
 * independiente de) el correo de confirmación al cliente — un fallo de uno
 * no bloquea al otro (RF-14 del mismo criterio que SCRUM-258/267).
 */
class FormalizacionGarantiasCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public string $urlAcceso;

    public function __construct(CreditoOrdinario $credito, string $nombreCliente, string $urlAcceso)
    {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->urlAcceso = $urlAcceso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Garantías validadas - Solicitud ' . $this->credito->numero_solicitud . ' lista para registro en CYF',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.formalizacion_garantias_coordinador');
    }

    public function attachments(): array
    {
        return [];
    }
}
