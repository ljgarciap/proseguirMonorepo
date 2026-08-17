<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-215: notifica a los usuarios con Rol Gerente que Operativo registró
 * la Operación de Desembolso en CYF — queda pendiente su aprobación
 * (SCRUM-219).
 */
class DesembolsoRegistradoMail extends Mailable
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
            subject: 'Operación de desembolso registrada en CYF - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.desembolso_registrado');
    }

    public function attachments(): array
    {
        return [];
    }
}
