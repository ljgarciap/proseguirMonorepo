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
 * SCRUM-258 (5.1/RF-06): notifica a cada Ingeniero activo que la
 * documentación de un crédito Constructor fue aprobada y ya puede
 * diligenciar el Informe Técnico. Sin modelo de asignación por crédito
 * (decisión de Luis 2026-08-26) — se envía a TODOS los usuarios activos con
 * rol 'ingeniero', ver ValidacionDocumentalNotificationService.
 */
class InformeTecnicoPendienteIngenieroMail extends Mailable
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
            subject: 'Solicitud pendiente para informe técnico - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.informe_tecnico_pendiente_ingeniero',
        );
    }
}
