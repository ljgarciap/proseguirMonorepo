<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-224 (§9.2): notifica a todos los usuarios activos con rol Gerente y
 * Coordinador Comercial que Tesorería registró la transferencia bancaria —
 * enlace autenticado que respeta los permisos del usuario que ingresa
 * (GestionCreditoController::urlIngresoSistema()).
 */
class TransferenciaRegistradaInternaMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public array $transferencia;
    public string $urlIngreso;

    public function __construct(CreditoOrdinario $credito, array $transferencia, string $urlIngreso)
    {
        $this->credito = $credito;
        $this->transferencia = $transferencia;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Transferencia bancaria registrada - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.transferencia_registrada_interna');
    }

    public function attachments(): array
    {
        return [];
    }
}
