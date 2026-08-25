<?php

namespace App\Mail;

use App\Models\SolicitudCredito;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-252: notifica al Coordinador Comercial que registró la solicitud
 * (SolicitudCredito::usuarioRegistra) cuando el cliente termina de cargar
 * todos los documentos requeridos de Etapa 1 — ver
 * DocumentRequestNotificationService::notificarCargaCompletaSiAplica().
 */
class CargaCompletaCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public SolicitudCredito $solicitud;
    public string $origen;
    public string $urlValidacion;

    public function __construct(SolicitudCredito $solicitud, string $origen, string $urlValidacion)
    {
        $this->solicitud = $solicitud;
        $this->origen = $origen;
        $this->urlValidacion = $urlValidacion;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cliente completó el cargue de documentos - Solicitud ' . $this->solicitud->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.carga_completa_coordinador',
        );
    }
}
