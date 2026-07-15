<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AiProviderClient
{
    /**
     * Send a request to the given AI provider.
     *
     * @param AiProvider $provider
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param array $options Optional configs like temperature, response_format, etc.
     * @return Response
     */
    public function sendRequest(AiProvider $provider, string $systemPrompt, string $userPrompt, array $options = []): Response
    {
        $baseUrl = rtrim($provider->base_url, '/');
        $model = $provider->model_name ?? 'gemini-1.5-pro';
        $providerType = strtolower($provider->provider_type ?? '');
        $temperature = $options['temperature'] ?? 0.1;

        if (str_contains($providerType, 'gemini') || str_contains(strtolower($provider->name), 'gemini')) {
            $endpoint = str_contains($baseUrl, '/models/')
                ? $baseUrl
                : $baseUrl . '/models/' . $model . ':generateContent';
            $apiUrl = $endpoint . '?key=' . urlencode($provider->api_key);
            
            $requestPayload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\n" . $userPrompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => (float)$temperature,
                ]
            ];

            // Some gemini models might fail with responseMimeType as per earlier findings. We won't inject it by default unless strictly required.
            
            return Http::timeout(60)->post($apiUrl, $requestPayload);
        } else {
            // Asumsi OpenAI atau penyedia kompatibel OpenAI lainnya
            $apiUrl = $baseUrl . '/chat/completions';
            $requestPayload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => (float)$temperature,
            ];

            if (isset($options['response_format']) && $options['response_format'] === 'json_object') {
                $requestPayload['response_format'] = ['type' => 'json_object'];
            }

            return Http::withToken($provider->api_key)->timeout(60)->post($apiUrl, $requestPayload);
        }
    }

    /**
     * Parse the successful response into plain text based on the provider type.
     */
    public function parseResponse(AiProvider $provider, Response $response): ?string
    {
        $responseData = $response->json();
        $providerType = strtolower($provider->provider_type ?? '');

        if (str_contains($providerType, 'gemini') || str_contains(strtolower($provider->name), 'gemini')) {
            return $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } else {
            return $responseData['choices'][0]['message']['content'] ?? null;
        }
    }
}
