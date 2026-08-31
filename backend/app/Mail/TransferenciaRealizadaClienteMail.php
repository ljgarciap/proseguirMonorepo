<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * SCRUM-224 (§9.1): confirma al cliente que Tesorería ejecutó y registró la
 * transferencia bancaria del desembolso de su crédito — incluye el
 * comprobante adjunto y la cuenta destino enmascarada, nunca el número
 * completo (§12 Requisitos de seguridad y control).
 *
 * Rebote 2026-08-31 (SCRUM-307, comentario de Juan): faltaban tipo de
 * documento del cliente, fecha de solicitud y tipo de crédito (sección
 * "Información del crédito" del ticket) — el resto de la sección ya
 * llegaba en $credito/$transferencia.
 */
class TransferenciaRealizadaClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public array $transferencia;
    public string $cuentaEnmascarada;
    public string $tipoDocumentoCliente;
    public ?Carbon $fechaSolicitud;
    public string $tipoCredito;

    public function __construct(
        CreditoOrdinario $credito,
        array $transferencia,
        string $cuentaEnmascarada,
        string $tipoDocumentoCliente,
        ?Carbon $fechaSolicitud,
        string $tipoCredito
    ) {
        $this->credito = $credito;
        $this->transferencia = $transferencia;
        $this->cuentaEnmascarada = $cuentaEnmascarada;
        $this->tipoDocumentoCliente = $tipoDocumentoCliente;
        $this->fechaSolicitud = $fechaSolicitud;
        $this->tipoCredito = $tipoCredito;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Transferencia bancaria realizada - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.transferencia_realizada_cliente');
    }

    public function attachments(): array
    {
        $path = $this->transferencia['comprobante_transferencia'] ?? null;
        if (!$path) {
            return [];
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';

        return [
            Attachment::fromStorageDisk('public', $path)
                ->as('comprobante_transferencia_' . $this->credito->numero_solicitud . '.' . $extension),
        ];
    }
}
