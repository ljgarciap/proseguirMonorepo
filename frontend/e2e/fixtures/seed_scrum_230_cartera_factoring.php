<?php
// Seed manual para validación visual de SCRUM-230 (Dashboard Cartera
// Factoring). Identificable por el prefijo PW-CF-. Idempotente
// (firstOrCreate) — se puede re-correr sin limpiar nada a mano.
//
// PW-CF-001: factura de Factoring con pago parcial coincidente (saldo
// después de pago 3.000.000) — ejercita el camino "con pago" y puebla el
// Top 10 / panel de clientes.
// PW-CF-002: factura de Factoring sin pago — ejercita el camino "sin pago"
// (los 5 campos de pago deben quedar vacíos, no en cero).

use App\Models\OperacionFactoring;
use App\Models\PagoFactoring;

OperacionFactoring::firstOrCreate(
    ['factura_numero' => 'PW-CF-001'],
    [
        'operacion' => 'OP-PW-CF-1',
        'cliente' => 'Cliente Playwright CF',
        'nit_cliente' => '900999888',
        'monto' => 8000000,
        'pagador' => 'Pagador Origen PW',
        'nit_pagador' => '800999888',
        'fecha_desembolso' => '01/07/2026',
        'fecha_vencimiento' => '01/09/2026',
    ]
);

PagoFactoring::firstOrCreate(
    ['factura_nro' => 'PW-CF-001'],
    [
        'pago_nro' => 'PAGO-PW-CF-1',
        'fecha_pago' => '15/08/2026',
        'cliente' => 'Cliente Playwright CF',
        'nit' => '900999888',
        'cc_o_nit' => '800999888',
        'pagador' => 'Pagador Real PW',
        'fecha_inicial' => '01/07/2026',
        'fecha_final' => '01/09/2026',
        'monto_pagado' => 5000000,
        'saldo_restante' => 3000000,
    ]
);

OperacionFactoring::firstOrCreate(
    ['factura_numero' => 'PW-CF-002'],
    [
        'operacion' => 'OP-PW-CF-2',
        'cliente' => 'Cliente Playwright CF Dos',
        'nit_cliente' => '900777666',
        'monto' => 2000000,
        'pagador' => 'Pagador Origen PW2',
        'nit_pagador' => '800777666',
        'fecha_desembolso' => '01/07/2026',
        'fecha_vencimiento' => '01/09/2026',
    ]
);

echo "Sembrado PW-CF-001 (con pago) y PW-CF-002 (sin pago).\n";
