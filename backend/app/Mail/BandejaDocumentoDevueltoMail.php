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
 * SCRUM-311 (§6.2/§7.2): notifica al usuario origen que el paso actual
 * devolvió el documento. $step es el paso recién marcado 'devuelto' (con
 * area cargada); $envio debe llegar con sender, steps.area, files.
 */
class BandejaDocumentoDevueltoMail extends Mailable
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
            subject: 'Documento devuelto - ' . $this->envio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bandeja_documento_devuelto');
    }

    public function attachments(): array
    {
        return $this->envio->files
            ->map(fn ($file) => Attachment::fromStorageDisk('public', $file->path)->as($file->original_name))
            ->all();
    }
}
