<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\OperacionConfirming;
use App\Models\OperacionFactoring;
use App\Models\PagoCompraventa;
use App\Models\PagoFactoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-230 — pestaña "Cartera Factoring" del Dashboard: cruce
 * Factoring↔Pagos Factoring (por Doc#) y CompraVenta(Confirming)↔Pagos
 * CompraVenta (por Id Título), conservando facturas sin pago.
 */
class DashboardCarteraFactoringTest extends TestCase
{
    use RefreshDatabase;

    private User $operativo;
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $cc = DocumentType::where('codigo', 'CC')->first()
            ?? DocumentType::create(['nombre' => 'Cedula', 'codigo' => 'CC']);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.cf.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'op_cf_' . uniqid(),
            'tipo_documento_id' => $cc->id,
            'roles' => ['operativo'],
        ]);

        $this->superadmin = User::create([
            'name' => 'Superadmin Test',
            'email' => 'superadmin.cf.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'sa_cf_' . uniqid(),
            'tipo_documento_id' => $cc->id,
            'roles' => ['superadmin'],
        ]);
    }

    private function seedFacturaConPago(): void
    {
        OperacionFactoring::create([
            'operacion' => 'OP-1',
            'cliente' => 'Cliente Uno',
            'nit_cliente' => '900111222',
            'factura_numero' => '001234',
            'monto' => 10000000,
            'pagador' => 'Pagador Origen',
            'nit_pagador' => '800111222',
            'fecha_desembolso' => '01/01/2026',
            'fecha_vencimiento' => '01/03/2026',
        ]);

        PagoFactoring::create([
            'pago_nro' => 'PAGO-1',
            'fecha_pago' => '15/02/2026',
            'cliente' => 'Cliente Uno',
            'nit' => '900111222',
            'factura_nro' => '001234',
            'cc_o_nit' => '800111222',
            'pagador' => 'Pagador Real',
            'fecha_inicial' => '01/01/2026',
            'fecha_final' => '01/03/2026',
            'monto_pagado' => 6000000,
            'saldo_restante' => 4000000,
        ]);
    }

    private function seedFacturaSinPago(): void
    {
        OperacionFactoring::create([
            'operacion' => 'OP-2',
            'cliente' => 'Cliente Dos',
            'nit_cliente' => '900333444',
            'factura_numero' => '005678',
            'monto' => 3000000,
            'pagador' => 'Pagador Origen 2',
            'nit_pagador' => '800333444',
            'fecha_desembolso' => '01/01/2026',
            'fecha_vencimiento' => '01/03/2026',
        ]);
    }

    private function seedTituloConPago(): void
    {
        OperacionConfirming::create([
            'operacion' => 'OPF-1',
            'emisor' => 'Emisor Uno',
            'emisor_nit' => '900555666',
            'deudor' => 'Deudor Uno',
            'deudor_nit' => '800555666',
            'id_titulo' => 'T-001',
            'fecha_inicial' => '01/01/2026',
            'fecha_final' => '01/03/2026',
            'valor_pagar_deudor' => 5000000,
        ]);

        PagoCompraventa::create([
            'pagador' => 'Deudor Real',
            'nit_pagador' => '800555666',
            'cliente' => 'Emisor Uno',
            'nit_cliente' => '900555666',
            'fecha_recaudo' => '20/02/2026',
            'id_titulo' => 'T-001',
            'valor_factura' => 5000000,
            'total_pagado' => 5000000,
            'saldo_despues_pago' => 0,
        ]);
    }

    public function test_operativo_puede_consultar_indicadores_y_detalle(): void
    {
        $this->seedFacturaConPago();
        $this->seedFacturaSinPago();
        $this->seedTituloConPago();

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/dashboard/cartera-factoring');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame(3, $data['indicadores']['facturas_origen']);
        $this->assertSame(2, $data['indicadores']['pagos_coincidentes']);
        $this->assertSame(1, $data['indicadores']['registros_sin_pago']);
        // saldo despues de pago: 4.000.000 (factoring) + 0 (compraventa) = 4.000.000
        $this->assertEquals(4000000, $data['indicadores']['valor_cartera_total_despues_pago']);
        // valor pagado: 6.000.000 (factoring) + 5.000.000 (compraventa)
        $this->assertEquals(11000000, $data['indicadores']['total_valor_pagado']);

        $this->assertCount(3, $data['detalle']['data']);
    }

    public function test_cruza_por_doc_igualdad_exacta(): void
    {
        $this->seedFacturaConPago();

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/dashboard/cartera-factoring');

        $fila = collect($response->json('detalle.data'))->firstWhere('factura_numero', '001234');
        $this->assertNotNull($fila);
        $this->assertSame(1, $fila['tiene_pago']);
        $this->assertEquals(4000000, $fila['saldo_despues_pago']);
        $this->assertEquals(6000000, $fila['valor_pagado']);
        $this->assertSame('Pagador Real', $fila['pagador']);
    }

    public function test_registro_sin_pago_no_trae_datos_de_pago(): void
    {
        $this->seedFacturaSinPago();

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/dashboard/cartera-factoring');

        $fila = collect($response->json('detalle.data'))->firstWhere('factura_numero', '005678');
        $this->assertNotNull($fila);
        $this->assertSame(0, $fila['tiene_pago']);
        $this->assertNull($fila['valor_pagado']);
        $this->assertNull($fila['saldo_despues_pago']);
    }

    public function test_top10_clientes_ordenado_por_saldo_descendente(): void
    {
        $this->seedFacturaConPago();
        $this->seedTituloConPago();

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/dashboard/cartera-factoring');

        $top10 = $response->json('top10_clientes');
        // Solo "Cliente Uno" tiene saldo > 0 (4.000.000); Compraventa quedó en 0 y no debe aparecer.
        $this->assertCount(1, $top10);
        $this->assertSame('Cliente Uno', $top10[0]['cliente']);
    }

    public function test_filtro_por_factura_numero(): void
    {
        $this->seedFacturaConPago();
        $this->seedFacturaSinPago();

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/dashboard/cartera-factoring?factura=005678');

        $data = $response->json('detalle.data');
        $this->assertCount(1, $data);
        $this->assertSame('005678', $data[0]['factura_numero']);
    }

    public function test_filtro_por_cliente_nit(): void
    {
        $this->seedFacturaConPago();
        $this->seedFacturaSinPago();

        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/dashboard/cartera-factoring?cliente=900333444');

        $data = $response->json('detalle.data');
        $this->assertCount(1, $data);
        $this->assertSame('Cliente Dos', $data[0]['cliente']);
    }

    public function test_gerente_no_autorizado(): void
    {
        $cc = DocumentType::where('codigo', 'CC')->first();
        $gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.cf.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'ger_cf_' . uniqid(),
            'tipo_documento_id' => $cc->id,
            'roles' => ['gerente'],
        ]);

        Passport::actingAs($gerente);
        $this->getJson('/api/dashboard/cartera-factoring')->assertStatus(403);
    }

    public function test_operativo_no_puede_exportar_solo_superadmin(): void
    {
        $this->seedFacturaConPago();

        Passport::actingAs($this->operativo);
        $this->get('/api/dashboard/cartera-factoring/export')->assertStatus(403);

        Passport::actingAs($this->superadmin);
        $this->get('/api/dashboard/cartera-factoring/export')->assertStatus(200);
    }
}
