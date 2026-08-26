<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-267 (Notificación 1): notifica al Coordinador Comercial responsable
 * de la solicitud (SolicitudCredito::usuarioRegistra) que Control Interno
 * finalizó la validación en Listas Restrictivas y SARLAFT con concepto
 * favorable — ver SarlaftValidacionNotificationService.
 */
class SarlaftFavorableCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $urlValidacion;

    public function __construct(CreditoOrdinario $credito, string $urlValidacion)
    {
        $this->credito = $credito;
        $this->urlValidacion = $urlValidacion;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Validación en Listas Restrictivas y SARLAFT finalizada - Análisis Financiero requerido',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sarlaft_favorable_coordinador',
        );
    }
}
