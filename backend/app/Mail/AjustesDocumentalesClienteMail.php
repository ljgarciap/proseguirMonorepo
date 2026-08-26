<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use App\Services\ConfiguracionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-258 (5.2 Solicitar Completar Soportes): notifica al cliente que el
 * Coordinador Comercial requiere ajustes en la documentación de Etapa 1.
 *
 * Alcance acotado (decisión de Luis 2026-08-26, mismo criterio "mínimo
 * viable" de SCRUM-252): la spec (RF-08, §7) pide "cada documento que
 * requiere ajuste y la observación asociada", pero Etapa 1 hoy no tiene una
 * pantalla de revisión POR documento — la acción "Solicitar Completar
 * Soportes" es a nivel de toda la solicitud, con un único comentario de
 * auditoría (RF-03). El correo usa ese comentario como la observación;
 * itemizar por documento requeriría una pantalla nueva de revisión
 * individual, fuera de alcance de este ticket.
 */
class AjustesDocumentalesClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $comentario;
    public string $urlAcceso;

    public function __construct(CreditoOrdinario $credito, string $comentario)
    {
        $this->credito = $credito;
        $this->comentario = $comentario;
        $this->urlAcceso = ConfiguracionService::urlIngresoSistema('/creditos/' . $credito->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ajustes requeridos en la documentación de su solicitud de crédito',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ajustes_documentales_cliente',
        );
    }
}
