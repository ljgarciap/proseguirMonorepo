<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Solicitud de Crédito - Proseguir</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 30px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
        }
        .content p {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #4a5568;
        }
        .highlight-box {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .highlight-box h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #1e3a8a;
        }
        .highlight-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .highlight-box table td {
            padding: 4px 0;
            font-size: 15px;
            color: #4a5568;
        }
        .document-list {
            margin: 20px 0;
            padding: 0 0 0 20px;
        }
        .document-list li {
            margin-bottom: 10px;
            font-size: 15px;
            color: #2d3748;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            background: #1d4ed8;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            margin-top: 10px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #edf2f7;
            font-size: 13px;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proseguir Soluciones de Liquidez</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Gestión de Liquidez y Soluciones de Crédito</div>
        </div>
        <div class="content">
            <p>Estimado(a) <strong>{{ $solicitud->cliente->nombre }}</strong>,</p>
            
            <p>{!! nl2br(e($solicitud->mensaje_notificacion)) !!}</p>

            <div class="highlight-box">
                <h3>Resumen de las Condiciones Solicitadas</h3>
                <table>
                    <tr>
                        <td style="font-weight: bold; width: 150px;">Tipo de Crédito:</td>
                        <td>{{ $solicitud->tipoCredito->nombre }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Monto Solicitado:</td>
                        <td>${{ number_format($solicitud->monto_solicitado, 0, ',', '.') }} COP</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Plazo:</td>
                        <td>{{ $solicitud->plazo_meses }} meses</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Amortización:</td>
                        <td>{{ $solicitud->amortizacion->nombre }}</td>
                    </tr>
                </table>
            </div>

            @if(count($documentosRequeridos) > 0)
                <p>Para continuar con el proceso de estudio, por favor prepare y cargue a través de su portal de cliente los siguientes documentos:</p>
                <ul class="document-list">
                    @foreach($documentosRequeridos as $doc)
                        <li>📌 {{ $doc }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- SCRUM-244: datos de acceso reales, componente automático de
                 la plantilla (nunca del texto editable por el Coordinador). --}}
            <div class="highlight-box">
                <p style="margin:0;">Para realizar esta gestión, utilice los siguientes datos de acceso:</p>
                <p style="margin:8px 0 0 0;">
                    <strong>URL:</strong> {{ $urlIngreso }}<br>
                    <strong>Usuario:</strong> {{ $usuarioAcceso }}<br>
                    <strong>Clave:</strong> {{ $claveAcceso }}
                </p>
            </div>

            <p style="margin-top: 25px;">Si tiene alguna inquietud o requiere apoyo adicional, no dude en contactarse con su Director de Crédito asignado.</p>

            <p style="text-align:center;">
                <a href="{{ $urlIngreso }}" class="btn">INGRESAR A LA PLATAFORMA</a>
            </p>
            <p style="font-size: 13px; color: #a0aec0;">Por seguridad, no comparta sus credenciales de acceso. Si requiere asistencia, comuníquese con nuestro equipo de atención.</p>
        </div>
        <div class="footer">
            Este es un correo automático enviado desde la plataforma Proseguir.<br>
            © {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
