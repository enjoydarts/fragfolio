<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private array $costPerToken;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
        $this->model = config('services.ai.claude_model', 'claude-3-sonnet-20240229');
        $this->costPerToken = [
            'claude-3-sonnet-20240229' => ['input' => 0.003 / 1000, 'output' => 0.015 / 1000],
            'claude-3-haiku-20240307' => ['input' => 0.00025 / 1000, 'output' => 0.00125 / 1000],
        ];

        if (! $this->apiKey) {
            throw new \Exception('Anthropic API key is not configured');
        }
    }

    public function complete(string $query, array $options = []): array
    {
        $type = $options['type'] ?? 'brand';
        $limit = $options['limit'] ?? 10;
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildCompletionPrompt($query, $type, $limit, $language);

        $startTime = microtime(true);
        $response = $this->makeRequest($prompt, ['max_tokens' => 500]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseJsonResponse($response);

        return [
            'suggestions' => $result['suggestions'] ?? [],
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $this->estimateCost($response),
        ];
    }

    public function normalize(string $brandName, string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildNormalizationPrompt($brandName, $fragranceName, $language);

        $startTime = microtime(true);
        $response = $this->makeRequest($prompt, ['max_tokens' => 1000]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseJsonResponse($response);

        return [
            'normalized_data' => $result,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $this->estimateCost($response),
        ];
    }

    public function suggestNotes(string $brandName, string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildNotesSuggestionPrompt($brandName, $fragranceName, $language);

        $startTime = microtime(true);
        $response = $this->makeRequest($prompt, ['max_tokens' => 800]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseJsonResponse($response);

        return [
            'notes' => $result['notes'] ?? [],
            'confidence_score' => $result['confidence_score'] ?? 0.0,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $this->estimateCost($response),
        ];
    }

    public function suggestAttributes(string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildAttributesSuggestionPrompt($fragranceName, $language);

        $startTime = microtime(true);
        $response = $this->makeRequest($prompt, ['max_tokens' => 600]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseJsonResponse($response);

        return [
            'attributes' => $result['attributes'] ?? [],
            'confidence_score' => $result['confidence_score'] ?? 0.0,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $this->estimateCost($response),
        ];
    }

    public function calculateCost(array $usage): float
    {
        $model = $usage['model'] ?? $this->model;
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $rates = $this->costPerToken[$model] ?? $this->costPerToken['claude-3-sonnet-20240229'];

        return ($inputTokens * $rates['input']) + ($outputTokens * $rates['output']);
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    private function makeRequest(string $prompt, array $options = []): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $options['max_tokens'] ?? 1000,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                throw new \Exception('Anthropic API request failed: '.$response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Anthropic API request failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseJsonResponse(array $response): array
    {
        $content = $response['content'][0]['text'] ?? '';

        // JSONを抽出（マークダウンコードブロックがある場合を考慮）
        $jsonPattern = '/```json\s*(.*?)\s*```/s';
        if (preg_match($jsonPattern, $content, $matches)) {
            $jsonString = $matches[1];
        } else {
            $jsonString = $content;
        }

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse AI response as JSON: '.json_last_error_msg());
        }

        return $data;
    }

    private function estimateCost(array $response): float
    {
        $usage = $response['usage'] ?? [];

        return $this->calculateCost([
            'model' => $this->model,
            'input_tokens' => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
        ]);
    }

    private function buildCompletionPrompt(string $query, string $type, int $limit, string $language): string
    {
        $typeText = $type === 'brand' ? 'ブランド名' : '香水名';

        return "香水の{$typeText}の補完候補を提案してください。

入力: {$query}
タイプ: {$type}
最大候補数: {$limit}
言語: {$language}

以下のJSON形式で回答してください：
{
    \"suggestions\": [
        {
            \"text\": \"候補テキスト\",
            \"confidence\": 0.95,
            \"type\": \"{$type}\",
            \"metadata\": {
                \"normalized_name\": \"正規化名\",
                \"country\": \"国名\",
                \"established_year\": \"設立年\"
            }
        }
    ]
}

注意：
- 信頼度は0.0-1.0で設定
- 候補は関連性の高い順に並べる
- 実在する{$typeText}のみ提案する";
    }

    private function buildNormalizationPrompt(string $brandName, string $fragranceName, string $language): string
    {
        return "香水情報を正規化してください。

ブランド名: {$brandName}
香水名: {$fragranceName}
言語: {$language}

以下のJSON形式で回答してください：
{
    \"normalized_brand\": \"正規化されたブランド名\",
    \"normalized_fragrance_name\": \"正規化された香水名\",
    \"concentration_type\": \"EDP/EDT/Parfum/その他\",
    \"launch_year\": \"発売年\",
    \"fragrance_family\": \"香りファミリー\",
    \"confidence_score\": 0.95,
    \"description_ja\": \"日本語での説明\",
    \"description_en\": \"English description\"
}

注意：
- 正式名称に正規化する
- 不明な情報はnullにする
- 信頼度スコアを0.0-1.0で設定";
    }

    private function buildNotesSuggestionPrompt(string $brandName, string $fragranceName, string $language): string
    {
        return "香水の香りノートを推定してください。

ブランド名: {$brandName}
香水名: {$fragranceName}
言語: {$language}

以下のJSON形式で回答してください：
{
    \"notes\": {
        \"top\": [
            {\"name\": \"bergamot\", \"intensity\": \"strong\", \"confidence\": 0.85},
            {\"name\": \"lemon\", \"intensity\": \"moderate\", \"confidence\": 0.72}
        ],
        \"middle\": [...],
        \"base\": [...]
    },
    \"confidence_score\": 0.80
}

注意：
- 強度レベル: light/moderate/strong
- 信頼度は0.0-1.0で設定
- 具体的な香料名で回答";
    }

    private function buildAttributesSuggestionPrompt(string $fragranceName, string $language): string
    {
        return "香水の季節・シーン適性を推定してください。

香水名: {$fragranceName}
言語: {$language}

以下のJSON形式で回答してください：
{
    \"attributes\": {
        \"seasons\": [\"spring\", \"summer\"],
        \"occasions\": [\"casual\", \"business\"],
        \"time_of_day\": [\"morning\", \"daytime\"],
        \"age_groups\": [\"20s\", \"30s\"]
    },
    \"confidence_score\": 0.75
}

注意：
- 適切な属性のみ選択する
- 信頼度は0.0-1.0で設定";
    }
}
