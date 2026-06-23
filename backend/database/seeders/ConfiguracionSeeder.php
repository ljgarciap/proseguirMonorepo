<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuracion;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // Proveedores IA
            [
                'clave'       => 'GEMINI_API_KEY',
                'valor'       => env('GEMINI_API_KEY'),
                'descripcion' => 'API Key de Google Gemini (OCR primario)',
                'grupo'       => 'ia_providers',
                'es_secreto'  => true,
            ],
            [
                'clave'       => 'MISTRAL_API_KEY',
                'valor'       => env('MISTRAL_API_KEY'),
                'descripcion' => 'API Key de Mistral AI (OCR fallback)',
                'grupo'       => 'ia_providers',
                'es_secreto'  => true,
            ],
            [
                'clave'       => 'MISTRAL_API_URL',
                'valor'       => env('MISTRAL_API_URL', 'https://api.mistral.ai'),
                'descripcion' => 'URL base de la API de Mistral',
                'grupo'       => 'ia_providers',
                'es_secreto'  => false,
            ],
            // Integraciones
            [
                'clave'       => 'N8N_API_TOKEN',
                'valor'       => env('N8N_API_TOKEN'),
                'descripcion' => 'Token de autenticación para webhooks de n8n',
                'grupo'       => 'integraciones',
                'es_secreto'  => true,
            ],
            [
                'clave'       => 'N8N_INTERNAL_WEBHOOK_URL',
                'valor'       => env('N8N_INTERNAL_WEBHOOK_URL'),
                'descripcion' => 'URL interna del webhook de n8n (red Docker)',
                'grupo'       => 'integraciones',
                'es_secreto'  => false,
            ],
            // Email
            [
                'clave'       => 'MAIL_HOST',
                'valor'       => env('MAIL_HOST'),
                'descripcion' => 'Servidor SMTP de correo',
                'grupo'       => 'email',
                'es_secreto'  => false,
            ],
            [
                'clave'       => 'MAIL_PORT',
                'valor'       => env('MAIL_PORT'),
                'descripcion' => 'Puerto SMTP',
                'grupo'       => 'email',
                'es_secreto'  => false,
            ],
            [
                'clave'       => 'MAIL_USERNAME',
                'valor'       => env('MAIL_USERNAME'),
                'descripcion' => 'Usuario SMTP',
                'grupo'       => 'email',
                'es_secreto'  => false,
            ],
            [
                'clave'       => 'MAIL_PASSWORD',
                'valor'       => env('MAIL_PASSWORD'),
                'descripcion' => 'Contraseña SMTP',
                'grupo'       => 'email',
                'es_secreto'  => true,
            ],
            [
                'clave'       => 'MAIL_FROM_ADDRESS',
                'valor'       => env('MAIL_FROM_ADDRESS'),
                'descripcion' => 'Dirección de correo remitente',
                'grupo'       => 'email',
                'es_secreto'  => false,
            ],
            [
                'clave'       => 'MAIL_FROM_NAME',
                'valor'       => env('MAIL_FROM_NAME', 'Proseguir Liquidez'),
                'descripcion' => 'Nombre del remitente',
                'grupo'       => 'email',
                'es_secreto'  => false,
            ],
        ];

        foreach ($configs as $config) {
            Configuracion::updateOrCreate(
                ['clave' => $config['clave']],
                $config
            );
        }
    }
}
