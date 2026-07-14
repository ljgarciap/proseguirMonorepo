<?php

namespace Tests\Feature;

use App\Services\ConciliationService;
use Tests\TestCase;

class ConciliationServiceValorParsingTest extends TestCase
{
    private function parseBankText(string $text): array
    {
        $service = new ConciliationService();
        $method = new \ReflectionMethod($service, 'parseBankText');
        $method->setAccessible(true);

        return $method->invoke($service, $text);
    }

    public function test_parses_valid_amounts_with_thousand_separator_and_decimals(): void
    {
        $text = "2026/03/02|CONSIGNACION CORRESPONSAL CB|CNB REDES|26,700.00";

        $data = $this->parseBankText($text);

        $this->assertCount(1, $data);
        $this->assertEquals(26700.0, $data[0]['amount']);
        $this->assertEquals('2026-03-02', $data[0]['date']);
    }

    public function test_parses_negative_amounts(): void
    {
        $text = "2026/03/13|PAGO A PROVE CONSTRUCCIONES|SANCANCIO|8100024555|-1,194,288,000.00";

        $data = $this->parseBankText($text);

        $this->assertCount(1, $data);
        $this->assertEquals(1194288000.0, $data[0]['amount']);
    }

    /**
     * Caso real que rompió la conciliación en test (2026-07-06): dos números de
     * REFERENCIA/DOCUMENTO quedaron pegados sin separador por el helper de
     * extracción de PDF, y el parser los tomaba como si fueran el VALOR
     * (ej. "1003002751201047" ~ 1 cuatrillón), desbordando la columna
     * total_amount (decimal 15,2) al sumarlos.
     */
    public function test_rejects_concatenated_reference_numbers_without_currency_format(): void
    {
        $text = "2026/03/11|PAGO DE PROV CCA ALIANZA FID|SANCANCIO|800194297|1003002751201047";

        $data = $this->parseBankText($text);

        $this->assertCount(0, $data);
    }

    public function test_rejects_reference_number_with_stray_space_instead_of_separator(): void
    {
        $text = "2026/03/11|PAGO DE PROV CREDICORP CAPIT|SANCANCIO|860068182|4028954 3";

        $data = $this->parseBankText($text);

        $this->assertCount(0, $data);
    }

    public function test_rejects_rows_where_last_column_is_a_person_name(): void
    {
        $text = "2026/03/11|TRANSFERENCIA DESDE NEQUI|SANCANCIO|WILSON MURILLO";

        $data = $this->parseBankText($text);

        $this->assertCount(0, $data);
    }

    public function test_mixed_valid_and_invalid_rows_only_keeps_valid_ones(): void
    {
        $text = implode("\n", [
            "2026/03/11|PAGO DE PROV CCA ALIANZA FID|SANCANCIO|800194297|1003002751201047",
            "2026/03/11|TRASLADO DE FONDO DE INVERS|SANCANCIO|2,000,000,000.00",
            "2026/03/11|CONSIGNACION CORRESPONSAL CB|CNB REDES|44,800.00",
            "2026/03/11|TRANSFERENCIA DESDE NEQUI|SANCANCIO|WILSON MURILLO",
        ]);

        $data = $this->parseBankText($text);

        $this->assertCount(2, $data);
        $this->assertEqualsCanonicalizing([2000000000.0, 44800.0], array_column($data, 'amount'));
    }

    /**
     * Caso real SCRUM-125: el pago ocupa dos renglones en el PDF — la fecha y
     * descripción (terminando en el nombre del remitente) quedan en una línea,
     * y el VALOR queda solo en la línea siguiente, sin fecha. Antes del fix
     * ambas líneas se descartaban por separado y el pago nunca entraba a la
     * conciliación (aparecía como "SOLO EN SUSUERTE").
     */
    public function test_reassembles_payment_split_across_two_lines(): void
    {
        $text = implode("\n", [
            "2026/07/10|TRANSFERENCIA DESDE NEQUI|SANCANCIO|WILSON MURILLO",
            "900,354.00",
        ]);

        $data = $this->parseBankText($text);

        $this->assertCount(1, $data);
        $this->assertEquals(900354.0, $data[0]['amount']);
        $this->assertEquals('2026-07-10', $data[0]['date']);
    }

    public function test_reassembled_multiline_payment_does_not_swallow_following_valid_row(): void
    {
        $text = implode("\n", [
            "2026/07/10|TRANSFERENCIA DESDE NEQUI|SANCANCIO|WILSON MURILLO",
            "900,354.00",
            "2026/07/11|CONSIGNACION CORRESPONSAL CB|CNB REDES|44,800.00",
        ]);

        $data = $this->parseBankText($text);

        $this->assertCount(2, $data);
        $this->assertEqualsCanonicalizing([900354.0, 44800.0], array_column($data, 'amount'));
    }

    public function test_orphan_line_with_no_preceding_transaction_is_discarded(): void
    {
        $text = implode("\n", [
            "EXTRACTO BANCARIO SUSUERTE JULIO 2026",
            "900,354.00",
            "2026/07/11|CONSIGNACION CORRESPONSAL CB|CNB REDES|44,800.00",
        ]);

        $data = $this->parseBankText($text);

        $this->assertCount(1, $data);
        $this->assertEquals(44800.0, $data[0]['amount']);
    }
}
