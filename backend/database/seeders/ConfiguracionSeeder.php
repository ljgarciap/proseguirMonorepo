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
            // Análisis Financiero (SCRUM-155)
            [
                'clave'       => 'ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM',
                'valor'       => '5',
                'descripcion' => 'Tolerancia (en millones de COP) para la diferencia Activo - (Pasivo + Patrimonio) del último año antes de bloquear la confirmación del Análisis Financiero.',
                'grupo'       => 'analisis_financiero',
                'es_secreto'  => false,
            ],
            // Duración de sesión (SCRUM-161)
            [
                'clave'       => 'SESSION_DURATION_OPTIONS',
                'valor'       => json_encode([
                    ['value' => 30, 'label' => '30 minutos'],
                    ['value' => 60, 'label' => '1 hora'],
                    ['value' => 240, 'label' => '4 horas'],
                    ['value' => 480, 'label' => '8 horas'],
                    ['value' => 1440, 'label' => '24 horas'],
                ]),
                'descripcion' => 'Lista cerrada de duraciones de sesión (minutos) que el usuario puede elegir desde /perfil. JSON de objetos {value,label}.',
                'grupo'       => 'sesion',
                'es_secreto'  => false,
            ],
            [
                'clave'       => 'SESSION_DURATION_DEFAULT',
                'valor'       => '480',
                'descripcion' => 'Duración de sesión (minutos) asignada por defecto a un usuario que no eligió ninguna preferencia todavía.',
                'grupo'       => 'sesion',
                'es_secreto'  => false,
            ],
            [
                'clave'       => 'SESSION_DURATION_MAX',
                'valor'       => '1440',
                'descripcion' => 'Techo absoluto (minutos) para la duración de sesión: ninguna opción de SESSION_DURATION_OPTIONS puede aplicarse por encima de este valor, aunque exista en la lista.',
                'grupo'       => 'sesion',
                'es_secreto'  => false,
            ],
        ];

        foreach ($configs as $config) {
            $existing = Configuracion::where('clave', $config['clave'])->first();

            if ($existing) {
                // No se toca 'valor': puede haber sido configurado manualmente desde
                // /configuraciones y este seeder corre en cada deploy (ver deploy.yml).
                // Sobreescribirlo con env() aquí borraría esa configuración en cada release.
                $existing->update([
                    'descripcion' => $config['descripcion'],
                    'grupo'       => $config['grupo'],
                    'es_secreto'  => $config['es_secreto'],
                ]);
            } else {
                Configuracion::create($config);
            }
        }
    }
}
