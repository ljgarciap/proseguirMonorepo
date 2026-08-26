<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-258 (5.1 Aprobar Documentos): notifica al cliente que la
 * documentación de Etapa 1 fue revisada y aprobada por el Coordinador
 * Comercial, e incluye — sin alteraciones (RF-04) — el comentario que
 * registró en la confirmación de la acción.
 */
class DocumentacionValidadaClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $comentario;

    public function __construct(CreditoOrdinario $credito, string $comentario)
    {
        $this->credito = $credito;
        $this->comentario = $comentario;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documentación validada - Su solicitud continúa en proceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.documentacion_validada_cliente',
        );
    }
}
