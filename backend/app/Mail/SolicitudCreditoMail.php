<?php

namespace App\Mail;

use App\Models\SolicitudCredito;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudCreditoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;
    public $documentosRequeridos;

    /**
     * Create a new message instance.
     */
    public function __construct(SolicitudCredito $solicitud, array $documentosRequeridos = [])
    {
        $this->solicitud = $solicitud;
        $this->documentosRequeridos = $documentosRequeridos;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->solicitud->asunto_notificacion,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud_credito',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
