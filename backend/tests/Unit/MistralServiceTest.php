<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MistralService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MistralServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mistral.api_key' => 'test-api-key']);
    }

    public function test_upload_file_success()
    {
        Storage::fake('local');
        Storage::disk('local')->put('temp_doc.pdf', 'pdf contents');
        $filePath = Storage::path('temp_doc.pdf');

        Http::fake([
            'https://api.mistral.ai/v1/files' => Http::response(['id' => 'file-uuid-123'], 200),
        ]);

        $service = new MistralService();
        $fileId = $service->uploadFile($filePath, 'temp_doc.pdf');

        $this->assertEquals('file-uuid-123', $fileId);
    }

    public function test_perform_ocr_success()
    {
        Http::fake([
            'https://api.mistral.ai/v1/ocr' => Http::response([
                'pages' => [
                    ['markdown' => 'Page 1 text content'],
                ],
            ], 200),
        ]);

        $service = new MistralService();
        $pages = $service->performOcr('file-uuid-123');

        $this->assertCount(1, $pages);
        $this->assertEquals('Page 1 text content', $pages[0]['markdown']);
    }

    public function test_get_structured_extraction_success()
    {
        Http::fake([
            'https://api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['extracted_field' => 'some value']),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new MistralService();
        $data = $service->getStructuredExtraction('Extract JSON please', 'Raw text here');

        $this->assertEquals(['extracted_field' => 'some value'], $data);
    }
}
