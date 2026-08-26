<?php

namespace Tests\Unit;

use App\Mail\Transport\GraphMailTransport;
use App\Models\Configuracion;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * GraphMailTransport (setup de correo vía Microsoft Graph API, 2026-08-26):
 * Guzzle mockeado — nunca pega contra login.microsoftonline.com/graph.microsoft.com
 * de verdad. Verifica la forma del request (endpoint de token, endpoint de
 * sendMail, payload), no la integración real (esa se corre manualmente una
 * vez con credenciales reales, fuera de la suite automática).
 */
class GraphMailTransportTest extends TestCase
{
    use RefreshDatabase;

    private function setConfig(): void
    {
        foreach ([
            'GRAPH_MAIL_TENANT_ID' => 'tenant-123',
            'GRAPH_MAIL_CLIENT_ID' => 'client-456',
            'GRAPH_MAIL_CLIENT_SECRET' => 'secret-789',
            'GRAPH_MAIL_FROM_ADDRESS' => 'coordinadorcomercial@proseguirliquidez.com',
        ] as $clave => $valor) {
            Configuracion::create(['clave' => $clave, 'valor' => $valor, 'descripcion' => $clave, 'grupo' => 'email', 'es_secreto' => false]);
        }
    }

    private function email(): Email
    {
        return (new Email())
            ->from('coordinadorcomercial@proseguirliquidez.com')
            ->to('cliente@test.com')
            ->subject('Asunto de prueba')
            ->html('<p>Contenido de prueba</p>');
    }

    public function test_falla_con_mensaje_claro_si_faltan_credenciales(): void
    {
        $transport = new GraphMailTransport(new Client());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/faltan credenciales/');

        $transport->send($this->email());
    }

    public function test_pide_token_y_envia_via_graph_con_el_payload_correcto(): void
    {
        Cache::forget('graph_mail_access_token');
        $this->setConfig();

        $requests = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'fake-token-abc'])),
            new Response(202),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $transport = new GraphMailTransport($client);
        $transport->send($this->email());

        $this->assertCount(2, $requests);

        $tokenReq = $requests[0]['request'];
        $this->assertSame('POST', $tokenReq->getMethod());
        $this->assertStringContainsString('login.microsoftonline.com/tenant-123/oauth2/v2.0/token', (string) $tokenReq->getUri());
        parse_str((string) $tokenReq->getBody(), $tokenBody);
        $this->assertSame('client-456', $tokenBody['client_id']);
        $this->assertSame('secret-789', $tokenBody['client_secret']);
        $this->assertSame('client_credentials', $tokenBody['grant_type']);
        $this->assertSame('https://graph.microsoft.com/.default', $tokenBody['scope']);

        $sendReq = $requests[1]['request'];
        $this->assertSame('POST', $sendReq->getMethod());
        $this->assertStringContainsString('graph.microsoft.com/v1.0/users/coordinadorcomercial@proseguirliquidez.com/sendMail', (string) $sendReq->getUri());
        $this->assertSame('Bearer fake-token-abc', $sendReq->getHeaderLine('Authorization'));

        $payload = json_decode((string) $sendReq->getBody(), true);
        $this->assertSame('Asunto de prueba', $payload['message']['subject']);
        $this->assertSame('HTML', $payload['message']['body']['contentType']);
        $this->assertStringContainsString('Contenido de prueba', $payload['message']['body']['content']);
        $this->assertSame('cliente@test.com', $payload['message']['toRecipients'][0]['emailAddress']['address']);
        $this->assertSame('coordinadorcomercial@proseguirliquidez.com', $payload['message']['from']['emailAddress']['address']);
        $this->assertTrue($payload['saveToSentItems']);
    }

    public function test_reusa_el_token_cacheado_en_el_segundo_envio(): void
    {
        Cache::forget('graph_mail_access_token');
        $this->setConfig();

        $requests = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'fake-token-abc'])),
            new Response(202),
            new Response(202), // NO hay 2do response de token — si se pidiera otro, MockHandler fallaría por agotamiento
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $transport = new GraphMailTransport($client);
        $transport->send($this->email());
        $transport->send($this->email());

        // 1 pedido de token + 2 envíos = 3 requests, no 4.
        $this->assertCount(3, $requests);
        Cache::forget('graph_mail_access_token');
    }
}
