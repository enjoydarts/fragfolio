<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private array $costPerToken;

    private CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.ai.gpt_model', 'gpt-4o-mini');

        // コスト設定を外部ファイルから取得し、1M tokens → 1 token に変換
        $costs = config('ai_costs.openai', []);
        $this->costPerToken = [];

        foreach ($costs as $model => $rates) {
            $this->costPerToken[$model] = [
                'input' => ($rates['input'] ?? 0) / 1000000,   // $per 1M tokens → $per token
                'output' => ($rates['output'] ?? 0) / 1000000,  // $per 1M tokens → $per token
            ];
        }

        if (! $this->apiKey) {
            throw new \Exception('OpenAI API key is not configured');
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

        $result = $this->parseToolCallResponse($response, 'suggest_fragrances');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'completion', $costEstimate);

        return [
            'suggestions' => $result['suggestions'] ?? [],
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'openai',
            'ai_provider' => 'openai',
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

        $result = $this->parseToolCallResponse($response, 'normalize_fragrance');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'normalization', $costEstimate);

        return [
            'normalized_data' => $result,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'openai',
            'cost_estimate' => $costEstimate,
        ];
    }

    public function suggestNotes(string $brandName, string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildNotesSuggestionPrompt($brandName, $fragranceName, $language);

        $startTime = microtime(true);
        $response = $this->makeRequest($prompt, ['max_tokens' => 800, 'temperature' => 0.2]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseJsonResponse($response);
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'notes_suggestion', $costEstimate);

        return [
            'notes' => $result['notes'] ?? [],
            'confidence_score' => $result['confidence_score'] ?? 0.0,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'openai',
            'cost_estimate' => $costEstimate,
        ];
    }

    public function suggestAttributes(string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';

        $prompt = $this->buildAttributesSuggestionPrompt($fragranceName, $language);

        $startTime = microtime(true);
        $response = $this->makeRequest($prompt, ['max_tokens' => 600, 'temperature' => 0.2]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseJsonResponse($response);
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'attributes_suggestion', $costEstimate);

        return [
            'attributes' => $result['attributes'] ?? [],
            'confidence_score' => $result['confidence_score'] ?? 0.0,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'openai',
            'cost_estimate' => $costEstimate,
        ];
    }

    public function calculateCost(array $usage): float
    {
        $model = $usage['model'] ?? $this->model;
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $defaultModel = config('ai_costs.defaults.openai', 'gpt-4');
        $rates = $this->costPerToken[$model] ?? $this->costPerToken[$defaultModel] ?? ['input' => 0, 'output' => 0];

        return ($inputTokens * $rates['input']) + ($outputTokens * $rates['output']);
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    private function makeRequest(string $prompt, array $options = []): array
    {
        try {
            Log::info('AI Request Started', [
                'provider' => 'openai',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'max_tokens' => $options['max_tokens'] ?? 4000,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $options['max_tokens'] ?? 4000, // さらに増量
                'temperature' => $options['temperature'] ?? 0.1,
            ]);

            if (! $response->successful()) {
                throw new \Exception('OpenAI API request failed: '.$response->body());
            }

            $responseData = $response->json();
            Log::info('AI Request Completed', [
                'provider' => 'openai',
                'model' => $this->model,
                'prompt_tokens' => $responseData['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $responseData['usage']['completion_tokens'] ?? 0,
                'cost_estimate' => $this->estimateCost($responseData),
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('AI Request Failed', [
                'provider' => 'openai',
                'model' => $this->model,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseJsonResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        // JSONを抽出（複数パターンを試行）
        $jsonString = $this->extractJsonFromContent($content);

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('AI JSON parsing failed, using fallback', [
                'provider' => 'openai',
                'model' => $this->model,
                'error' => json_last_error_msg(),
            ]);

            // 改良されたフォールバック: テキストから直接提案を抽出
            return $this->extractSuggestionsFromText($content);
        }

        return $data;
    }

    private function getFewShotExamples(string $query, string $type): array
    {
        try {
            $aiFeedbackService = app(\App\Services\AI\AIFeedbackService::class);

            return $aiFeedbackService->getFewShotExamples($query, 'completion', 3);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function extractSuggestionsFromText(string $content): array
    {
        $suggestions = [];

        // 各 "text" フィールドを順次検索
        $texts = [];
        $textEns = [];
        $confidences = [];
        $brandNames = [];

        // text フィールドを抽出
        preg_match_all('/"text":\s*"([^"]+)"/s', $content, $textMatches);
        if (! empty($textMatches[1])) {
            $texts = $textMatches[1];
        }

        // text_en フィールドを抽出
        preg_match_all('/"text_en":\s*"([^"]+)"/s', $content, $textEnMatches);
        if (! empty($textEnMatches[1])) {
            $textEns = $textEnMatches[1];
        }

        // confidence フィールドを抽出
        preg_match_all('/"confidence":\s*([0-9.]+)/s', $content, $confidenceMatches);
        if (! empty($confidenceMatches[1])) {
            $confidences = $confidenceMatches[1];
        }

        // brand_name フィールドを抽出
        preg_match_all('/"brand_name":\s*"([^"]*)"/s', $content, $brandMatches);
        if (! empty($brandMatches[1])) {
            $brandNames = $brandMatches[1];
        }

        // brand_name_en フィールドを抽出
        preg_match_all('/"brand_name_en":\s*"([^"]*)"/s', $content, $brandEnMatches);
        $brandNamesEn = [];
        if (! empty($brandEnMatches[1])) {
            $brandNamesEn = $brandEnMatches[1];
        }

        // 配列の長さを揃える
        $count = min(count($texts), count($textEns), count($confidences));

        for ($i = 0; $i < $count; $i++) {
            $suggestion = [
                'text' => $texts[$i],
                'text_en' => $textEns[$i],
                'confidence' => (float) $confidences[$i],
                'type' => 'fragrance',
                'source' => 'extracted',
            ];

            if (isset($brandNames[$i]) && ! empty($brandNames[$i])) {
                $suggestion['brand_name'] = $brandNames[$i];
            }

            if (isset($brandNamesEn[$i]) && ! empty($brandNamesEn[$i])) {
                $suggestion['brand_name_en'] = $brandNamesEn[$i];
            }

            $suggestions[] = $suggestion;
        }

        return [
            'suggestions' => $suggestions,
        ];
    }

    private function extractJsonFromContent(string $content): string
    {
        // パターン1: ```json ... ```
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $json = trim($matches[1]);

            return $this->repairIncompleteJson($json);
        }

        // パターン2: ``` ... ``` (json記述なし)
        if (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $json = trim($matches[1]);

            return $this->repairIncompleteJson($json);
        }

        // パターン3: JSONオブジェクト直接検出
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = trim($matches[0]);

            return $this->repairIncompleteJson($json);
        }

        // パターン4: []配列直接検出
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $json = trim($matches[0]);

            return $this->repairIncompleteJson($json);
        }

        return $this->repairIncompleteJson(trim($content));
    }

    private function repairIncompleteJson(string $json): string
    {
        // 不完全なJSONを修復を試行
        $json = trim($json);

        // 開始が{なら、最後を}で閉じる
        if (str_starts_with($json, '{') && ! str_ends_with($json, '}')) {
            // 不完全な末尾を検出して修復
            $json = rtrim($json, " \n\r\t,\"");

            // suggestionsが配列として開かれているが閉じられていない場合
            if (preg_match('/\"suggestions\":\s*\[/', $json)) {
                // 不完全な文字列値を修復（例: "text": "トム フォード のような場合）
                if (preg_match('/:\s*\"[^\"]*$/', $json)) {
                    // 文字列が開いているが閉じられていない
                    $json .= '"}]}';
                }
                // 配列内の最後の要素が不完全かチェック
                elseif (preg_match('/\"suggestions\":\s*\[.*{[^}]*$/', $json)) {
                    // 最後のオブジェクトが不完全 - 閉じ括弧を追加
                    $json .= '}]}';
                } elseif (! preg_match('/\"suggestions\":\s*\[.*\]/', $json)) {
                    // 配列が開いているが閉じられていない
                    $json .= ']}';
                } else {
                    // 通常の場合
                    $json .= '}';
                }
            } else {
                $json .= '}';
            }
        }

        // 開始が[なら、最後を]で閉じる
        if (str_starts_with($json, '[') && ! str_ends_with($json, ']')) {
            $json = rtrim($json, " \n\r\t,").']';
        }

        return $json;
    }

    private function estimateCost(array $response): float
    {
        $usage = $response['usage'] ?? [];

        return $this->calculateCost([
            'model' => $this->model,
            'input_tokens' => $usage['prompt_tokens'] ?? 0,
            'output_tokens' => $usage['completion_tokens'] ?? 0,
        ]);
    }

    private function getCompletionTool(string $type, int $limit): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'suggest_fragrances',
                    'description' => '香水またはブランドの提案リストを生成する',
                    'parameters' => [
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
            ],
        ];
    }

    private function buildCompletionPrompt(string $query, string $type, int $limit, string $language): string
    {
        $fewShotExamples = $this->getFewShotExamples($query, $type);

        return PromptBuilder::buildCompletionPrompt($query, $type, $limit, $language, $fewShotExamples);
    }

    private function makeRequestWithTools(string $prompt, array $tools): array
    {
        try {
            Log::info('AI Request Started (Function Calling)', [
                'provider' => 'openai',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'tools_count' => count($tools),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'tools' => $tools,
                'tool_choice' => 'required',
                'temperature' => 0.1,
            ]);

            if (! $response->successful()) {
                throw new \Exception('OpenAI API request failed: '.$response->body());
            }

            $responseData = $response->json();
            Log::info('AI Request Completed (Function Calling)', [
                'provider' => 'openai',
                'model' => $this->model,
                'prompt_tokens' => $responseData['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $responseData['usage']['completion_tokens'] ?? 0,
                'cost_estimate' => $this->estimateCost($responseData),
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('AI Request Failed (Function Calling)', [
                'provider' => 'openai',
                'model' => $this->model,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseToolCallResponse(array $response, string $functionName): array
    {
        $message = $response['choices'][0]['message'] ?? null;
        $toolCalls = $message['tool_calls'] ?? [];

        foreach ($toolCalls as $toolCall) {
            if ($toolCall['function']['name'] === $functionName) {
                $arguments = json_decode($toolCall['function']['arguments'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $arguments;
                }
            }
        }

        // フォールバック: メッセージ内容から直接解析
        if (! empty($message['content'])) {
            return $this->parseJsonResponse($response);
        }

        throw new \Exception('No valid function call found in response');
    }

    private function getNormalizationTool(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'normalize_fragrance',
                    'description' => '香水情報を正規化・検証する',
                    'parameters' => [
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
                                'enum' => ['EDP', 'EDT', 'Parfum', 'その他', null],
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
            ],
        ];
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

        $result = $this->parseToolCallResponse($response, 'normalize_fragrance');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'normalization_from_input', $costEstimate);

        return [
            'normalized_data' => $result,
            'response_time_ms' => round($responseTime, 2),
            'provider' => 'openai',
            'cost_estimate' => $costEstimate,
        ];
    }

    private function buildSmartInputNormalizationPrompt(string $input, string $language): string
    {
        return "以下の入力から香水情報を抽出・正規化してください。

入力: {$input}
言語: {$language}

以下のJSON形式で回答してください：
{
    \"normalized_brand_ja\": \"正規化されたブランド名（日本語）\",
    \"normalized_brand_en\": \"正規化されたブランド名（英語）\",
    \"normalized_fragrance_ja\": \"正規化された香水名（日本語）\",
    \"normalized_fragrance_en\": \"正規化された香水名（英語）\",
    \"concentration_type\": \"EDP/EDT/Parfum/その他\",
    \"launch_year\": \"発売年\",
    \"fragrance_family\": \"香りファミリー\",
    \"confidence_score\": 0.95,
    \"description_ja\": \"日本語での説明\",
    \"description_en\": \"English description\"
}

重要な要件：
- 入力からブランド名と香水名を自動判別する
- 日本語と英語の両方の名前を提供する
- ブランド名のみ、香水名のみの場合も対応する
- 不明な情報はnullにする
- 信頼度スコアを0.0-1.0で設定
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
                provider: 'openai',
                model: $this->model,
                operation: $operation,
                inputTokens: $usage['prompt_tokens'] ?? 0,
                outputTokens: $usage['completion_tokens'] ?? 0,
                cost: $cost
            );
        } catch (\Exception $e) {
            Log::error('Failed to record cost tracking', [
                'provider' => 'openai',
                'model' => $this->model,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
