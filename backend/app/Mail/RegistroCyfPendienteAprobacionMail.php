<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-288: el Coordinador Comercial guardó fecha + radicado del Registro
 * de Crédito en CYF — se notifica a los usuarios activos con Rol Gerente
 * para que revisen y aprueben el registro. Disparado desde
 * GestionCreditoController::registroCyf() en el mismo punto donde el
 * crédito pasa a 'aprobacion_registro_cyf' (ese guard de estado es la única
 * protección contra duplicados, mismo criterio que
 * RegistroCyfAprobadoMail/notificarPorRol()).
 */
class RegistroCyfPendienteAprobacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public string $nombreCoordinador;
    public string $urlAcceso;

    public function __construct(CreditoOrdinario $credito, string $nombreCliente, string $nombreCoordinador, string $urlAcceso)
    {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->nombreCoordinador = $nombreCoordinador;
        $this->urlAcceso = $urlAcceso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Crédito registrado en CYF - Solicitud ' . $this->credito->numero_solicitud . ' pendiente de aprobación',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registro_cyf_pendiente_aprobacion');
    }

    public function attachments(): array
    {
        return [];
    }
}
