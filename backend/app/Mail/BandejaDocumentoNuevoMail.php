<?php

namespace App\Mail;

use App\Models\DocumentEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-311 (§6.1/§7.1): notifica al área del primer paso de la ruta que
 * hay un documento nuevo pendiente de revisión. $envio debe llegar con
 * sender, category, priority, steps.area y files cargados.
 */
class BandejaDocumentoNuevoMail extends Mailable
{
    use Queueable, SerializesModels;

    public DocumentEnvio $envio;
    public string $rolOrigen;
    public string $urlIngreso;

    public function __construct(DocumentEnvio $envio, string $rolOrigen, string $urlIngreso)
    {
        $this->envio = $envio;
        $this->rolOrigen = $rolOrigen;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo documento pendiente de revisión - ' . $this->envio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bandeja_documento_nuevo');
    }

    public function attachments(): array
    {
        return $this->envio->files
            ->map(fn ($file) => Attachment::fromStorageDisk('public', $file->path)->as($file->original_name))
            ->all();
    }
}
