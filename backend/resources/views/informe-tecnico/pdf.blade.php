<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe Técnico — {{ $credito->numero_solicitud }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 4px; }
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
        .note { font-size: 9.5px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; padding: 6px 8px; margin-bottom: 6px; }
        .coverage-box { display: inline-block; width: 31%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; margin-right: 1%; }
        .coverage-box.verde { background: #f0fdf4; border-color: #bbf7d0; }
        .coverage-box.rojo { background: #fef2f2; border-color: #fecaca; }
        .traceability { margin-top: 6px; font-size: 9.5px; color: #64748b; }
    </style>
</head>
<body>

@if($informe->estado !== 'registrado')
    <div class="draft-banner">*** BORRADOR — INFORME NO FINALIZADO ***</div>
@endif

<h1>INFORME TÉCNICO — CRÉDITO CONSTRUCTOR</h1>

<div class="header-box">
    <table>
        <tr><td><strong>No. de Crédito:</strong> {{ $credito->numero_solicitud }}</td><td><strong>Proyecto:</strong> {{ $solicitudCredito->proyecto ?? '—' }}</td></tr>
        <tr><td><strong>Solicitante:</strong> {{ $credito->cliente->name ?? '—' }}</td><td><strong>Tipo de Crédito:</strong> Constructor</td></tr>
        <tr><td><strong>Ciudad:</strong> {{ $cliente->ciudad ?? '—' }}</td><td><strong>Dirección:</strong> {{ $cliente->direccion ?? '—' }}</td></tr>
        <tr><td colspan="2"><strong>Estado del Expediente:</strong> {{ $credito->estado }}</td></tr>
    </table>
</div>

<h2>Sección Ingeniero</h2>

<h3>① Ventas Totales Proyecto</h3>
<table>
    <thead><tr><th>Campo</th><th>Valor</th><th>% Sobre Ventas</th></tr></thead>
    <tbody>
        <tr><td>Casas</td><td>{{ number_format($v['casas'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['casas'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Apartamentos</td><td>{{ number_format($v['apartamentos'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['apartamentos'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Parqueaderos</td><td>{{ number_format($v['parqueaderos'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['parqueaderos'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Conexión gas/arras desist.</td><td>{{ number_format($v['conexion_gas_arras'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['conexion_gas_arras'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Local comercial</td><td>{{ number_format($v['local_comercial'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['local_comercial'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Cuartos útiles</td><td>{{ number_format($v['cuartos_utiles'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['cuartos_utiles'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Otros</td><td>{{ number_format($v['otros'] ?? 0, 0) }}</td><td>{{ number_format(($v['porcentajes']['otros'] ?? 0) * 100, 2) }}%</td></tr>
        <tr class="total-row"><td>Total Ventas</td><td colspan="2">{{ number_format($v['total_ventas'] ?? 0, 0) }}</td></tr>
    </tbody>
</table>

<h3>② Costos (incluido Costo Financiero)</h3>
<table>
    <thead><tr><th>Campo</th><th>Valor</th><th></th></tr></thead>
    <tbody>
        <tr><td>Lote</td><td>{{ number_format($c['lote'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Directos</td><td>{{ number_format($c['directos'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Directos Urbanismo</td><td>{{ number_format($c['directos_urbanismo'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Indirectos</td><td>{{ number_format($c['indirectos'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Honorarios</td><td>{{ number_format($c['honorarios'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Incremento en costos</td><td>{{ number_format($c['incremento_costos'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Financieros</td><td>{{ number_format($c['financieros'] ?? 0, 0) }}</td><td></td></tr>
        <tr class="total-row"><td>Total Costos</td><td>{{ number_format($c['total_costos'] ?? 0, 0) }}</td><td>{{ number_format(($c['porcentaje_costos'] ?? 0) * 100, 2) }}%</td></tr>
    </tbody>
</table>

<h3>③ Invertido</h3>
<table>
    <thead><tr><th>Campo</th><th>Valor</th><th></th></tr></thead>
    <tbody>
        <tr><td>Lote</td><td>{{ number_format($i['lote'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Costos Directos</td><td>{{ number_format($i['costos_directos'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Costos Indirectos</td><td>{{ number_format($i['costos_indirectos'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Cuotas Iniciales en Fiducia</td><td>{{ number_format($i['cuotas_iniciales_fiducia'] ?? 0, 0) }}</td><td></td></tr>
        <tr class="total-row"><td>Total Invertido</td><td>{{ number_format($i['total_invertido'] ?? 0, 0) }}</td><td>{{ number_format(($i['porcentaje_invertido'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Recursos propios</td><td>{{ number_format($i['recursos_propios'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Cuotas Iniciales Ya Pagadas</td><td>{{ number_format($i['cuotas_iniciales_ya_pagadas'] ?? 0, 0) }}</td><td></td></tr>
        <tr class="total-row"><td>Total Fuentes</td><td colspan="2">{{ number_format($i['total_fuentes'] ?? 0, 0) }}</td></tr>
    </tbody>
</table>

<h3>Observaciones del Ingeniero</h3>
<p>{{ $informe->observaciones_ingeniero ?: '—' }}</p>
<div class="traceability">
    Diligenciado por: {{ $informe->ingeniero->name ?? '—' }} ·
    Fecha: {{ optional($informe->diligenciado_por_ingeniero_at)->format('Y-m-d H:i') ?? '—' }}
</div>

<h2>Sección Coordinador Comercial</h2>

<h3>④ Crédito Solicitado</h3>
<table>
    <thead><tr><th>Campo</th><th>Valor</th><th>% Sobre Ventas</th></tr></thead>
    <tbody>
        <tr><td>Crédito Solicitado</td><td>{{ number_format($cs['credito_solicitado'] ?? 0, 0) }}</td><td>{{ number_format(($cs['porcentaje_sobre_ventas'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Aptos. Vendidos</td><td>{{ number_format($cs['aptos_vendidos'] ?? 0, 0) }}</td><td>{{ number_format(($cs['porcentaje_aptos_vendidos_sobre_ventas'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>Cuotas Iniciales Ya Pagadas (= Invertido Ingeniero)</td><td>{{ number_format($cs['cuotas_iniciales_ya_pagadas'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>% para cuotas iniciales pendientes</td><td>{{ number_format(($cs['porcentaje_cuotas_iniciales_pendientes'] ?? 0) * 100, 2) }}%</td><td></td></tr>
        <tr><td>Cuotas Iniciales Pendientes</td><td>{{ number_format($cs['cuotas_iniciales_pendientes'] ?? 0, 0) }}</td><td></td></tr>
        <tr class="total-row"><td>Saldo por Recaudar Contraentrega (vendidos)</td><td colspan="2">{{ number_format($cs['saldo_recaudar_contraentrega_vendidos'] ?? 0, 0) }}</td></tr>
    </tbody>
</table>

<h3>⑥ Saldo por Recaudar Contraentrega (por vender)</h3>
<table>
    <thead><tr><th>Campo</th><th>Valor</th><th>% Sobre Ventas</th></tr></thead>
    <tbody>
        <tr><td>Aptos x Vender</td><td>{{ number_format($s['aptos_x_vender'] ?? 0, 0) }}</td><td>{{ number_format(($s['porcentaje_sobre_ventas'] ?? 0) * 100, 2) }}%</td></tr>
        <tr><td>% para cuotas iniciales (por vender)</td><td>{{ number_format(($s['porcentaje_cuotas_iniciales'] ?? 0) * 100, 2) }}%</td><td></td></tr>
        <tr><td>Cuotas Iniciales</td><td>{{ number_format($s['cuotas_iniciales'] ?? 0, 0) }}</td><td></td></tr>
        <tr><td>Cuotas Iniciales Pendientes</td><td>{{ number_format($s['cuotas_iniciales_pendientes'] ?? 0, 0) }}</td><td></td></tr>
        <tr class="total-row"><td>Saldo por Recaudar Contraentrega (por vender)</td><td colspan="2">{{ number_format($s['saldo_recaudar_contraentrega_por_vender'] ?? 0, 0) }}</td></tr>
        <tr class="total-row"><td>Total Pendiente por Recaudar</td><td colspan="2">{{ number_format($s['total_pendiente_por_recaudar'] ?? 0, 0) }}</td></tr>
    </tbody>
</table>

<h3>Análisis de Financiación</h3>
<table>
    <tbody>
        <tr><td>Costo de la Obra</td><td>{{ number_format($af['costo_obra'] ?? 0, 0) }}</td></tr>
        <tr><td>(-) Valor Invertido</td><td>{{ number_format($af['valor_invertido'] ?? 0, 0) }}</td></tr>
        <tr><td>(-) Crédito</td><td>{{ number_format($af['credito'] ?? 0, 0) }}</td></tr>
        <tr><td>Saldo X Financiar (bruto)</td><td>{{ number_format($af['saldo_x_financiar_bruto'] ?? 0, 0) }}</td></tr>
        <tr><td>(-) Cuotas Iniciales x Recaudar</td><td>{{ number_format($af['cuotas_iniciales_x_recaudar'] ?? 0, 0) }}</td></tr>
        <tr class="total-row"><td>⑦ Saldo X Financiar</td><td>{{ number_format($af['saldo_x_financiar'] ?? 0, 0) }}</td></tr>
        <tr><td>(-) Cuotas iniciales Pendientes (por vender)</td><td>{{ number_format($af['cuotas_iniciales_pendientes_por_vender'] ?? 0, 0) }}</td></tr>
        <tr class="total-row"><td>⑧ Saldo No Financiado</td><td>{{ number_format($af['saldo_no_financiado'] ?? 0, 0) }}</td></tr>
    </tbody>
</table>

<h3>Coberturas</h3>
<div class="note">Cobertura de Garantía usa el Total de Ventas del proyecto (todas las unidades vendidas) como referencia.</div>
<div class="coverage-box {{ $cob['peor_escenario']['semaforo'] ?? '' }}">
    <strong>A) Peor Escenario</strong><br>
    {{ number_format(($cob['peor_escenario']['cobertura'] ?? 0) * 100, 2) }}%
    (máx {{ number_format(($cob['peor_escenario']['umbral_maximo'] ?? 0) * 100, 0) }}%)
</div>
<div class="coverage-box {{ $cob['mejor_escenario']['semaforo'] ?? '' }}">
    <strong>B) Mejor Escenario</strong><br>
    {{ number_format(($cob['mejor_escenario']['cobertura'] ?? 0) * 100, 2) }}%
    (máx {{ number_format(($cob['mejor_escenario']['umbral_maximo'] ?? 0) * 100, 0) }}%)
</div>
<div class="coverage-box {{ $cob['cobertura_garantia']['semaforo'] ?? '' }}">
    <strong>C) Cobertura de Garantía</strong><br>
    {{ number_format(($cob['cobertura_garantia']['cobertura'] ?? 0) * 100, 2) }}%
    (máx {{ number_format(($cob['cobertura_garantia']['umbral_maximo'] ?? 0) * 100, 0) }}%)
</div>

<h3 style="margin-top: 14px;">Observaciones del Coordinador Comercial</h3>
<p>{{ $informe->observaciones_coordinador ?: '—' }}</p>
<div class="traceability">
    Diligenciado por: {{ $informe->coordinador->name ?? '—' }} ·
    Fecha: {{ optional($informe->diligenciado_por_coordinador_at)->format('Y-m-d H:i') ?? '—' }}
</div>

</body>
</html>
