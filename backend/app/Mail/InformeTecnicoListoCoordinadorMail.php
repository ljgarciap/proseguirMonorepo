<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use App\Services\ConfiguracionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-262 (§7.1): notifica al Coordinador Comercial asignado a la
 * solicitud (SolicitudCredito::usuarioRegistra — mismo campo que ya
 * reutiliza SCRUM-252, sin modelo de asignación nuevo) que el Ingeniero
 * registró la parte inicial del Informe Técnico y la solicitud está lista
 * para que continúe diligenciando sus secciones.
 */
class InformeTecnicoListoCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $urlAcceso;

    public function __construct(CreditoOrdinario $credito)
    {
        $this->credito = $credito;
        $this->urlAcceso = ConfiguracionService::urlIngresoSistema('/informes-tecnicos/' . $credito->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Informe técnico listo para continuar - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.informe_tecnico_listo_coordinador',
        );
    }
}
