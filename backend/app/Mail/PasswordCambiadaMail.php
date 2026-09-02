<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SCRUM-317: confirmación al correo registrado del usuario tras un cambio
 * de contraseña exitoso desde CONFIGURACIÓN > CAMBIO DE CONTRASEÑA (hoy la
 * pantalla "Mi Perfil"). Disparado desde AuthController::changePassword(),
 * best-effort — la contraseña ya quedó actualizada aunque el envío falle
 * (ver flujo alterno "Falla en el envío del correo" del ticket).
 */
class PasswordCambiadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $usuario;
    public Carbon $fechaCambio;

    public function __construct(User $usuario, Carbon $fechaCambio)
    {
        $this->usuario = $usuario;
        $this->fechaCambio = $fechaCambio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de cambio de contraseña – Sistema de Gestión de Liquidez',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password_cambiada');
    }

    public function attachments(): array
    {
        return [];
    }
}
