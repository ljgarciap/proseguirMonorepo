<?php

namespace App\Mail;

use App\Models\CreditoOrdinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notificación de Gestión de Créditos (SCRUM-178/268): asunto y mensaje los
 * redacta el Coordinador Comercial en la pantalla de gestión (precargados
 * por escenario — GestionCreditoController::PLANTILLAS/plantillaSugerida(),
 * RN-02/RN-03), a diferencia de las Mailable anteriores (SCRUM-128) que
 * traían contenido fijo. Cubre los 4 resultados: Garantías, SARLAFT
 * desfavorable, Rechazada por Comité y Pendiente por Comité.
 *
 * §4 "Composición del correo" del ticket: el saludo, el banner de estado,
 * la lista de documentos y el botón de acción son componentes automáticos
 * que arma la vista a partir de $resultado/$documentos/$urlAccion — nunca
 * se construyen a partir del texto libre del Coordinador ($mensajeCorreo),
 * así RN-04 (no reemplazar lo que el usuario dejó diligenciado) y RN-11/
 * RN-12 (no exponer información sensible/interna) no dependen de que nadie
 * la escriba a mano cada vez.
 */
class GestionCreditoNotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    private const BANNERS = [
        'aprobada_garantias' => ['texto' => 'SOLICITUD APROBADA — GESTIÓN DE GARANTÍAS', 'color' => '#15803d'],
        'sarlaft_desfavorable' => ['texto' => 'SOLICITUD FINALIZADA', 'color' => '#b91c1c'],
        'rechazada_comite' => ['texto' => 'SOLICITUD NO APROBADA', 'color' => '#b91c1c'],
        'pendiente_comite' => ['texto' => 'SOLICITUD PENDIENTE', 'color' => '#b45309'],
    ];

    public CreditoOrdinario $credito;
    public string $asuntoCorreo;
    public string $mensajeCorreo;
    public string $resultado;
    public array $documentos;
    public ?string $urlAccion;

    public function __construct(
        CreditoOrdinario $credito,
        string $asunto,
        string $mensaje,
        string $resultado,
        array $documentos = [],
        ?string $urlAccion = null,
    ) {
        $this->credito = $credito;
        $this->asuntoCorreo = $asunto;
        $this->mensajeCorreo = $mensaje;
        $this->resultado = $resultado;
        $this->documentos = $documentos;
        $this->urlAccion = $urlAccion;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asuntoCorreo);
    }

    public function content(): Content
    {
        $banner = self::BANNERS[$this->resultado] ?? null;

        return new Content(view: 'emails.gestion_credito_notificacion', with: [
            'bannerTexto' => $banner['texto'] ?? null,
            'bannerColor' => $banner['color'] ?? '#1e3a8a',
            // RN-08: el botón solo se llama "Diligenciar garantías" para el
            // escenario de garantías — en pendiente_comite con documentos
            // requeridos lleva al mismo portal, pero es una acción genérica
            // de "entrar a cargar lo pedido", no la formalización de la
            // aprobación del Comité.
            'botonTexto' => $this->resultado === 'aprobada_garantias' ? 'Diligenciar garantías' : 'Ingresar a Mis Créditos',
            // RN-11: aviso explícito de que el correo no expone hallazgos
            // ni observaciones internas — solo aplica al escenario SARLAFT.
            'mostrarAvisoSarlaft' => $this->resultado === 'sarlaft_desfavorable',
        ]);
    }

    public function attachments(): array
    {
        return [];
    }
}
