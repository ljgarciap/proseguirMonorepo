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
 *
 * SCRUM-317 rebote (Juan, 2026-09-02): $fechaCambio llega en server time
 * (config('app.timezone') = 'UTC', ver config/app.php) — sin conversión, el
 * correo mostraba la hora 5h adelantada de Bogotá. Se guarda aparte
 * $fechaCambioBogota (America/Bogota) solo para el texto del correo;
 * $fechaCambio se deja intacto porque AuthController también lo usa para
 * el metadata de ActivityLog en server time, igual que el resto de la
 * auditoría del proyecto.
 */
class PasswordCambiadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $usuario;
    public Carbon $fechaCambio;
    public Carbon $fechaCambioBogota;

    public function __construct(User $usuario, Carbon $fechaCambio)
    {
        $this->usuario = $usuario;
        $this->fechaCambio = $fechaCambio;
        $this->fechaCambioBogota = $fechaCambio->copy()->setTimezone('America/Bogota');
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
