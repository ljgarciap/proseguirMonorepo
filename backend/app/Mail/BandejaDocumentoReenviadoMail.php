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
 * SCRUM-311 (rebote 2026-09-01, Juan): notifica al área del paso que
 * vuelve a quedar pendiente cuando el remitente reenvía un documento
 * previamente devuelto — gap señalado en el comentario de cierre original
 * (acción "reenviar" fuera del alcance listado, no disparaba nada). $step
 * es el paso reenviado (con area cargada, ya vuelto a 'pendiente');
 * $motivoDevolucion/$fechaDevolucion se capturan ANTES de limpiar el paso
 * (el controlador los pone en null al reenviar). $envio debe llegar con
 * sender, steps.area, files.
 */
class BandejaDocumentoReenviadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public DocumentEnvio $envio;
    public DocumentEnvioStep $step;
    public ?string $motivoDevolucion;
    public $fechaDevolucion;
    public string $notaReenvio;
    public string $rolOrigen;
    public string $urlIngreso;

    public function __construct(
        DocumentEnvio $envio,
        DocumentEnvioStep $step,
        ?string $motivoDevolucion,
        $fechaDevolucion,
        string $notaReenvio,
        string $rolOrigen,
        string $urlIngreso
    ) {
        $this->envio = $envio;
        $this->step = $step;
        $this->motivoDevolucion = $motivoDevolucion;
        $this->fechaDevolucion = $fechaDevolucion;
        $this->notaReenvio = $notaReenvio;
        $this->rolOrigen = $rolOrigen;
        $this->urlIngreso = $urlIngreso;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento reenviado - Pendiente de revisión - ' . $this->envio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bandeja_documento_reenviado');
    }

    public function attachments(): array
    {
        return $this->envio->files
            ->map(fn ($file) => Attachment::fromStorageDisk('public', $file->path)->as($file->original_name))
            ->all();
    }
}
