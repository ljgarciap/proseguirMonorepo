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
 * SCRUM-258 (5.1/RF-07): notifica a cada usuario de Control Interno activo
 * (rol 'oficial_cumplimiento' — mismo rol del flujo de Listas Restrictivas y
 * SARLAFT, ver ListasRestrictivasSarlaftController) que la documentación de
 * un crédito Ordinario fue aprobada y ya puede validar listas y SARLAFT.
 * Sin modelo de asignación por crédito (decisión de Luis 2026-08-26) — se
 * envía a TODOS los usuarios activos con ese rol.
 */
class ListasSarlaftPendienteControlInternoMail extends Mailable
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
            subject: 'Solicitud pendiente para listas restrictivas y SARLAFT - ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.listas_sarlaft_pendiente_control_interno',
        );
    }
}
