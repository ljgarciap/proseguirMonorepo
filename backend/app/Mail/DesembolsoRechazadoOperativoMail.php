<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-219: notifica a los usuarios con Rol Operativo que Gerencia
 * rechazó el Registro de Operación de Desembolso en CYF — deben ajustar el
 * registro y sus documentos (vuelve a SCRUM-215).
 */
class DesembolsoRechazadoOperativoMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public ?string $observaciones;
    public string $urlIngreso;

    public function __construct(CreditoOrdinario $credito, ?string $observaciones, string $urlIngreso)
    {
        $this->credito = $credito;
        $this->observaciones = $observaciones;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registro de operación de desembolso rechazado - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.desembolso_rechazado_operativo');
    }

    public function attachments(): array
    {
        return [];
    }
}
