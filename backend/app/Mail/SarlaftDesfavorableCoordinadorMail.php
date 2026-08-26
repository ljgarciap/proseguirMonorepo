<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-128: notifica al Coordinador Comercial que un crédito fue rechazado
 * por concepto SARLAFT desfavorable.
 *
 * SCRUM-267 (Notificación 2): destinatario y contenido actualizados —
 * antes se enviaba a TODOS los usuarios con rol coordinador_comercial;
 * ahora va al responsable de la solicitud (SolicitudCredito::
 * usuarioRegistra, mismo criterio ya establecido en SCRUM-252) e incluye
 * los datos dinámicos y el enlace de acción que pide la spec — ver
 * SarlaftValidacionNotificationService.
 */
class SarlaftDesfavorableCoordinadorMail extends Mailable
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
            subject: 'Concepto desfavorable en Listas Restrictivas y SARLAFT - Gestión de Crédito requerida',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sarlaft_desfavorable_coordinador',
        );
    }
}
