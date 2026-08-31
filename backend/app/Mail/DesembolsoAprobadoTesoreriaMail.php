<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * SCRUM-219: notifica a los usuarios con Rol Tesorería que Gerencia aprobó
 * el Registro de Operación de Desembolso en CYF — pueden efectuar y
 * registrar la transferencia bancaria (estado 'ejecucion_transferencia',
 * pantalla propia desde SCRUM-224: transferenciaBancaria()).
 *
 * Rebote 2026-08-31 (SCRUM-303, comentario de Juan): mismo patrón que
 * SCRUM-294 — faltaban cliente, tipo de crédito, quién decidió y
 * fecha/hora de la decisión en el resumen del correo.
 */
class DesembolsoAprobadoTesoreriaMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public string $tipoCredito;
    public string $nombreGerente;
    public Carbon $fechaHoraDecision;
    public string $urlIngreso;

    public function __construct(
        CreditoOrdinario $credito,
        string $nombreCliente,
        string $tipoCredito,
        string $nombreGerente,
        Carbon $fechaHoraDecision,
        string $urlIngreso
    ) {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->tipoCredito = $tipoCredito;
        $this->nombreGerente = $nombreGerente;
        $this->fechaHoraDecision = $fechaHoraDecision;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Operación de desembolso aprobada - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.desembolso_aprobado_tesoreria');
    }

    public function attachments(): array
    {
        return [];
    }
}
