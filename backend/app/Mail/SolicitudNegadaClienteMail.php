<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-258 (5.3 Rechazar Solicitud, RF-09): notifica al cliente que su
 * solicitud fue negada en la revisión documental de Etapa 1, con el
 * comentario del Coordinador Comercial. SCRUM-257 renombró "Rechazado" a
 * "Negado" en las etiquetas visibles — este correo, nuevo, ya nace con el
 * wording correcto.
 */
class SolicitudNegadaClienteMail extends Mailable
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
            subject: 'Resultado de su solicitud de crédito - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud_negada_cliente',
        );
    }
}
