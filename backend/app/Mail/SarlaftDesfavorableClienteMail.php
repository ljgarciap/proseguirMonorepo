<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al cliente que su crédito fue rechazado por concepto SARLAFT
 * desfavorable (SCRUM-128).
 */
class SarlaftDesfavorableClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $credito;

    public function __construct(CreditoOrdinario $credito)
    {
        $this->credito = $credito;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resultado de validación de crédito - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sarlaft_desfavorable_cliente',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
