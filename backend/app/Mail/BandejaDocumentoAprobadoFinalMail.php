<?php

namespace App\Mail;

use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-311 (§6.4/§7.3): notifica al usuario origen que el último paso de
 * la ruta aprobó el documento (ruta finalizada). $step es el paso final
 * recién procesado (con area y usuario cargados); $envio debe llegar con
 * sender, steps.area, files.
 */
class BandejaDocumentoAprobadoFinalMail extends Mailable
{
    use Queueable, SerializesModels;

    public DocumentEnvio $envio;
    public DocumentEnvioStep $step;
    public string $rolOrigen;
    public string $urlIngreso;

    public function __construct(DocumentEnvio $envio, DocumentEnvioStep $step, string $rolOrigen, string $urlIngreso)
    {
        $this->envio = $envio;
        $this->step = $step;
        $this->rolOrigen = $rolOrigen;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento aprobado - Ruta finalizada - ' . $this->envio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bandeja_documento_aprobado_final');
    }

    public function attachments(): array
    {
        return $this->envio->files
            ->map(fn ($file) => Attachment::fromStorageDisk('public', $file->path)->as($file->original_name))
            ->all();
    }
}
