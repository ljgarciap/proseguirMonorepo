<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $asuntoCorreo }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .banner { text-align: center; padding: 14px; margin: 0 0 20px 0; border-radius: 8px; color: #ffffff; font-weight: 700; font-size: 14px; letter-spacing: 0.5px; }
        .mensaje-box { background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0; white-space: pre-line; }
        .documentos-box { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; margin: 20px 0; border-radius: 8px; }
        .documentos-box ul { margin: 8px 0 0 0; padding-left: 20px; }
        .boton-wrap { text-align: center; margin: 24px 0 10px 0; }
        .boton { display: inline-block; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; }
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
            @if($bannerTexto)
                <div class="banner" style="background-color: {{ $bannerColor }};">{{ $bannerTexto }}</div>
            @endif

            {{-- §4 "Saludo y datos de la solicitud": automático, no editable
                 por el Coordinador — nombre/razón social y número de
                 crédito salen del registro, no del mensaje libre. --}}
            <p>Estimado(a) {{ $credito->solicitudCredito?->cliente?->nombre ?? $credito->cliente?->name ?? 'cliente' }}:</p>
            <p>Referencia: solicitud de crédito <strong>{{ $credito->numero_solicitud }}</strong>.</p>

            {{-- Mensaje de acompañamiento: versión final diligenciada por
                 el Coordinador al confirmar el envío (RN-04). --}}
            <div class="mensaje-box">{{ $mensajeCorreo }}</div>

            @if(!empty($documentos))
                <div class="documentos-box">
                    <strong>Documentos requeridos según la documentación seleccionada:</strong>
                    <ul>
                        @foreach($documentos as $documento)
                            <li>{{ $documento }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($urlAccion)
                <div class="boton-wrap">
                    <a href="{{ $urlAccion }}" class="boton" style="background-color: {{ $bannerColor }};">{{ $botonTexto }}</a>
                </div>
            @endif
        </div>
        <div class="footer">
            Cordialmente,<br>
            <strong>Proseguir Soluciones de Liquidez</strong><br>
            Este es un mensaje automático. Por favor, no responda este correo.<br><br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
