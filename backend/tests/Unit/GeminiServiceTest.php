<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => 'test-gemini-key']);
    }

    public function test_extract_structured_data_success()
    {
        Storage::fake('local');
        Storage::disk('local')->put('test.pdf', 'fake pdf data');
        $filePath = Storage::path('test.pdf');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'registros' => [
                                            ['Cliente' => 'Empresa A', 'Valor' => '5,000']
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = new GeminiService();
        $response = $service->extractStructuredData($filePath, 'test.pdf', 'Prompt here');

        $this->assertArrayHasKey('registros', $response);
        $this->assertEquals('Empresa A', $response['registros'][0]['Cliente']);
    }
}
