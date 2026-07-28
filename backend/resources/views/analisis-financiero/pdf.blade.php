<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Análisis Financiero — {{ $credito->numero_solicitud }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10.5px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin: 16px 0 6px 0; padding-bottom: 3px; border-bottom: 2px solid #334155; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 3px 5px; border-bottom: 1px solid #e2e8f0; text-align: right; }
        th:first-child, td:first-child { text-align: left; }
        th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; color: #64748b; }
        .total-row td { font-weight: bold; background: #f8fafc; border-top: 1.5px solid #94a3b8; }
        .header-box { margin-bottom: 14px; }
        .header-box table td { border: none; padding: 2px 6px; text-align: left; }
        .draft-banner {
            background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
            padding: 8px 12px; font-weight: bold; text-align: center; margin-bottom: 12px; border-radius: 4px;
        }
        .note { font-size: 9px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; padding: 6px 8px; margin-bottom: 6px; }
        .resumen-grid td { border: 1px solid #cbd5e1; padding: 8px; }
        .traceability { margin-top: 6px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>

@php
    $anios = $calculado['anios'];
    $pct = fn ($v) => $v === null ? 'N/A' : number_format($v * 100, 2) . '%';
    $num = fn ($v) => number_format($v ?? 0, 0);
@endphp

@if($analisis->estado !== 'confirmado')
    <div class="draft-banner">*** BORRADOR — ANÁLISIS NO CONFIRMADO ***</div>
@endif

<h1>ANÁLISIS FINANCIERO</h1>

<div class="header-box">
    <table>
        <tr><td><strong>No. de Solicitud:</strong> {{ $credito->numero_solicitud }}</td><td><strong>Cliente:</strong> {{ $cliente->nombre ?? '—' }}</td></tr>
        <tr><td><strong>Identificación:</strong> {{ $cliente->numero_documento ?? '—' }}</td><td><strong>Monto Solicitado:</strong> {{ $num($solicitudCredito->monto_solicitado ?? 0) }}</td></tr>
        <tr><td colspan="2"><strong>Años del análisis:</strong> {{ implode(' - ', $anios) }} · <strong>Estado:</strong> {{ $analisis->estado }}</td></tr>
    </table>
</div>

@php
    // Tabla genérica valor + análisis estructural + variación por año, usada
    // en ACTIVO/PASIVO/PATRIMONIO/UTILIDAD NETA.
    $renderFila = function ($label, $valores, $estructural, $variacion, $anios, $bold = false) use ($num, $pct) {
        $cells = "<td>{$label}</td>";
        foreach ($anios as $i => $anio) {
            $cells .= '<td>' . $num($valores[$anio] ?? 0) . '</td>';
            $cells .= '<td>' . $pct($estructural[$anio] ?? null) . '</td>';
            if ($i > 0) {
                $cells .= '<td>' . $pct($variacion[$anio] ?? null) . '</td>';
            }
        }
        $class = $bold ? ' class="total-row"' : '';
        echo "<tr{$class}>{$cells}</tr>";
    };
    $renderEncabezado = function ($anios) {
        $cells = '<th>Concepto</th>';
        foreach ($anios as $i => $anio) {
            $cells .= "<th>{$anio}</th><th>An\u{00e1}lisis Estructural</th>";
            if ($i > 0) {
                $cells .= '<th>Variaci' . "\u{00f3}" . 'n</th>';
            }
        }
        echo "<tr>{$cells}</tr>";
    };
@endphp

<h2>ACTIVO</h2>
<table>
    <thead>{!! $renderEncabezado($anios) !!}</thead>
    <tbody>
    @foreach ($labelsActivo as $clave => $label)
        {!! $renderFila($label, $calculado['activo']['valores'][$clave], $calculado['activo']['estructural'][$clave], $calculado['activo']['variacion'][$clave] ?? [], $anios) !!}
    @endforeach
    {!! $renderFila('TOTAL ACTIVO CORRIENTE', $calculado['activo']['total_activo_corriente'], $calculado['activo']['estructural']['activo_corriente'], $calculado['activo']['variacion']['activo_corriente'] ?? [], $anios, true) !!}
    {!! $renderFila('TOTAL ACTIVO NO CORRIENTE', $calculado['activo']['total_activo_no_corriente'], $calculado['activo']['estructural']['activo_no_corriente'], $calculado['activo']['variacion']['activo_no_corriente'] ?? [], $anios, true) !!}
    </tbody>
    <tfoot>
        <tr class="total-row"><td>TOTAL ACTIVO</td>
        @foreach ($anios as $anio)
            <td colspan="{{ $loop->first ? 2 : 3 }}">{{ $num($calculado['activo']['total_activo'][$anio] ?? 0) }}</td>
        @endforeach
        </tr>
    </tfoot>
</table>

<h2>PASIVO</h2>
<table>
    <thead>{!! $renderEncabezado($anios) !!}</thead>
    <tbody>
    @foreach ($labelsPasivo as $clave => $label)
        {!! $renderFila($label, $calculado['pasivo']['valores'][$clave], $calculado['pasivo']['estructural'][$clave], $calculado['pasivo']['variacion'][$clave] ?? [], $anios) !!}
    @endforeach
    {!! $renderFila('TOTAL PASIVO CORRIENTE', $calculado['pasivo']['total_pasivo_corriente'], $calculado['pasivo']['estructural']['pasivo_corriente'], $calculado['pasivo']['variacion']['pasivo_corriente'] ?? [], $anios, true) !!}
    {!! $renderFila('TOTAL PASIVO NO CORRIENTE', $calculado['pasivo']['total_pasivo_no_corriente'], $calculado['pasivo']['estructural']['pasivo_no_corriente'], $calculado['pasivo']['variacion']['pasivo_no_corriente'] ?? [], $anios, true) !!}
    </tbody>
    <tfoot>
        <tr class="total-row"><td>TOTAL PASIVO</td>
        @foreach ($anios as $anio)
            <td colspan="{{ $loop->first ? 2 : 3 }}">{{ $num($calculado['pasivo']['total_pasivo'][$anio] ?? 0) }}</td>
        @endforeach
        </tr>
    </tfoot>
</table>

<h2>PATRIMONIO</h2>
<div class="note">Validación contable: TOTAL ACTIVO debe ser igual a TOTAL PASIVO + PATRIMONIO.</div>
<table>
    <thead>{!! $renderEncabezado($anios) !!}</thead>
    <tbody>
    @foreach ($labelsPatrimonio as $clave => $label)
        {!! $renderFila($label, $calculado['patrimonio']['valores'][$clave], $calculado['patrimonio']['estructural'][$clave], $calculado['patrimonio']['variacion'][$clave] ?? [], $anios) !!}
    @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row"><td>TOTAL PATRIMONIO</td>
        @foreach ($anios as $anio)
            <td colspan="{{ $loop->first ? 2 : 3 }}">{{ $num($calculado['patrimonio']['total_patrimonio'][$anio] ?? 0) }}</td>
        @endforeach
        </tr>
        <tr><td>Diferencia (Activo - (Pasivo + Patrimonio))</td>
        @foreach ($anios as $anio)
            <td colspan="{{ $loop->first ? 2 : 3 }}">{{ $num($calculado['patrimonio']['validacion_contable'][$anio] ?? 0) }}</td>
        @endforeach
        </tr>
    </tfoot>
</table>

@php $un = $calculado['utilidad_neta']; @endphp
<h2>UTILIDAD NETA</h2>
<table>
    <thead><tr><th>Concepto</th>@foreach ($anios as $anio)<th>{{ $anio }}</th>@endforeach</tr></thead>
    <tbody>
        <tr><td>Ingresos ordinarios</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['ingresos_ordinarios'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Costo de ventas</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['costo_ventas'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr class="total-row"><td>UTILIDAD BRUTA</td>@foreach ($anios as $anio)<td>{{ $num($un['utilidad_bruta'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Gastos de administración</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['gastos_administracion'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Gastos de ventas y distribución</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['gastos_ventas_distribucion'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr class="total-row"><td>UTILIDAD OPERACIONAL</td>@foreach ($anios as $anio)<td>{{ $num($un['utilidad_operacional'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Ingreso financiero</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['ingreso_financiero'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Otros ingresos</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['otros_ingresos'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Ingresos método de participación</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['ingresos_metodo_participacion'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Gasto financiero</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['gasto_financiero'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Intereses</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['intereses'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Otros gastos</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['otros_gastos'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr class="total-row"><td>GANANCIA ANTES DE IMPUESTOS</td>@foreach ($anios as $anio)<td>{{ $num($un['ganancia_antes_impuestos'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Impuesto a las ganancias</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['impuesto_ganancias'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Impuesto de renta</td>@foreach ($anios as $anio)<td>{{ $num($un['valores']['impuesto_renta'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr class="total-row"><td>UTILIDAD NETA</td>@foreach ($anios as $anio)<td>{{ $num($un['utilidad_neta'][$anio] ?? 0) }}</td>@endforeach</tr>
    </tbody>
</table>

@php $ori = $calculado['ori']; @endphp
<h2>ORI - OTRO RESULTADO INTEGRAL</h2>
<table>
    <thead><tr><th>Concepto</th>@foreach ($anios as $anio)<th>{{ $anio }}</th>@endforeach</tr></thead>
    <tbody>
        <tr><td>Superávit por revaluación de activos</td>@foreach ($anios as $anio)<td>{{ $num($ori['valores']['superavit_revaluacion_activos'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr><td>Conversión moneda extranjera</td>@foreach ($anios as $anio)<td>{{ $num($ori['valores']['conversion_moneda_extranjera'][$anio] ?? 0) }}</td>@endforeach</tr>
        <tr class="total-row"><td>TOTAL ORI</td>@foreach ($anios as $anio)<td>{{ $num($ori['total_ori'][$anio] ?? 0) }}</td>@endforeach</tr>
    </tbody>
</table>

@php $cartera = $calculado['cartera']; @endphp
<h2>CARTERA</h2>
<table>
    <thead><tr><th>Concepto</th>@foreach ($anios as $anio)<th>{{ $anio }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($labelsCartera as $clave => $label)
        <tr><td>{{ $label }}</td>@foreach ($anios as $anio)<td>{{ $num($cartera['valores'][$clave][$anio] ?? 0) }}</td>@endforeach</tr>
    @endforeach
        <tr class="total-row"><td>CARTERA NETA</td>@foreach ($anios as $anio)<td>{{ $num($cartera['cartera_neta'][$anio] ?? 0) }}</td>@endforeach</tr>
    </tbody>
</table>

@php $resumen = $calculado['resumen']; @endphp
<h2>RESUMEN — Año {{ $resumen['anio_resumen'] }}</h2>
<table class="resumen-grid">
    <tr>
        <td><strong>Total Activo</strong><br>{{ $num($resumen['total_activo']) }}</td>
        <td><strong>Total Pasivo</strong><br>{{ $num($resumen['total_pasivo']) }}</td>
        <td><strong>Patrimonio</strong><br>{{ $num($resumen['total_patrimonio']) }}</td>
    </tr>
    <tr>
        <td><strong>Utilidad Neta</strong><br>{{ $num($resumen['utilidad_neta']) }}</td>
        <td><strong>Cartera Neta</strong><br>{{ $num($resumen['cartera_neta']) }}</td>
        <td><strong>Diferencia contable</strong><br>{{ $num($resumen['diferencia_contable']) }}</td>
    </tr>
</table>

<h3>Observaciones</h3>
<p>{{ $analisis->observaciones ?: '—' }}</p>
<div class="traceability">
    Diligenciado por: {{ $analisis->diligenciadoPor->name ?? '—' }} ·
    Fecha: {{ optional($analisis->diligenciado_por_at)->format('Y-m-d H:i') ?? '—' }}
</div>

</body>
</html>
