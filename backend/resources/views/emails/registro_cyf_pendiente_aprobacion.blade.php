<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crédito registrado en CYF - pendiente de aprobación - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { padding: 16px 20px; margin: 20px 0; border-radius: 8px; font-weight: 700; text-align: center; background-color: #fffaeb; border-left: 4px solid #d97706; color: #b45309; }
        .highlight-box table { width: 100%; border-collapse: collapse; text-align: left; font-weight: normal; margin-top: 8px; }
        .highlight-box table td { padding: 4px 0; font-size: 14px; color: #92400e; }
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
            <p>Estimado(a) Gerente:</p>
            <p>El crédito correspondiente a la solicitud No. <strong>{{ $credito->numero_solicitud }}</strong>, del cliente {{ $nombreCliente }}, fue registrado exitosamente en CYF por {{ $nombreCoordinador }}.</p>
            <div class="highlight-box">
                PENDIENTE DE REVISIÓN Y APROBACIÓN
                <table>
                    <tr>
                        <td style="font-weight: bold; width: 180px;">Tipo de crédito:</td>
                        <td>{{ $tipoCredito }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Tipo de documento:</td>
                        <td>{{ $tipoDocumento }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Número de documento:</td>
                        <td>{{ $numeroDocumento }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Monto:</td>
                        <td>${{ number_format($credito->monto, 0, ',', '.') }} COP</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Fecha de registro CYF:</td>
                        <td>{{ optional($credito->fecha_registro_cyf)->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Radicado CYF:</td>
                        <td>{{ $credito->radicado_cyf }}</td>
                    </tr>
                </table>
            </div>
            <p>La solicitud se encuentra disponible para que realice la revisión del registro y seleccione la decisión de aprobación correspondiente desde la funcionalidad Gestión de Créditos.</p>
            <p style="text-align:center;">
                <a href="{{ $urlAcceso }}" class="btn">REVISAR Y APROBAR REGISTRO</a>
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
