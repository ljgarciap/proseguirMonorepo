<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Auditoría post-SCRUM-348 (fallo en prod de solicitudes_credito.garantia
 * como string()/VARCHAR 255): las 3 columnas de garantía de
 * operacion_carteras vienen de la misma fuente de riesgo — extracción
 * libre por OCR/LLM (ProcessUploadJob, prompt sin formato fijo, ver
 * OcrPersistenceService::persist()) sin longitud acotada. 'garantia_detalle'
 * y 'tipo_garantia' ya se pasaron a text() en
 * 2026_03_02_021053_change_garantia_columns_to_text_in_operacion_carteras_table;
 * 'estado_garantia' quedó afuera de ese fix pese al mismo origen y riesgo —
 * corregido en 2026_09_10_220001_change_estado_garantia_to_text_in_operacion_carteras_table.
 *
 * Se valida el tipo real de columna en el schema, no el comportamiento de
 * inserción — SQLite (motor de esta suite) no trunca ni rechaza un string
 * más largo que la columna, así que un test funcional no detectaría una
 * regresión de vuelta a string().
 */
class OperacionCarteraSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_columnas_de_garantia_son_text_para_soportar_contenido_largo(): void
    {
        foreach (['garantia_detalle', 'tipo_garantia', 'estado_garantia'] as $columna) {
            $this->assertEquals(
                'text',
                Schema::getColumnType('operacion_carteras', $columna),
                "'{$columna}' debe ser text() — un string() (VARCHAR 255) revienta en MySQL modo estricto con contenido largo extraído por OCR/LLM (ver SCRUM-348)."
            );
        }
    }
}
