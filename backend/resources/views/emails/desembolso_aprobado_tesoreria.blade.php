<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operación de desembolso aprobada - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { padding: 16px 20px; margin: 20px 0; border-radius: 8px; font-weight: 700; text-align: center; background-color: #f0fdf4; border-left: 4px solid #16a34a; color: #15803d; }
        .highlight-box table { width: 100%; border-collapse: collapse; text-align: left; font-weight: normal; margin-top: 8px; }
        .highlight-box table td { padding: 4px 0; font-size: 14px; color: #15803d; }
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
            <p>Estimado(a):</p>
            <p>Gerencia aprobó la Operación de Desembolso en CYF de la solicitud No. <strong>{{ $credito->numero_solicitud }}</strong>.</p>
            <div class="highlight-box">
                DESEMBOLSO APROBADO
                <table>
                    <tr>
                        <td style="font-weight: bold; width: 180px;">Cliente:</td>
                        <td>{{ $nombreCliente }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Tipo de crédito:</td>
                        <td>{{ $tipoCredito }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Monto solicitado:</td>
                        <td>${{ number_format($credito->monto, 0, ',', '.') }} COP</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Radicado CYF:</td>
                        <td>{{ $credito->radicado_cyf }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Decisión registrada por:</td>
                        <td>{{ $nombreGerente }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Fecha y hora:</td>
                        <td>{{ $fechaHoraDecision->bogota()->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </div>
            <p>La solicitud está disponible para efectuar y registrar la transferencia bancaria.</p>
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
