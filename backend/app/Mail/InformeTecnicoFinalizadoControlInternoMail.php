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
 * SCRUM-262 (§7.2): notifica a cada usuario de Control Interno activo (rol
 * 'oficial_cumplimiento', mismo criterio "todos los activos" de SCRUM-258 —
 * no hay modelo de asignación por crédito) que el Coordinador Comercial
 * finalizó el Informe Técnico y la solicitud está lista para validación de
 * Listas Restrictivas y SARLAFT.
 */
class InformeTecnicoFinalizadoControlInternoMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $urlAcceso;

    public function __construct(CreditoOrdinario $credito)
    {
        $this->credito = $credito;
        $this->urlAcceso = ConfiguracionService::urlIngresoSistema('/listas-sarlaft/' . $credito->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Informe técnico finalizado - Validación de listas y SARLAFT pendiente - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.informe_tecnico_finalizado_control_interno',
        );
    }
}
