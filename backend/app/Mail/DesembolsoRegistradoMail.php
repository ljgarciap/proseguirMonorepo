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
 * SCRUM-215: notifica a los usuarios con Rol Gerente que Operativo registró
 * la Operación de Desembolso en CYF — queda pendiente su aprobación
 * (SCRUM-219).
 *
 * Rebote 2026-08-31 (SCRUM-299, comentario de Juan): el asunto y el cuerpo
 * no coincidían con §9 del ticket — faltaban cliente, radicado CYF,
 * registrado por y fecha/hora del registro, y el asunto no identificaba la
 * solicitud como pendiente de validación/aprobación.
 */
class DesembolsoRegistradoMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public string $usuarioOperativo;
    public Carbon $fechaHoraRegistro;
    public string $urlIngreso;

    public function __construct(
        CreditoOrdinario $credito,
        string $nombreCliente,
        string $usuarioOperativo,
        Carbon $fechaHoraRegistro,
        string $urlIngreso
    ) {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->usuarioOperativo = $usuarioOperativo;
        $this->fechaHoraRegistro = $fechaHoraRegistro;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Operación de desembolso pendiente de validación y aprobación - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.desembolso_registrado');
    }

    public function attachments(): array
    {
        return [];
    }
}
