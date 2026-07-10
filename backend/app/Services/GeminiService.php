<?php

namespace App\Services;

use App\Services\ConfiguracionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = ConfiguracionService::get('GEMINI_API_KEY', config('services.gemini.api_key')) ?? '';
    }

    /**
     * Extracts structured data directly from a document using Gemini 1.5 Flash.
     *
     * @param string $filePath Absolute path to the local file
     * @param string $fileName Original name of the file
     * @param string $prompt Prompt instructions
     * @return array Decoded structured JSON data
     * @throws \Exception
     */
    public function extractStructuredData(string $filePath, string $fileName, string $prompt): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("GEMINI_API_KEY is not configured.");
        }

        Log::info("Sending document to Gemini 1.5 Flash: {$fileName}");

        if (!file_exists($filePath)) {
            throw new \Exception("File not found at: {$filePath}");
        }

        $fileContent = file_get_contents($filePath);
        $base64Data = base64_encode($fileContent);
        $mimeType = mime_content_type($filePath) ?: 'application/pdf';

        // Set up the API URL
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $this->apiKey;

        // Structured prompt to ensure it fits the category requirement
        $fullPrompt = $prompt . "\n\nAnaliza todo el documento provisto y extrae la información requerida estrictamente en formato JSON de acuerdo al esquema indicado.";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(240)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $fullPrompt
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64Data
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ]);

        if ($response->failed()) {
            Log::error("Gemini API request failed: " . $response->body());
            throw new \Exception("Gemini API failed: " . $response->status() . " - " . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (empty($text)) {
            throw new \Exception("Empty response or invalid structure from Gemini: " . $response->body());
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to decode JSON from Gemini response content: " . $text);
        }

        Log::info("Gemini extraction successful for: {$fileName}");
        return $decoded;
    }
}
