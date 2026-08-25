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
    public $urlIngreso;

    /**
     * Create a new message instance.
     *
     * SCRUM-244 (RF-07): $urlIngreso es el botón "Ingresar a la
     * plataforma" del correo — independiente de la URL/usuario/clave que
     * ya vienen incrustados como texto plano dentro de
     * $solicitud->mensaje_notificacion (editable por el Coordinador
     * Comercial antes de enviar, SCRUM-173). Mismo helper que ya usan los
     * correos de staff (ConfiguracionService::urlIngresoSistema()).
     */
    public function __construct(SolicitudCredito $solicitud, array $documentosRequeridos = [], ?string $urlIngreso = null)
    {
        $this->solicitud = $solicitud;
        $this->documentosRequeridos = $documentosRequeridos;
        $this->urlIngreso = $urlIngreso ?? \App\Services\ConfiguracionService::urlIngresoSistema();
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
