<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crédito rechazado por SARLAFT - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .highlight-box table { width: 100%; border-collapse: collapse; }
        .highlight-box table td { padding: 4px 0; font-size: 15px; color: #4a5568; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proseguir Factoring</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Listas Restrictivas y SARLAFT</div>
        </div>
        <div class="content">
            <p>Estimado(a) Coordinador Comercial,</p>
            <p>El Oficial de Cumplimiento emitió concepto <strong>desfavorable</strong> sobre el crédito <strong>{{ $credito->numero_solicitud }}</strong>. El crédito ha sido rechazado.</p>
            <div class="highlight-box">
                <table>
                    <tr><td style="font-weight:bold; width:150px;">No. de Crédito:</td><td>{{ $credito->numero_solicitud }}</td></tr>
                    <tr><td style="font-weight:bold;">Fecha:</td><td>{{ optional($credito->sarlaft_diligenciado_at)->format('d/m/Y') }}</td></tr>
                    <tr><td style="font-weight:bold;">Motivo:</td><td>{{ $credito->sarlaft_observaciones }}</td></tr>
                </table>
            </div>
        </div>
        <div class="footer">
            Este es un correo automático enviado desde la plataforma Proseguir.<br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
