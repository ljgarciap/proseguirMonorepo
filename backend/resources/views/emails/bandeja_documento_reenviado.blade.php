<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Documento reenviado - Proseguir</title>
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
        .mensaje-box { padding: 14px 16px; margin: 10px 0 20px; border-radius: 8px; background-color: #f8fafc; border: 1px solid #e2e8f0; font-size: 14px; white-space: pre-line; }
        ul.archivos { margin: 0 0 20px; padding-left: 20px; font-size: 14px; }
        .btn { display: inline-block; background: #1d4ed8; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; margin-top: 10px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proseguir Factoring</h1>
            <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Bandeja Interna de Documentos</div>
        </div>
        <div class="content">
            <p>Estimado(a):</p>
            <p>Un documento previamente devuelto fue reenviado y vuelve a estar pendiente de revisión.</p>
            <div class="highlight-box">DOCUMENTO REENVIADO - PENDIENTE DE REVISIÓN</div>

            <table class="detalle">
                <tr><td class="label">Título</td><td>{{ $envio->titulo }}</td></tr>
                <tr><td class="label">Enviado por</td><td>{{ $envio->sender->name ?? '—' }} ({{ $envio->sender->documentType->nombre ?? 'Documento' }} {{ $envio->sender->numero_documento ?? '—' }})</td></tr>
                <tr><td class="label">Rol de quien envía</td><td>{{ $rolOrigen }}</td></tr>
                <tr><td class="label">Fecha y hora del envío original</td><td>{{ $envio->created_at->format('d/m/Y H:i') }}</td></tr>
                <tr>
                    <td class="label">Ruta de aprobación</td>
                    <td>
                        @foreach ($envio->steps as $paso)
                            {{ $paso->orden }}. {{ $paso->area->nombre ?? '—' }}@if (!$loop->last)<br>@endif
                        @endforeach
                    </td>
                </tr>
                <tr><td class="label">Prioridad</td><td>{{ $envio->priority->nombre ?? '—' }}</td></tr>
            </table>

            <p style="margin-bottom:6px;"><strong>Mensaje</strong></p>
            <div class="mensaje-box">{{ $envio->observaciones ?: 'Sin mensaje adicional.' }}</div>

            <p style="margin-bottom:6px;"><strong>Archivos adjuntos</strong></p>
            <ul class="archivos">
                @forelse ($envio->files as $archivo)
                    <li>{{ $archivo->original_name }}</li>
                @empty
                    <li>Sin archivos adjuntos.</li>
                @endforelse
            </ul>

            <table class="detalle">
                <tr><td class="label">Fecha y hora de la devolución</td><td>{{ optional($fechaDevolucion)->format('d/m/Y H:i') ?? '—' }}</td></tr>
                <tr><td class="label">Motivo de la devolución</td><td>{{ $motivoDevolucion ?? '—' }}</td></tr>
                <tr><td class="label">Respuesta del remitente al reenviar</td><td>{{ $notaReenvio }}</td></tr>
            </table>

            <p style="text-align:center;">
                <a href="{{ $urlIngreso }}" class="btn">Acceder al Sistema</a>
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
