<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ajustes requeridos en su solicitud - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { background-color: #fffbeb; border-left: 4px solid #d97706; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .btn { display: inline-block; background: #1d4ed8; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 10px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proseguir Soluciones de Liquidez</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Gestión de Liquidez y Soluciones de Crédito</div>
        </div>
        <div class="content">
            <p>Estimado(a) {{ $credito->cliente->name ?? 'cliente' }},</p>
            <p>Su solicitud de crédito <strong>{{ $credito->numero_solicitud }}</strong> requiere ajustes en la documentación cargada antes de continuar con el proceso.</p>
            <div class="highlight-box">
                <p style="margin:0;"><strong>Observación del Director de Crédito:</strong><br>{{ $comentario }}</p>
            </div>
            <p>Por favor ingrese al sistema para revisar y actualizar los documentos solicitados.</p>
            <p style="text-align:center;">
                <a href="{{ $urlAcceso }}" class="btn">ACTUALIZAR DOCUMENTOS</a>
            </p>
        </div>
        <div class="footer">
            Este es un correo automático enviado desde la plataforma Proseguir.<br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
