<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-294 (NTF-02): notifica a los usuarios activos con Rol Coordinador
 * Comercial que Gerencia aprobó el Registro de Crédito en CYF — informativa,
 * independiente de NTF-01 (RegistroCyfAprobadoMail, a Operativo). Disparada
 * desde GestionCreditoController::aprobacionRegistroCyf() en la misma
 * transición a 'desembolso_ingreso'.
 */
class RegistroCyfAprobadoCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $urlIngreso;

    public function __construct(CreditoOrdinario $credito, string $urlIngreso)
    {
        $this->credito = $credito;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registro de Crédito en CYF aprobado - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registro_cyf_aprobado_coordinador');
    }

    public function attachments(): array
    {
        return [];
    }
}
