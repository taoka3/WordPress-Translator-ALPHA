<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LMStudioService
{
    protected string $baseUrl;
    protected string $defaultModel;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = SystemSetting::get('lmstudio_url', env('LMSTUDIO_BASE_URL'));
        $this->defaultModel = SystemSetting::get('lmstudio_model', env('LMSTUDIO_MODEL', 'google/gemma-4-e4b'));
        $this->timeout = (int) SystemSetting::get('lmstudio_timeout', env('LMSTUDIO_TIMEOUT', 180));
    }

    /**
     * Get the configured base URL.
     */
    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Fetch list of available models from LMStudio.
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        try {
            $response = Http::timeout(5)->get($this->getBaseUrl() . '/v1/models');
            if ($response->successful()) {
                $data = $response->json();
                $models = [];
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $m) {
                        if (isset($m['id'])) {
                            $models[] = $m['id'];
                        }
                    }
                }
                return $models;
            }
        } catch (Exception $e) {
            Log::warning('Failed to fetch LMStudio models: ' . $e->getMessage());
        }

        return [$this->defaultModel];
    }

    /**
     * Test connection to LMStudio server.
     *
     * @param string|null $url
     * @return array{success: boolean, message: string, models: array}
     */
    public function testConnection(?string $url = null): array
    {
        $targetUrl = rtrim($url ?: $this->baseUrl, '/');
        try {
            $start = microtime(true);
            $response = Http::timeout(6)->get($targetUrl . '/v1/models');
            $duration = round((microtime(true) - $start) * 1000, 1);

            if ($response->successful()) {
                $data = $response->json();
                $models = [];
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $m) {
                        if (isset($m['id'])) {
                            $models[] = $m['id'];
                        }
                    }
                }
                return [
                    'success' => true,
                    'message' => "LMStudio サーバーへの接続に成功しました ({$duration}ms)",
                    'models' => $models,
                ];
            }

            return [
                'success' => false,
                'message' => 'LMStudio サーバーからエラー応答を受信しました: ' . $response->status(),
                'models' => [],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'LMStudio サーバーに接続できませんでした: ' . $e->getMessage(),
                'models' => [],
            ];
        }
    }

    /**
     * Translate Japanese text to English using LMStudio LLM.
     *
     * @param string $text Japanese text (possibly containing placeholders and HTML tags)
     * @param string $contextType 'title', 'term', or 'content'
     * @param string|null $modelOverride
     * @return string Translated English text
     * @throws Exception
     */
    public function translate(string $text, string $contextType = 'content', ?string $modelOverride = null): string
    {
        if (trim($text) === '') {
            return '';
        }

        $model = $modelOverride ?: SystemSetting::get('lmstudio_model', $this->defaultModel);
        $url = $this->getBaseUrl() . '/v1/chat/completions';

        // Provide strict system prompt tailored to preservation of placeholders and HTML structure
        $systemPrompt = <<<EOT
You are an expert Japanese to English technical translator for WordPress.
Translate the Japanese text into clear, fluent, professional English.

MANDATORY RULES:
1. PRESERVE PLACEHOLDERS: Do NOT alter, translate, or remove any placeholder matching ___SOURCE_CODE_BLOCK_\d+___. Leave them intact.
2. PRESERVE HTML & GUTENBERG: Keep all HTML tags (like <p>, <a>, <div>, <h1>, <h2>, <h3>, <h4>, <ul>, <ol>, <li>, <img>, <span>, <strong>, <em>, <blockquote>, <br>) and Gutenberg comments (like <!-- wp:... -->, <!-- /wp:... -->) exactly intact without altering attributes or URLs.
3. OUTPUT ONLY TRANSLATION: Output ONLY the translated text. Do NOT include markdown code fences around the output, conversational preambles, notes, explanations, or greeting messages.
EOT;

        if ($contextType === 'title') {
            $userPrompt = "Translate the following WordPress post title from Japanese to natural English. Output only the English title without quotation marks:\n\n{$text}";
        } elseif ($contextType === 'term') {
            $userPrompt = "Translate this WordPress category or tag name from Japanese to English (concise, title case or appropriate English term). Output only the translated term:\n\n{$text}";
        } else {
            $userPrompt = "Translate the following WordPress post content from Japanese to English. Preserve all placeholders like ___SOURCE_CODE_BLOCK_0___ and HTML/Gutenberg tags exactly as they are:\n\n{$text}";
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.2,
            'max_tokens' => 4096,
        ];

        try {
            @set_time_limit(0);

            $response = Http::connectTimeout(10)
                ->timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if (!$response->successful()) {
                throw new Exception("LMStudio API エラー (Status {$response->status()}): " . $response->body());
            }

            $json = $response->json();
            $translatedContent = $json['choices'][0]['message']['content'] ?? null;

            if ($translatedContent === null) {
                throw new Exception("LMStudio から有効な翻訳結果が得られませんでした: " . json_encode($json));
            }

            // Strip reasoning/thinking blocks (<think>...</think>) if model outputs them
            $cleaned = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $translatedContent);
            $cleaned = trim($cleaned);

            // Strip accidental markdown fences if LMStudio wrapped the entire response in ```html ... ```
            if (preg_match('/^```(?:html|markdown|text)?\s*\n([\s\S]*?)\n```$/i', $cleaned, $m)) {
                $cleaned = trim($m[1]);
            }

            return $cleaned;
        } catch (\Throwable $e) {
            Log::error('LMStudio translation error: ' . $e->getMessage());
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
