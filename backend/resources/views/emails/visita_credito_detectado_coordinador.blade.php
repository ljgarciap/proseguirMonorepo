<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nueva solicitud de crédito identificada en visita - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .section-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #1e3a8a; margin: 24px 0 10px 0; }
        .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; border-radius: 8px; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box table td { padding: 4px 0; font-size: 15px; color: #4a5568; vertical-align: top; }
        .info-box table td:first-child { font-weight: 700; width: 200px; }
        .btn { display: inline-block; background: #1d4ed8; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 10px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    @php
        $cliente = $visita->cliente;
        $tipoDocumento = $cliente?->documentType->nombre ?? '';
        $numeroDocumento = $cliente?->numero_documento ?? '—';
    @endphp
    <div class="container">
        <div class="header">
            <h1>Proseguir Soluciones de Liquidez</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Gestión de Liquidez y Soluciones de Crédito</div>
        </div>
        <div class="content">
            <p>Estimado(a) Coordinador(a) Comercial:</p>
            <p>La Gerencia de Proseguir Soluciones de Liquidez ha registrado una nueva visita en la que el cliente manifestó que requiere un crédito. Por favor, revise la información registrada y realice la gestión correspondiente para formalizar la solicitud de crédito en el sistema.</p>

            <div class="section-title">Datos de la visita</div>
            <div class="info-box">
                <table>
                    <tr><td>ID de la visita:</td><td>{{ $visita->id }}</td></tr>
                    <tr><td>Fecha de la visita:</td><td>{{ $visita->fecha->format('d/m/Y') }}</td></tr>
                    <tr><td>Gerente que registra:</td><td>{{ $nombreGerente }}</td></tr>
                    <tr><td>Cliente:</td><td>{{ $cliente->nombre }}</td></tr>
                    <tr><td>Tipo y número de identificación:</td><td>{{ trim($tipoDocumento . ' ' . $numeroDocumento) }}</td></tr>
                    <tr><td>Ciudad:</td><td>{{ $visita->ciudad }}</td></tr>
                </table>
            </div>

            <div class="section-title">Información preliminar del crédito</div>
            <div class="info-box">
                <table>
                    <tr><td>Tipo de crédito:</td><td>{{ $visita->tipoCredito->nombre ?? '—' }}</td></tr>
                    <tr><td>Monto solicitado:</td><td>$ {{ number_format($visita->monto_solicitado, 0, ',', '.') }}</td></tr>
                    <tr><td>Plazo:</td><td>{{ $visita->plazo }} meses</td></tr>
                    <tr><td>Amortización:</td><td>{{ $visita->amortizacion->nombre ?? '—' }}</td></tr>
                    <tr><td>Destino del recurso:</td><td>{{ $visita->destino_recurso }}</td></tr>
                    <tr><td>Garantía ofrecida:</td><td>{{ $visita->garantia ?: '—' }}</td></tr>
                    <tr><td>Fuente de pago:</td><td>{{ $visita->fuente_pago }}</td></tr>
                </table>
            </div>

            <p style="text-align:center; margin-top: 28px;">
                <a href="{{ $urlAcceso }}" class="btn">INGRESAR AL SISTEMA</a>
            </p>

            <p>Cordialmente,<br>Equipo Proseguir</p>
        </div>
        <div class="footer">
            Este es un mensaje automático; por favor no responda a este correo. La información contenida es confidencial y está dirigida únicamente a su destinatario.<br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
