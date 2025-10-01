<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private array $costPerToken;

    private CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
        $this->apiKey = config('services.anthropic.api_key');
        $this->model = config('services.ai.claude_model', 'claude-3-haiku-20240307');

        // コスト設定を外部ファイルから取得し、1M tokens → 1 token に変換
        $costs = config('ai_costs.anthropic', []);
        $this->costPerToken = [];

        foreach ($costs as $model => $rates) {
            $this->costPerToken[$model] = [
                'input' => ($rates['input'] ?? 0) / 1000000,   // $per 1M tokens → $per token
                'output' => ($rates['output'] ?? 0) / 1000000,  // $per 1M tokens → $per token
            ];
        }

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
        $response = $this->makeRequestWithTools($prompt, $this->getCompletionTool($type, $limit));
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseToolResponse($response, 'suggest_fragrances');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'completion', $costEstimate);

        return [
            'suggestions' => $result['suggestions'] ?? [],
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'ai_provider' => 'anthropic',
            'ai_model' => $this->model,
            'cost_estimate' => $costEstimate,
        ];
    }

    public function normalize(string $brandName, string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildNormalizationPrompt($brandName, $fragranceName, $language);

        $startTime = microtime(true);
        $response = $this->makeRequestWithTools($prompt, $this->getNormalizationTool());
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseToolResponse($response, 'normalize_fragrance');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'normalization', $costEstimate);

        return [
            'normalized_data' => $result,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $costEstimate,
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
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'notes_suggestion', $costEstimate);

        return [
            'notes' => $result['notes'] ?? [],
            'confidence_score' => $result['confidence_score'] ?? 0.0,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $costEstimate,
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
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'attributes_suggestion', $costEstimate);

        return [
            'attributes' => $result['attributes'] ?? [],
            'confidence_score' => $result['confidence_score'] ?? 0.0,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $costEstimate,
        ];
    }

    public function calculateCost(array $usage): float
    {
        $model = $usage['model'] ?? $this->model;
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $defaultModel = config('ai_costs.defaults.anthropic', 'claude-3-sonnet-20240229');
        $rates = $this->costPerToken[$model] ?? $this->costPerToken[$defaultModel] ?? ['input' => 0, 'output' => 0];

        return ($inputTokens * $rates['input']) + ($outputTokens * $rates['output']);
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    private function makeRequest(string $prompt, array $options = []): array
    {
        try {
            Log::info('AI Request Started', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'max_tokens' => $options['max_tokens'] ?? 1000,
            ]);

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

            $responseData = $response->json();
            Log::info('AI Request Completed', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'input_tokens' => $responseData['usage']['input_tokens'] ?? 0,
                'output_tokens' => $responseData['usage']['output_tokens'] ?? 0,
                'cost_estimate' => $this->estimateCost($responseData),
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('AI Request Failed', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseJsonResponse(array $response): array
    {
        $content = $response['content'][0]['text'] ?? '';

        // JSONを抽出（複数パターンを試行）
        $jsonString = $this->extractJsonFromContent($content);

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('AI JSON parsing failed, using fallback', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'error' => json_last_error_msg(),
            ]);

            // フォールバック: 基本的な補完結果を返す
            return [];
        }

        return $data;
    }

    private function extractJsonFromContent(string $content): string
    {
        // パターン1: ```json ... ```
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            return trim($matches[1]);
        }

        // パターン2: ``` ... ``` (json記述なし)
        if (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            return trim($matches[1]);
        }

        // パターン3: JSONオブジェクト直接検出
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            return trim($matches[0]);
        }

        // パターン4: []配列直接検出
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            return trim($matches[0]);
        }

        return trim($content);
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

    private function makeRequestWithTools(string $prompt, array $tools): array
    {
        try {
            Log::info('AI Request Started (Anthropic Tools)', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'tools_count' => count($tools),
            ]);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1000,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'tools' => $tools,
                'tool_choice' => ['type' => 'tool', 'name' => $tools[0]['name']],
            ]);

            if (! $response->successful()) {
                throw new \Exception('Anthropic API request failed: '.$response->body());
            }

            $responseData = $response->json();
            Log::info('AI Request Completed (Anthropic Tools)', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'input_tokens' => $responseData['usage']['input_tokens'] ?? 0,
                'output_tokens' => $responseData['usage']['output_tokens'] ?? 0,
                'cost_estimate' => $this->estimateCost($responseData),
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('AI Request Failed (Anthropic Tools)', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseToolResponse(array $response, string $toolName): array
    {
        $content = $response['content'] ?? [];

        // デバッグログを追加
        Log::info('parseToolResponse Debug', [
            'tool_name' => $toolName,
            'content_count' => count($content),
            'content_structure' => array_map(function ($block) {
                return [
                    'type' => $block['type'] ?? 'unknown',
                    'name' => $block['name'] ?? null,
                    'has_input' => isset($block['input']),
                    'input_keys' => isset($block['input']) ? array_keys($block['input']) : [],
                ];
            }, $content),
        ]);

        foreach ($content as $block) {
            if ($block['type'] === 'tool_use' && $block['name'] === $toolName) {
                Log::info('Found tool_use block', [
                    'input' => $block['input'],
                    'suggestions_count' => is_array($block['input']['suggestions'] ?? null) ? count($block['input']['suggestions']) : 0,
                ]);

                return $block['input'];
            }
        }

        // フォールバック: 通常のテキストレスポンスを解析
        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                Log::info('Fallback to text parsing', ['text' => $block['text'] ?? '']);

                return $this->parseJsonResponse($response);
            }
        }

        Log::error('No valid tool response found', ['response' => $response]);
        throw new \Exception('No valid tool response found');
    }

    private function getNormalizationTool(): array
    {
        return [
            [
                'name' => 'normalize_fragrance',
                'description' => '香水情報を正規化・検証する',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'normalized_brand' => [
                            'type' => 'string',
                            'description' => '正規化されたブランド名（基本）',
                        ],
                        'normalized_brand_ja' => [
                            'type' => 'string',
                            'description' => '日本語ブランド名',
                        ],
                        'normalized_brand_en' => [
                            'type' => 'string',
                            'description' => '英語ブランド名',
                        ],
                        'normalized_fragrance_name' => [
                            'type' => 'string',
                            'description' => '正規化された香水名（基本）',
                        ],
                        'normalized_fragrance_ja' => [
                            'type' => 'string',
                            'description' => '日本語香水名',
                        ],
                        'normalized_fragrance_en' => [
                            'type' => 'string',
                            'description' => '英語香水名',
                        ],
                        'concentration_type' => [
                            'type' => 'string',
                            'description' => 'EDP/EDT/Parfum/その他',
                        ],
                        'launch_year' => [
                            'type' => 'string',
                            'description' => '発売年',
                        ],
                        'fragrance_family' => [
                            'type' => 'string',
                            'description' => '香りファミリー',
                        ],
                        'final_confidence_score' => [
                            'type' => 'number',
                            'description' => '信頼度スコア（0.0-1.0）',
                            'minimum' => 0.0,
                            'maximum' => 1.0,
                        ],
                        'description_ja' => [
                            'type' => 'string',
                            'description' => '日本語での説明',
                        ],
                        'description_en' => [
                            'type' => 'string',
                            'description' => 'English description',
                        ],
                        'validation_notes' => [
                            'type' => 'string',
                            'description' => '信頼度の根拠や確認事項',
                        ],
                    ],
                    'required' => [
                        'normalized_brand',
                        'normalized_fragrance_name',
                        'final_confidence_score',
                    ],
                ],
            ],
        ];
    }

    private function getCompletionTool(string $type, int $limit): array
    {
        return [
            [
                'name' => 'suggest_fragrances',
                'description' => '香水またはブランドの提案リストを生成する',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'suggestions' => [
                            'type' => 'array',
                            'description' => '提案リスト',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'text' => [
                                        'type' => 'string',
                                        'description' => '日本語名',
                                    ],
                                    'text_en' => [
                                        'type' => 'string',
                                        'description' => '英語名',
                                    ],
                                    'confidence' => [
                                        'type' => 'number',
                                        'description' => '信頼度（0.0-1.0）',
                                        'minimum' => 0.0,
                                        'maximum' => 1.0,
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'description' => 'タイプ',
                                        'enum' => ['brand', 'fragrance'],
                                    ],
                                    'brand_name' => [
                                        'type' => 'string',
                                        'description' => 'ブランド名（香水の場合）',
                                    ],
                                    'brand_name_en' => [
                                        'type' => 'string',
                                        'description' => '英語ブランド名（香水の場合）',
                                    ],
                                ],
                                'required' => ['text', 'text_en', 'confidence', 'type'],
                            ],
                            'minItems' => $limit,
                            'maxItems' => $limit,
                        ],
                    ],
                    'required' => ['suggestions'],
                ],
            ],
        ];
    }

    /**
     * Few-shot学習用の成功例を取得
     */
    private function getFewShotExamples(string $query, string $type): array
    {
        try {
            // AIFeedbackServiceから実際の学習データを取得
            $aiFeedbackService = app(\App\Services\AI\AIFeedbackService::class);

            return $aiFeedbackService->getFewShotExamples($query, 'completion', 3);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function buildCompletionPrompt(string $query, string $type, int $limit, string $language): string
    {
        $fewShotExamples = $this->getFewShotExamples($query, $type);

        return PromptBuilder::buildCompletionPrompt($query, $type, $limit, $language, $fewShotExamples);
    }

    private function buildNormalizationPrompt(string $brandName, string $fragranceName, string $language): string
    {
        return PromptBuilder::buildNormalizationPrompt($brandName, $fragranceName, $language);
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

    public function normalizeFromInput(string $input, array $options = []): array
    {
        $language = $options['language'] ?? 'mixed';

        $prompt = $this->buildSmartInputNormalizationPrompt($input, $language);

        $startTime = microtime(true);
        $response = $this->makeRequestWithTools($prompt, $this->getNormalizationTool());
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseToolResponse($response, 'normalize_fragrance');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'normalization_from_input', $costEstimate);

        return [
            'normalized_data' => $result,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'anthropic',
            'cost_estimate' => $costEstimate,
        ];
    }

    private function buildSmartInputNormalizationPrompt(string $input, string $language): string
    {
        return "以下の入力から香水情報を抽出・正規化してください。

入力: {$input}
言語: {$language}

**信頼度スコアの基準（厳格に判定してください）：**
- **0.90-1.0**: 100%実在が確認できる世界的な有名香水（Chanel No.5、Dior Sauvage、Tom Ford Black Orchid、Creed Aventus等）
- **0.80-0.89**: 実在が非常に確実な有名ブランドの人気香水
- **0.70-0.79**: 実在するが詳細確認が必要な香水
- **0.60-0.69**: ブランド名または香水名に軽微な修正が必要
- **0.40-0.59**: 大幅な修正が必要または情報が不完全
- **0.20-0.39**: 明らかに間違いがある組み合わせ
- **0.0-0.19**: 存在しないブランドまたは香水

**重要**: validation_notesで説明する信頼度とfinal_confidence_scoreの数値は必ず一致させてください。

normalize_fragrance関数を呼び出して、構造化されたデータを返してください。

重要な要件：
- 入力からブランド名と香水名を自動判別する
- 日本語と英語の両方の名前を提供する
- ブランド名のみ、香水名のみの場合も対応する
- 不明な情報はnullにする
- final_confidence_scoreは厳格な基準で判定する
- 実在する香水・ブランドを優先する

例：
入力「シャネル No.5」→ ブランド：「シャネル/CHANEL」、香水：「No.5/No.5」
入力「Tom Ford Black Orchid」→ ブランド：「トム フォード/Tom Ford」、香水：「ブラック オーキッド/Black Orchid」";
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

    private function recordCost(array $response, string $operation, float $cost): void
    {
        try {
            $usage = $response['usage'] ?? [];

            $this->costTrackingService->recordCost(
                provider: 'anthropic',
                model: $this->model,
                operation: $operation,
                inputTokens: $usage['input_tokens'] ?? 0,
                outputTokens: $usage['output_tokens'] ?? 0,
                cost: $cost
            );
        } catch (\Exception $e) {
            Log::error('Failed to record cost tracking', [
                'provider' => 'anthropic',
                'model' => $this->model,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
