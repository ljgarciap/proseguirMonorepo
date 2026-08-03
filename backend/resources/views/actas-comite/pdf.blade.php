<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta Comité de Crédito — N.° {{ $acta->numero }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 4px; text-align: center; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; padding-bottom: 3px; border-bottom: 2px solid #334155; }
        h3 { font-size: 11.5px; margin: 10px 0 4px 0; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; font-size: 9.5px; text-transform: uppercase; color: #64748b; }
        .total-row td { font-weight: bold; background: #f8fafc; border-top: 1.5px solid #94a3b8; }
        .header-box { margin-bottom: 14px; }
        .header-box table td { border: none; padding: 2px 6px; }
        .draft-banner {
            background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
            padding: 8px 12px; font-weight: bold; text-align: center; margin-bottom: 12px; border-radius: 4px;
        }
        .rich-text { margin-bottom: 6px; }
        .rich-text img { max-width: 100%; }
        .orden-dia-item { margin-bottom: 10px; }
        .firma-box { display: inline-block; width: 45%; margin: 20px 2% 0 0; text-align: center; border-top: 1px solid #334155; padding-top: 4px; }
    </style>
</head>
<body>

@if($acta->estado !== 'aprobada')
    <div class="draft-banner">*** BORRADOR — ACTA NO REGISTRADA ***</div>
@endif

<h1>ACTA DEL COMITÉ DE CRÉDITO N.° {{ $acta->numero }}</h1>

<div class="header-box">
    <table>
        <tr>
            <td><strong>Fecha:</strong> {{ optional($acta->fecha_reunion)->format('d/m/Y') ?? '—' }}</td>
            <td><strong>Hora de inicio:</strong> {{ $acta->hora_inicio ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Lugar:</strong></td>
        </tr>
    </table>
    <div class="rich-text">{!! $acta->lugar ?? '—' !!}</div>
</div>

<h2>Asistentes</h2>
<ul>
    @forelse(($acta->asistentes ?? []) as $asistente)
        <li>{{ is_array($asistente) ? ($asistente['nombre'] ?? '') : $asistente }}</li>
    @empty
        <li>—</li>
    @endforelse
</ul>

<h2>Orden del día {{ $acta->orden_dia_aprobado ? '(aprobado por unanimidad)' : '' }}</h2>
<ol>
    @foreach(($acta->orden_dia ?? []) as $item)
        <li>{{ $item['texto'] ?? '' }}</li>
    @endforeach
</ol>

<h2>Desarrollo</h2>
@foreach(($acta->orden_dia ?? []) as $item)
    <div class="orden-dia-item">
        <h3>{{ $item['texto'] ?? '' }}</h3>
        <div class="rich-text">{!! ($acta->desarrollo[$item['id']] ?? null) ?: '—' !!}</div>
    </div>
@endforeach

<h3>Presentación de solicitudes</h3>
<table>
    <thead>
        <tr><th>Cliente</th><th>Solicitud</th><th>Monto</th><th>Origen</th></tr>
    </thead>
    <tbody>
        @forelse($acta->solicitudes as $solicitud)
            <tr>
                <td>{{ $solicitud->cliente_nombre }}</td>
                <td>{{ $solicitud->tipo_solicitud }}</td>
                <td>${{ number_format($solicitud->monto ?? 0, 0, ',', '.') }}</td>
                <td>{{ $solicitud->origen === 'sistema' ? 'Análisis financiero' : 'Adicionada manualmente' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No hay solicitudes presentadas.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Decisión y detalle de solicitudes</h2>
<table>
    <thead>
        <tr>
            <th>Cliente</th><th>Monto</th><th>Amortización</th><th>Plazo</th>
            <th>Tasa</th><th>Garantías</th><th>Fuente de pago</th><th>Estado</th><th>Vigencia</th>
        </tr>
    </thead>
    <tbody>
        @forelse($acta->solicitudes as $solicitud)
            <tr>
                <td>{{ $solicitud->cliente_nombre }} @if($solicitud->cliente_identificacion)({{ $solicitud->cliente_identificacion }})@endif</td>
                <td>${{ number_format($solicitud->monto ?? 0, 0, ',', '.') }}</td>
                <td>{{ $solicitud->amortizacion ?? '—' }}</td>
                <td>{{ $solicitud->plazo_meses ?? '—' }} meses</td>
                <td>{{ $solicitud->tasa_interes !== null ? number_format($solicitud->tasa_interes, 2) . '%' : '—' }}</td>
                <td>{{ $solicitud->garantias ?? '—' }}</td>
                <td>{{ $solicitud->fuente_pago ?? '—' }}</td>
                <td>{{ strtoupper($solicitud->estado_decision ?? 'pendiente') }}</td>
                <td>{{ $solicitud->vigencia_aprobacion ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No hay solicitudes registradas.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Resumen de aprobaciones</h2>
<p>Se dan por APROBADAS, NEGADAS O APLAZADAS las siguientes operaciones, de la siguiente manera:</p>
<table>
    <thead><tr><th>Cliente</th><th>Concepto</th><th>Decisión</th></tr></thead>
    <tbody>
        @forelse($acta->solicitudes as $solicitud)
            <tr>
                <td>{{ $solicitud->cliente_nombre }}</td>
                <td>{{ strtoupper($solicitud->estado_decision ?? 'PENDIENTE') }}</td>
                <td>${{ number_format($solicitud->monto_decision ?? 0, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No hay solicitudes registradas.</td></tr>
        @endforelse
        <tr class="total-row"><td>Total Aprobado</td><td colspan="2">${{ number_format($totales['aprobado'] ?? 0, 0, ',', '.') }}</td></tr>
        <tr class="total-row"><td>Total Rechazado</td><td colspan="2">${{ number_format($totales['rechazado'] ?? 0, 0, ',', '.') }}</td></tr>
        <tr class="total-row"><td>Total Pendiente</td><td colspan="2">${{ number_format($totales['pendiente'] ?? 0, 0, ',', '.') }}</td></tr>
    </tbody>
</table>

<h2>Observaciones generales</h2>
<div class="rich-text">{!! $acta->observaciones_generales ?? '—' !!}</div>
<p>Termina la reunión a las {{ $acta->hora_finalizacion ?? '—' }}.</p>

<h2>Firmantes</h2>
@foreach(($acta->firmantes ?? []) as $firmante)
    <div class="firma-box">
        {{ is_array($firmante) ? ($firmante['nombre'] ?? '') : '' }}<br>
        <small>{{ is_array($firmante) ? ($firmante['rol'] ?? '') : '' }}</small>
    </div>
@endforeach

</body>
</html>
