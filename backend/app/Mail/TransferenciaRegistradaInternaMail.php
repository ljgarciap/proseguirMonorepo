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
 * SCRUM-224 (§9.2): notifica a todos los usuarios activos con rol Gerente y
 * Coordinador Comercial que Tesorería registró la transferencia bancaria —
 * enlace autenticado que respeta los permisos del usuario que ingresa
 * (GestionCreditoController::urlIngresoSistema()).
 *
 * SCRUM-307: incluye el comprobante de transferencia adjunto, igual que
 * TransferenciaRealizadaClienteMail — el ticket exige el adjunto "en ambas
 * notificaciones", no solo en la del cliente.
 *
 * Rebote 2026-08-31 (SCRUM-307, comentario de Juan): mismo gap que en
 * TransferenciaRealizadaClienteMail — faltaban tipo de documento del
 * cliente, fecha de solicitud, tipo de crédito, y toda la sección
 * "Información del beneficiario" (titular, documento, entidad, tipo y
 * número de cuenta) en el correo interno.
 */
class TransferenciaRegistradaInternaMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public array $transferencia;
    public string $urlIngreso;
    public string $tipoDocumentoCliente;
    public ?Carbon $fechaSolicitud;
    public string $tipoCredito;

    public function __construct(
        CreditoOrdinario $credito,
        array $transferencia,
        string $urlIngreso,
        string $tipoDocumentoCliente,
        ?Carbon $fechaSolicitud,
        string $tipoCredito
    ) {
        $this->credito = $credito;
        $this->transferencia = $transferencia;
        $this->urlIngreso = $urlIngreso;
        $this->tipoDocumentoCliente = $tipoDocumentoCliente;
        $this->fechaSolicitud = $fechaSolicitud;
        $this->tipoCredito = $tipoCredito;
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
