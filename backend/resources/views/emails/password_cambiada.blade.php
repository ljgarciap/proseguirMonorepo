<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirmación de cambio de contraseña - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; border-radius: 8px; margin: 20px 0; text-align: center; font-size: 16px; font-weight: 700; color: #1e3a8a; }
        .notice { font-size: 14px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin: 20px 0; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proseguir Factoring</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Sistema de Gestión de Liquidez</div>
        </div>
        <div class="content">
            <p>Estimado(a) {{ $usuario->name }}:</p>
            <p>Le informamos que la contraseña de acceso al Sistema de Gestión de Liquidez fue modificada exitosamente.</p>

            <div class="info-box">
                Fecha: {{ $fechaCambio->bogota()->format('d/m/Y') }} &nbsp;&nbsp; Hora: {{ $fechaCambio->bogota()->format('H:i') }} (hora Colombia)
            </div>

            <p>Si realizó este cambio, no es necesario efectuar ninguna acción adicional.</p>
            <p class="notice">Si no reconoce esta modificación, comuníquese inmediatamente con el administrador del sistema.</p>
            <p>Por seguridad, nunca comparta su contraseña con otras personas.</p>

            <p>Cordialmente,<br>Equipo Proseguir</p>
        </div>
        <div class="footer">
            Este es un mensaje automático; por favor no responda a este correo.<br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
