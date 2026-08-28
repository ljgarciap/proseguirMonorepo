<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-280: el cliente completó el cargue de todos los documentos
 * obligatorios de Etapa 5 (Formalización de Garantías) — se notifica a los
 * usuarios activos con Rol Operativo para que validen y registren el
 * resultado. Disparado desde
 * ClientUploadController::habilitarFormalizacionGarantiasSiAplica() en el
 * mismo punto donde el crédito pasa a 'pendiente_formalizacion_garantias'
 * (ese guard de estado es la única protección contra duplicados, mismo
 * criterio que el resto de correos de esta etapa — ver
 * GestionCreditoController::notificarPorRol()).
 */
class FormalizacionGarantiasPendienteOperativoMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public string $urlAcceso;

    public function __construct(CreditoOrdinario $credito, string $nombreCliente, string $urlAcceso)
    {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->urlAcceso = $urlAcceso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documentos de Formalización de Garantías listos para validación - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.formalizacion_garantias_pendiente_operativo');
    }

    public function attachments(): array
    {
        return [];
    }
}
