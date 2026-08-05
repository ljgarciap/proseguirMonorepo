<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notificación de Gestión de Créditos (SCRUM-178): asunto y mensaje los
 * redacta el Coordinador Comercial en la pantalla de gestión, a diferencia
 * de las Mailable anteriores (SCRUM-128) que traían contenido fijo. Cubre
 * los 4 resultados: Garantías, SARLAFT desfavorable, Rechazada por Comité y
 * Pendiente por Comité.
 */
class GestionCreditoNotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $asuntoCorreo;
    public string $mensajeCorreo;

    public function __construct(CreditoOrdinario $credito, string $asunto, string $mensaje)
    {
        $this->credito = $credito;
        $this->asuntoCorreo = $asunto;
        $this->mensajeCorreo = $mensaje;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asuntoCorreo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.gestion_credito_notificacion');
    }

    public function attachments(): array
    {
        return [];
    }
}
