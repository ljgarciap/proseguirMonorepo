<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_config_with_env_value_when_missing(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->assertDatabaseHas('configuraciones', [
            'clave' => 'MISTRAL_API_URL',
            'valor' => 'https://api.mistral.ai',
        ]);
    }

    public function test_seeder_does_not_overwrite_existing_value_on_rerun(): void
    {
        // El deploy corre este seeder en cada release (ver .github/workflows/deploy.yml).
        // Si una API key fue configurada manualmente desde /configuraciones, el
        // siguiente deploy NO debe pisarla con el valor de .env.
        $this->seed(ConfiguracionSeeder::class);

        Configuracion::where('clave', 'GEMINI_API_KEY')->update([
            'valor' => 'VALOR-CONFIGURADO-MANUALMENTE-EN-PROD',
        ]);

        $this->seed(ConfiguracionSeeder::class);

        $this->assertDatabaseHas('configuraciones', [
            'clave' => 'GEMINI_API_KEY',
            'valor' => 'VALOR-CONFIGURADO-MANUALMENTE-EN-PROD',
        ]);
    }

    public function test_seeder_refreshes_metadata_without_touching_valor(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        Configuracion::where('clave', 'MISTRAL_API_KEY')->update([
            'valor' => 'SECRETO-EN-PRODUCCION',
            'descripcion' => 'descripcion desactualizada',
        ]);

        $this->seed(ConfiguracionSeeder::class);

        $config = Configuracion::where('clave', 'MISTRAL_API_KEY')->first();
        $this->assertEquals('SECRETO-EN-PRODUCCION', $config->valor);
        $this->assertEquals('API Key de Mistral AI (OCR fallback)', $config->descripcion);
    }

    /**
     * SCRUM-329 (incidente 2026-09-04): una 'descripcion' de 265 caracteres
     * (columna string() = VARCHAR(255)) reventó el deploy a test con
     * "Data too long for column" — MySQL en modo estricto lo rechaza, pero
     * SQLite (motor de esta misma suite) trunca en silencio sin fallar, así
     * que ningún test funcional de arriba lo detectó. Se valida el límite
     * directo sobre el array fuente del seeder, sin depender del motor de
     * BD.
     */
    public function test_ninguna_descripcion_ni_clave_supera_el_limite_de_la_columna(): void
    {
        // SQLite (motor de esta suite) no trunca ni rechaza un string más
        // largo que la columna — solo lo guarda entero. Comparar lo
        // guardado contra el original detecta igual el caso que MySQL en
        // modo estricto rechazaría con "Data too long for column".
        $this->seed(ConfiguracionSeeder::class);

        foreach (Configuracion::all() as $config) {
            $this->assertLessThanOrEqual(
                255,
                mb_strlen($config->descripcion ?? ''),
                "descripcion de '{$config->clave}' supera 255 caracteres (columna string())."
            );
            $this->assertLessThanOrEqual(
                255,
                mb_strlen($config->clave),
                "clave '{$config->clave}' supera 255 caracteres (columna string())."
            );
        }
    }
}
