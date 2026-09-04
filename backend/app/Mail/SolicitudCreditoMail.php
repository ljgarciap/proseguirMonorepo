<?php

namespace App\Mail;

use App\Models\SolicitudCredito;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudCreditoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;
    public $documentosRequeridos;
    public $urlIngreso;
    public string $usuarioAcceso;
    public string $claveAcceso;

    /**
     * Create a new message instance.
     *
     * SCRUM-244 (feedback QA 2026-08-26): $usuarioAcceso/$claveAcceso son
     * las credenciales REALES de la cuenta de portal del cliente
     * (SolicitudCreditoController::store() ya las calcula al aprovisionar
     * el User). Antes de este fix esas credenciales se armaban como texto
     * libre en el frontend y se concatenaban dentro de
     * $solicitud->mensaje_notificacion (SCRUM-173) — dependían de un orden
     * de llenado del formulario que no siempre ocurría (bug reportado por
     * QA: correo real sin URL/usuario/clave). Ahora son un componente
     * automático de la plantilla, igual que $urlIngreso (botón "Ingresar a
     * la plataforma", RF-07) — nunca dependen del texto editable por el
     * Director de Crédito.
     *
     * SCRUM-328: $usuarioAcceso es $cliente->numero_documento, NO su
     * correo — AuthController::login() solo acepta numero_documento (no
     * hay login por email), así que mostrar el correo como "Usuario:" era
     * un dato que nunca servía para entrar a la plataforma.
     * $claveAcceso sigue siendo numero_documento sin guiones.
     */
    public function __construct(
        SolicitudCredito $solicitud,
        array $documentosRequeridos,
        string $usuarioAcceso,
        string $claveAcceso,
        ?string $urlIngreso = null,
    ) {
        $this->solicitud = $solicitud;
        $this->documentosRequeridos = $documentosRequeridos;
        $this->usuarioAcceso = $usuarioAcceso;
        $this->claveAcceso = $claveAcceso;
        $this->urlIngreso = $urlIngreso ?? \App\Services\ConfiguracionService::urlIngresoSistema();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->solicitud->asunto_notificacion,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud_credito',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
