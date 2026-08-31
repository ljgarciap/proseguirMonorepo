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
 * SCRUM-311 (§6.5/§7.4): notifica al usuario origen y al área del siguiente
 * paso que un paso intermedio aprobó y la ruta continúa. $stepAprobado es
 * el paso recién procesado (con area y usuario cargados), $siguientePaso
 * el que queda pendiente (con area cargada). $envio debe llegar con
 * sender, steps.area, files. Un mismo mailable se envía dos veces (origen
 * y siguiente área) — no cambia contenido, solo destinatario.
 */
class BandejaDocumentoAprobadoIntermedioMail extends Mailable
{
    use Queueable, SerializesModels;

    public DocumentEnvio $envio;
    public DocumentEnvioStep $stepAprobado;
    public DocumentEnvioStep $siguientePaso;
    public string $rolOrigen;
    public string $urlIngreso;

    public function __construct(
        DocumentEnvio $envio,
        DocumentEnvioStep $stepAprobado,
        DocumentEnvioStep $siguientePaso,
        string $rolOrigen,
        string $urlIngreso
    ) {
        $this->envio = $envio;
        $this->stepAprobado = $stepAprobado;
        $this->siguientePaso = $siguientePaso;
        $this->rolOrigen = $rolOrigen;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento aprobado - Continúa en proceso - ' . $this->envio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bandeja_documento_aprobado_intermedio');
    }

    public function attachments(): array
    {
        return $this->envio->files
            ->map(fn ($file) => Attachment::fromStorageDisk('public', $file->path)->as($file->original_name))
            ->all();
    }
}
