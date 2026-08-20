<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Resultado de la Formalización de Garantías (SCRUM-205, rol Operativo
 * desde SCRUM-237). A diferencia de GestionCreditoNotificacionMail, el
 * contenido es fijo (no redactado a mano por quien gestiona la pantalla) —
 * dos variantes según si todas las garantías quedaron aprobadas o si
 * alguna requiere ajustes.
 */
class FormalizacionGarantiasResultadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    /** @var array<int, array{garantia: string, resultado: string, observaciones: ?string}> */
    public array $detalleGarantias;
    public bool $requiereAjustes;
    public ?string $urlPortalCliente;

    public function __construct(CreditoOrdinario $credito, string $nombreCliente, array $detalleGarantias, bool $requiereAjustes)
    {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->detalleGarantias = $detalleGarantias;
        $this->requiereAjustes = $requiereAjustes;
        $this->urlPortalCliente = $requiereAjustes
            ? rtrim(env('FRONTEND_URL', config('app.url')), '/') . '/creditos'
            : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resultado de validación de garantías - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.formalizacion_garantias_resultado');
    }

    public function attachments(): array
    {
        return [];
    }
}
