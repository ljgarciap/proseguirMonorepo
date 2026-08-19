<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transferencia bancaria registrada - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { padding: 16px 20px; margin: 20px 0; border-radius: 8px; font-weight: 700; text-align: center; background-color: #eff6ff; border-left: 4px solid #1d4ed8; color: #1e40af; }
        table.detalle { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        table.detalle td { border: 1px solid #e2e8f0; padding: 10px 12px; }
        table.detalle td.label { background-color: #f8fafc; color: #4a5568; font-weight: 600; width: 45%; }
        .btn { display: inline-block; background: #1d4ed8; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 10px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proseguir Factoring</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Gestión de Liquidez y Soluciones de Crédito</div>
        </div>
        <div class="content">
            <p>Estimado(a):</p>
            <p>Tesorería registró la transferencia bancaria del desembolso de la solicitud No. <strong>{{ $credito->numero_solicitud }}</strong>.</p>
            <div class="highlight-box">TRANSFERENCIA REGISTRADA</div>

            <table class="detalle">
                <tr><td class="label">Solicitud</td><td>{{ $credito->numero_solicitud }}</td></tr>
                <tr><td class="label">Cliente</td><td>{{ $transferencia['cliente_nombre'] ?? '—' }}</td></tr>
                <tr><td class="label">Fecha y hora de la transferencia</td><td>{{ $transferencia['fecha_transferencia'] ?? '—' }} {{ $transferencia['hora_transferencia'] ?? '' }}</td></tr>
                <tr><td class="label">Valor</td><td>${{ number_format((float) ($transferencia['valor_transaccion'] ?? 0), 2) }} {{ $transferencia['moneda_cuenta'] ?? 'COP' }}</td></tr>
                <tr><td class="label">Número de la transacción</td><td>{{ $transferencia['numero_transaccion'] ?? '—' }}</td></tr>
                <tr><td class="label">Registrado por</td><td>{{ $transferencia['registrado_por_nombre'] ?? '—' }}</td></tr>
            </table>

            <p style="text-align:center;">
                <a href="{{ $urlIngreso }}" class="btn">Ingresar al sistema</a>
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
