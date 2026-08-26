<?php

namespace App\Mail\Transport;

use App\Services\ConfiguracionService;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport de correo vía Microsoft Graph API (`/users/{buzón}/sendMail`),
 * autenticado con OAuth2 client-credentials (App Registration de Azure AD)
 * — no SMTP. Exchange Online ya no acepta SMTP AUTH básico para la mayoría
 * de tenants; esta es la vía soportada por Microsoft para enviar correo
 * transaccional desde una app sin credenciales de usuario.
 *
 * Credenciales en la tabla `configuraciones` (grupo 'email', es_secreto en
 * el secreto), NO en `.env` en tiempo de ejecución — mismo criterio que
 * GEMINI_API_KEY/MISTRAL_API_KEY (ConfiguracionSeeder las siembra leyendo
 * `env()` en el deploy, pero el código de negocio siempre lee de
 * ConfiguracionService, nunca de env() directo, para poder rotar la clave
 * desde /configuraciones sin redeploy):
 * - GRAPH_MAIL_TENANT_ID
 * - GRAPH_MAIL_CLIENT_ID
 * - GRAPH_MAIL_CLIENT_SECRET (es_secreto)
 * - GRAPH_MAIL_FROM_ADDRESS — buzón autorizado a enviar (restringido en
 *   Azure vía Application Access Policy — ver docs del setup); Graph
 *   rechaza el envío si el token no tiene permiso para actuar como este
 *   buzón, aunque el token sea válido.
 *
 * El token de acceso se cachea (no se pide uno nuevo por cada correo).
 */
class GraphMailTransport extends AbstractTransport
{
    private const CACHE_KEY = 'graph_mail_access_token';

    // Los tokens de Microsoft Identity Platform duran ~3600s; se cachea por
    // menos para nunca usar uno vencido por un margen de reloj/latencia.
    private const CACHE_TTL_SECONDS = 3300;

    public function __construct(private ClientInterface $http = new Client())
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $tenantId = ConfiguracionService::get('GRAPH_MAIL_TENANT_ID');
        $clientId = ConfiguracionService::get('GRAPH_MAIL_CLIENT_ID');
        $clientSecret = ConfiguracionService::get('GRAPH_MAIL_CLIENT_SECRET');
        $fromAddress = ConfiguracionService::get('GRAPH_MAIL_FROM_ADDRESS');

        if (!$tenantId || !$clientId || !$clientSecret || !$fromAddress) {
            throw new TransportException(
                'GraphMailTransport: faltan credenciales en configuraciones (grupo email) — ' .
                'GRAPH_MAIL_TENANT_ID/CLIENT_ID/CLIENT_SECRET/FROM_ADDRESS.'
            );
        }

        $token = $this->obtenerAccessToken($tenantId, $clientId, $clientSecret);

        try {
            $this->http->request('POST', "https://graph.microsoft.com/v1.0/users/{$fromAddress}/sendMail", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->construirPayload($email, $fromAddress),
            ]);
        } catch (\Throwable $e) {
            Log::error('GraphMailTransport: fallo al enviar vía Graph API: ' . $e->getMessage());
            throw new TransportException('GraphMailTransport: fallo al enviar el correo — ' . $e->getMessage(), 0, $e);
        }
    }

    private function obtenerAccessToken(string $tenantId, string $clientId, string $clientSecret): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($tenantId, $clientId, $clientSecret) {
            $response = $this->http->request('POST', "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (empty($data['access_token'])) {
                throw new TransportException('GraphMailTransport: la respuesta de token no incluyó access_token.');
            }

            return $data['access_token'];
        });
    }

    private function construirPayload(Email $email, string $fromAddress): array
    {
        $direccion = fn (Address $a) => ['emailAddress' => array_filter([
            'address' => $a->getAddress(),
            'name' => $a->getName() ?: null,
        ])];

        return [
            'message' => array_filter([
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
                ],
                'toRecipients' => array_map($direccion, $email->getTo()),
                'ccRecipients' => array_map($direccion, $email->getCc()),
                'bccRecipients' => array_map($direccion, $email->getBcc()),
                'replyTo' => array_map($direccion, $email->getReplyTo()),
                'from' => ['emailAddress' => ['address' => $fromAddress]],
            ]),
            'saveToSentItems' => true,
        ];
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
