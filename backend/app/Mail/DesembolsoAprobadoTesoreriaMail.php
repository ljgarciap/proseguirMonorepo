<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-219: notifica a los usuarios con Rol Tesorería que Gerencia aprobó
 * el Registro de Operación de Desembolso en CYF — pueden efectuar y
 * registrar la transferencia bancaria (pantalla legacy de Crédito
 * Ordinario, estado 'ejecucion_transferencia').
 */
class DesembolsoAprobadoTesoreriaMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $urlIngreso;

    public function __construct(CreditoOrdinario $credito, string $urlIngreso)
    {
        $this->credito = $credito;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Operación de desembolso aprobada - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.desembolso_aprobado_tesoreria');
    }

    public function attachments(): array
    {
        return [];
    }
}
