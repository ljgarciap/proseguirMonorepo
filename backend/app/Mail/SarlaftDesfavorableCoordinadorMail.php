<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al Coordinador Comercial que un crédito fue rechazado por
 * concepto SARLAFT desfavorable (SCRUM-128).
 */
class SarlaftDesfavorableCoordinadorMail extends Mailable
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
            subject: 'Crédito rechazado por SARLAFT - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sarlaft_desfavorable_coordinador',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
