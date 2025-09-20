<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private string $defaultProvider;

    private string $openaiApiKey;

    private string $anthropicApiKey;

    private string $gptModel;

    private string $claudeModel;

    public function __construct()
    {
        $this->defaultProvider = config('services.ai.default_provider', 'openai');
        $this->openaiApiKey = config('services.openai.api_key');
        $this->anthropicApiKey = config('services.anthropic.api_key');
        $this->gptModel = config('services.ai.gpt_model', 'gpt-4');
        $this->claudeModel = config('services.ai.claude_model', 'claude-3-sonnet-20240229');
    }

    public function normalizeFragranceData(string $brandName, string $fragranceName, ?string $provider = null): array
    {
        $provider = $provider ?: $this->defaultProvider;

        $prompt = $this->buildNormalizationPrompt($brandName, $fragranceName);

        try {
            $response = match ($provider) {
                'openai' => $this->callOpenAI($prompt),
                'anthropic' => $this->callAnthropic($prompt),
                default => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}")
            };

            return $this->parseNormalizationResponse($response);
        } catch (\Exception $e) {
            Log::error('AI normalization failed', [
                'provider' => $provider,
                'brand' => $brandName,
                'fragrance' => $fragranceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildNormalizationPrompt(string $brandName, string $fragranceName): string
    {
        return "香水情報を正規化してください。以下の情報から、正確な香水情報をJSON形式で返してください。

ブランド名: {$brandName}
香水名: {$fragranceName}

以下のJSON形式で回答してください：
{
    \"normalized_brand\": \"正規化されたブランド名\",
    \"normalized_fragrance_name\": \"正規化された香水名\",
    \"concentration_type\": \"EDP/EDT/EDP/Parfum/その他\",
    \"launch_year\": \"発売年（不明の場合はnull）\",
    \"fragrance_family\": \"香りファミリー（フローラル/ウッディ/オリエンタル等）\",
    \"top_notes\": [\"トップノート1\", \"トップノート2\"],
    \"middle_notes\": [\"ミドルノート1\", \"ミドルノート2\"],
    \"base_notes\": [\"ベースノート1\", \"ベースノート2\"],
    \"suitable_seasons\": [\"春\", \"夏\", \"秋\", \"冬\"],
    \"suitable_scenes\": [\"ビジネス\", \"カジュアル\", \"フォーマル\", \"デート\"],
    \"description_ja\": \"日本語での香水の説明\",
    \"description_en\": \"English description of the fragrance\"
}

注意：
- ブランド名は正式名称に正規化してください
- 香水名も正式名称に正規化してください
- 情報が不明な場合はnullを設定してください
- ノート情報は具体的な香料名で回答してください";
    }

    private function callOpenAI(string $prompt): string
    {
        if (! $this->openaiApiKey) {
            throw new \Exception('OpenAI API key is not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->gptModel,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2000,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            throw new \Exception('OpenAI API request failed: '.$response->body());
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function callAnthropic(string $prompt): string
    {
        if (! $this->anthropicApiKey) {
            throw new \Exception('Anthropic API key is not configured');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->anthropicApiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->claudeModel,
            'max_tokens' => 2000,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \Exception('Anthropic API request failed: '.$response->body());
        }

        $data = $response->json();

        return $data['content'][0]['text'] ?? '';
    }

    private function parseNormalizationResponse(string $response): array
    {
        // JSONを抽出（マークダウンコードブロックがある場合を考慮）
        $jsonPattern = '/```json\s*(.*?)\s*```/s';
        if (preg_match($jsonPattern, $response, $matches)) {
            $jsonString = $matches[1];
        } else {
            // JSONブロックがない場合は全体をJSONとして扱う
            $jsonString = $response;
        }

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse AI response as JSON: '.json_last_error_msg());
        }

        return $data;
    }

    public function getAvailableProviders(): array
    {
        $providers = [];

        if ($this->openaiApiKey) {
            $providers[] = 'openai';
        }

        if ($this->anthropicApiKey) {
            $providers[] = 'anthropic';
        }

        return $providers;
    }
}
