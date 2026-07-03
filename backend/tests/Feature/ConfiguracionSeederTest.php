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
}
