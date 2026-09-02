<?php

namespace App\Mail;

use App\Models\Visita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-316: el Gerente (u otro usuario habilitado para Registro de Visita
 * a Cliente) marca "¿Requiere crédito?" = Sí al guardar una visita — se
 * avisa a todos los usuarios activos con rol Coordinador Comercial para que
 * formalicen la solicitud de crédito en el sistema. Disparado desde
 * VisitaController::store(), best-effort (un fallo de envío no revierte la
 * visita ya guardada), mismo criterio que notificarPorRol() en
 * GestionCreditoController.
 */
class VisitaCreditoDetectadoCoordinadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public Visita $visita;
    public string $nombreGerente;
    public string $urlAcceso;

    public function __construct(Visita $visita, string $nombreGerente, string $urlAcceso)
    {
        $this->visita = $visita;
        $this->nombreGerente = $nombreGerente;
        $this->urlAcceso = $urlAcceso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva solicitud de crédito identificada en visita – ' . $this->visita->cliente->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.visita_credito_detectado_coordinador');
    }

    public function attachments(): array
    {
        return [];
    }
}
