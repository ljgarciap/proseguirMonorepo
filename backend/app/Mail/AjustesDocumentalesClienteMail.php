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
 * SCRUM-339: la pantalla "Solicitud de Documentos" ahora sí itemiza por
 * documento (ver docs/specs/scrum-339-*) — $documentos trae los nombres
 * solicitados (catálogo o ad-hoc) y se muestra como tabla antes del bloque
 * de observaciones. Viene vacío desde el flujo de Constructor, que todavía
 * no pasa por esa pantalla (fuera de alcance de SCRUM-339); en ese caso el
 * correo se ve igual que antes (solo el párrafo de observación).
 */
class AjustesDocumentalesClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public CreditoOrdinario $credito;
    public string $comentario;
    public string $urlAcceso;
    public array $documentos;

    public function __construct(CreditoOrdinario $credito, string $comentario, array $documentos = [])
    {
        $this->credito = $credito;
        $this->comentario = $comentario;
        $this->documentos = $documentos;
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
