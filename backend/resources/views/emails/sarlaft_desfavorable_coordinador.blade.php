<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Concepto desfavorable SARLAFT - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { background-color: #f8fafc; border-left: 4px solid #dc2626; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .highlight-box table { width: 100%; border-collapse: collapse; }
        .highlight-box table td { padding: 4px 0; font-size: 15px; color: #4a5568; }
        .notice { font-size: 14px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin: 0 0 15px 0; }
        .btn { display: inline-block; background: #dc2626; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 10px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    @php
        $cliente = $credito->solicitudCredito?->cliente;
        $nombreCliente = $cliente->nombre ?? '—';
        $tipoDocumento = $cliente?->documentType->codigo ?? '';
        $numeroDocumento = $cliente->identificacion ?? $cliente->numero_documento ?? '—';
        $fechaValidacion = optional($credito->sarlaft_diligenciado_at?->bogota())->format('d/m/Y H:i');
    @endphp
    <div class="container">
        <div class="header">
            <h1>Proseguir Factoring</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Listas Restrictivas y SARLAFT</div>
        </div>
        <div class="content">
            <p>Estimado(a) Coordinador(a) Comercial:</p>
            <p>Le informamos que el usuario de Control Interno finalizó la validación en Listas Restrictivas y SARLAFT de la solicitud de crédito relacionada a continuación y registró un concepto <strong>desfavorable</strong>.</p>
            <p>En consecuencia, la solicitud quedó pendiente de su gestión. Por favor, ingrese a Gestión de Crédito, revise la información registrada y realice la notificación correspondiente al cliente desde dicha pantalla.</p>

            <div class="highlight-box">
                <table>
                    <tr><td style="font-weight:bold; width:180px;">Cliente:</td><td>{{ $nombreCliente }}</td></tr>
                    <tr><td style="font-weight:bold;">Identificación:</td><td>{{ trim($tipoDocumento . ' ' . $numeroDocumento) }}</td></tr>
                    <tr><td style="font-weight:bold;">Solicitud de crédito:</td><td>{{ $credito->numero_solicitud }}</td></tr>
                    <tr><td style="font-weight:bold;">Fecha de validación:</td><td>{{ $fechaValidacion ?? '—' }}</td></tr>
                    <tr><td style="font-weight:bold;">Concepto / estado:</td><td>Desfavorable / Pendiente de Gestión del Crédito</td></tr>
                    <tr><td style="font-weight:bold;">Observaciones:</td><td>{{ $credito->sarlaft_observaciones }}</td></tr>
                </table>
            </div>

            <p class="notice">Importante: el sistema no envía automáticamente la notificación al cliente en esta etapa.</p>

            <p style="text-align:center;">
                <a href="{{ $urlValidacion }}" class="btn">GESTIONAR SOLICITUD DE CRÉDITO</a>
            </p>
        </div>
        <div class="footer">
            Este es un correo automático enviado desde la plataforma Proseguir.<br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
