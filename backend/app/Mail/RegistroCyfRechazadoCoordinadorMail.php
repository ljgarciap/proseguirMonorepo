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
 * SCRUM-294 (NTF-03): notifica a los usuarios activos con Rol Coordinador
 * Comercial que Gerencia rechazó el Registro de Crédito en CYF, con las
 * observaciones que motivan el ajuste (obligatorias, validadas en
 * GestionCreditoController::aprobacionRegistroCyf() antes de guardar). El
 * botón lleva directo a la pantalla de registro-cyf (mismo destino que
 * FormalizacionGarantiasCoordinadorMail) porque el crédito vuelve a
 * 'pendiente_registro_cyf' para que Coordinador registre de nuevo. No se
 * envía NTF-01 en este resultado (solo aplica a la aprobación).
 *
 * Rebote 2026-08-31 (comentario de Juan): faltaban cliente, fecha de
 * registro CYF y radicado CYF en el resumen (las observaciones ya se
 * mostraban). $fechaRegistroCyf/$radicadoCyf llegan capturados por el
 * controlador ANTES de limpiarlos — el propio rechazo pone esos dos campos
 * en null sobre $credito antes de guardar, así que leerlos del modelo acá
 * mostraría vacío justo el dato que el correo necesita comunicar.
 */
class RegistroCyfRechazadoCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public ?Carbon $fechaRegistroCyf;
    public string $radicadoCyf;
    public string $nombreGerente;
    public string $observaciones;
    public string $urlIngreso;

    public function __construct(
        CreditoOrdinario $credito,
        string $nombreCliente,
        ?Carbon $fechaRegistroCyf,
        string $radicadoCyf,
        string $nombreGerente,
        string $observaciones,
        string $urlIngreso
    ) {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->fechaRegistroCyf = $fechaRegistroCyf;
        $this->radicadoCyf = $radicadoCyf;
        $this->nombreGerente = $nombreGerente;
        $this->observaciones = $observaciones;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registro de Crédito en CYF rechazado - Solicitud ' . $this->credito->numero_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registro_cyf_rechazado_coordinador');
    }

    public function attachments(): array
    {
        return [];
    }
}
