<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resultado de validación de garantías - Proseguir</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 30px; line-height: 1.6; }
        .content p { margin: 0 0 15px 0; font-size: 16px; color: #4a5568; }
        .highlight-box { padding: 16px 20px; margin: 20px 0; border-radius: 8px; font-weight: 700; text-align: center; }
        .highlight-box.aprobadas { background-color: #f0fdf4; border-left: 4px solid #16a34a; color: #15803d; }
        .highlight-box.ajustes { background-color: #fff7ed; border-left: 4px solid #ea580c; color: #c2410c; }
        table.garantias { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        table.garantias th, table.garantias td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: left; }
        table.garantias th { background-color: #f8fafc; color: #4a5568; }
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
            <p>Estimado(a) {{ $nombreCliente }}:</p>
            <p>Finalizamos la validación de las garantías asociadas a su solicitud de crédito No. <strong>{{ $credito->numero_solicitud }}</strong>. A continuación, encontrará el resultado del proceso:</p>

            @if($requiereAjustes)
                <div class="highlight-box ajustes">SE REQUIEREN AJUSTES</div>
                <p>Una o más garantías no fueron aprobadas. Consulte el detalle y las observaciones registradas, y realice los ajustes correspondientes.</p>
            @else
                <div class="highlight-box aprobadas">GARANTÍAS APROBADAS</div>
                <p>Todas las garantías fueron aprobadas. No se requiere ninguna acción de su parte; la solicitud continuará al Registro de Crédito en CYF.</p>
            @endif

            <table class="garantias">
                <thead>
                    <tr>
                        <th>Garantía</th>
                        <th>Resultado</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detalleGarantias as $item)
                        <tr>
                            <td>{{ $item['garantia'] }}</td>
                            <td>{{ $item['resultado'] }}</td>
                            <td>{{ $item['observaciones'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($requiereAjustes && $urlPortalCliente)
                <p style="text-align:center;">
                    <a href="{{ $urlPortalCliente }}" class="btn">Ingresar a Mis Créditos</a>
                </p>
            @endif

            <p>Cordialmente,<br>Equipo Proseguir</p>
        </div>
        <div class="footer">
            Este es un mensaje automático; por favor no responda a este correo. La información contenida es confidencial y está dirigida únicamente a su destinatario.<br>
            &copy; {{ date('Y') }} Proseguir. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
