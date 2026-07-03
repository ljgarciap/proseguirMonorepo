<?php

namespace App\Services;

use App\Services\ConfiguracionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MistralService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = ConfiguracionService::get('MISTRAL_API_KEY', config('services.mistral.api_key')) ?? '';
        $this->apiUrl = ConfiguracionService::get('MISTRAL_API_URL', config('services.mistral.api_url', 'https://api.mistral.ai'));
    }

    /**
     * Uploads a file to Mistral AI for OCR processing.
     *
     * @param string $filePath Absolute path to the file
     * @param string $fileName Original name of the file
     * @return string File ID returned by Mistral
     * @throws \Exception
     */
    public function uploadFile(string $filePath, string $fileName): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception("MISTRAL_API_KEY is not configured.");
        }

        Log::info("Uploading file to Mistral AI for OCR: {$fileName}");

        if (!file_exists($filePath)) {
            throw new \Exception("File not found at path: {$filePath}");
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->attach(
            'file',
            file_get_contents($filePath),
            $fileName
        )->post($this->apiUrl . '/v1/files', [
            'purpose' => 'ocr',
        ]);

        if ($response->failed()) {
            Log::error("Mistral File Upload failed: " . $response->body());
            throw new \Exception("Mistral upload failed: " . $response->status() . " - " . $response->body());
        }

        $fileId = $response->json('id');
        if (empty($fileId)) {
            throw new \Exception("Failed to get file ID from Mistral response: " . $response->body());
        }

        Log::info("Mistral file upload success. File ID: {$fileId}");
        return $fileId;
    }

    /**
     * Performs OCR on an uploaded file.
     *
     * @param string $fileId Mistral file ID
     * @return array Pages returned by Mistral OCR
     * @throws \Exception
     */
    public function performOcr(string $fileId): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("MISTRAL_API_KEY is not configured.");
        }

        Log::info("Requesting OCR from Mistral AI for file ID: {$fileId}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/v1/ocr', [
            'document' => [
                'type' => 'file',
                'file_id' => $fileId,
            ],
            'model' => 'mistral-ocr-latest',
        ]);

        if ($response->failed()) {
            Log::error("Mistral OCR request failed: " . $response->body());
            throw new \Exception("Mistral OCR failed: " . $response->status() . " - " . $response->body());
        }

        $pages = $response->json('pages');
        if (!is_array($pages)) {
            throw new \Exception("Invalid OCR response structure: pages field missing or not an array. Response: " . $response->body());
        }

        Log::info("Mistral OCR completed. Retrieved " . count($pages) . " pages.");
        return $pages;
    }

    /**
     * Sends a chat completion request to parse text structurally as JSON.
     *
     * @param string $prompt Prompt instruction
     * @param string $text Markdown/Text content to process
     * @return array Extracted JSON object
     * @throws \Exception
     */
    public function getStructuredExtraction(string $prompt, string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("MISTRAL_API_KEY is not configured.");
        }

        Log::info("Sending structured extraction chat request to Mistral AI.");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(240)->post($this->apiUrl . '/v1/chat/completions', [
            'model' => 'mistral-small-latest',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nTexto a procesar: " . $text,
                ]
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
        ]);

        if ($response->failed()) {
            Log::error("Mistral Chat Completion failed: " . $response->body());
            throw new \Exception("Mistral Chat Completion failed: " . $response->status() . " - " . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (empty($content)) {
            throw new \Exception("Failed to get content from Mistral Chat Completion: " . $response->body());
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse JSON response from Mistral content: " . $content);
        }

        return $decoded;
    }
}
