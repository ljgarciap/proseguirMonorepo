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
 * SCRUM-211: notifica a los usuarios con Rol Operativo que Gerencia aprobó
 * el Registro de Crédito en CYF — pueden continuar con el Ingreso de la
 * Operación de Desembolso CYF (SCRUM-215).
 *
 * Rebote 2026-08-31 (SCRUM-294, comentario de Juan): faltaban cliente,
 * fecha de registro CYF y radicado CYF en el resumen — $fechaRegistroCyf y
 * $radicadoCyf se reciben ya capturados por el controlador (no se leen de
 * $credito porque el flujo de rechazo, en el mismo endpoint, los limpia
 * antes de guardar).
 */
class RegistroCyfAprobadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $nombreCliente;
    public ?Carbon $fechaRegistroCyf;
    public string $radicadoCyf;
    public string $nombreGerente;
    public string $urlIngreso;

    public function __construct(
        CreditoOrdinario $credito,
        string $nombreCliente,
        ?Carbon $fechaRegistroCyf,
        string $radicadoCyf,
        string $nombreGerente,
        string $urlIngreso
    ) {
        $this->credito = $credito;
        $this->nombreCliente = $nombreCliente;
        $this->fechaRegistroCyf = $fechaRegistroCyf;
        $this->radicadoCyf = $radicadoCyf;
        $this->nombreGerente = $nombreGerente;
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
        return new Content(view: 'emails.registro_cyf_aprobado');
    }

    public function attachments(): array
    {
        return [];
    }
}
