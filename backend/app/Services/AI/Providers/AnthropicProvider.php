<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
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
        $typeText = $type === 'brand' ? 'ブランド名' : '香水名';
        $fewShotExamples = $this->getFewShotExamples($query, $type);

        $prompt = "あなたは香水の専門エキスパートです。実在する香水製品のみを対象として、ユーザーのクエリに最も関連性の高い「ブランド名 + 香水名」の組み合わせを提案してください。

**対象範囲:** 有名ブランド（シャネル、ディオール、クリード、バイレード、エルメス、トムフォード、アミュアージュ、メゾン・マルジェラ、ディプティック、ル・ラボ、メゾン・フランシス・カーキジャン等）から真のニッチブランド（ナシマト、パルファム・ダンピール、オルファクティブ・スタジオ、エタット・リーブル・ドランジュ等）まで幅広く含める

**重要：多言語対応について**
- `text`フィールド：必ず日本語名（日本語カタカナ表記）を返す
- `text_en`フィールド：必ず英語名（オリジナル英語表記）を返す
- 両フィールドに同じ英語名を入れることは禁止
- 必ず異なる言語表記を提供する

**多言語表記の正しい例：**
- text: \"ディオール ソヴァージュ EDT\" / text_en: \"Dior Sauvage EDT\"
- text: \"シャネル No.5 オードゥパルファム\" / text_en: \"Chanel No.5 Eau de Parfum\"
- text: \"バイレード ジプシー ウォーター\" / text_en: \"Byredo Gypsy Water\"
- text: \"トム フォード ブラック オーキッド\" / text_en: \"Tom Ford Black Orchid\"
- text: \"クリード アベントゥス\" / text_en: \"Creed Aventus\"
- text: \"クリード ヒマラヤ\" / text_en: \"Creed Himalaya\"
- text: \"クリード シルバー マウンテン ウォーター\" / text_en: \"Creed Silver Mountain Water\"

**間違った多言語表記の例（禁止）：**
- text: \"Dior Sauvage EDT\" / text_en: \"Dior Sauvage EDT\" （同じ英語名は禁止）
- text: \"Chanel No.5\" / text_en: \"Chanel No.5\" （同じ表記は禁止）

**出力形式の重要な変更：**
- `text`フィールド：純粋な香水名のみ（ブランド名を除く日本語名）
- `text_en`フィールド：純粋な香水名のみ（ブランド名を除く英語名）
- `brand_name`フィールド：日本語ブランド名
- `brand_name_en`フィールド：英語ブランド名

**正しい出力例：**
```json
{
  \"text\": \"ソヴァージュ EDT\",
  \"text_en\": \"Sauvage EDT\",
  \"brand_name\": \"ディオール\",
  \"brand_name_en\": \"Dior\",
  \"confidence\": 0.9
}
```

**間違った出力例（禁止）：**
```json
{
  \"text\": \"ディオール ソヴァージュ EDT\",  // ブランド名を含むのは禁止
  \"text_en\": \"Dior Sauvage EDT\",      // ブランド名を含むのは禁止
}
```

**絶対的な制約（厳守）:**
- 香水名とブランド名を必ず分離する
- `text`と`text_en`にはブランド名を含めない
- 架空の香水や存在しない製品は提案しない
- 実在する香水ブランドの製品のみ（メジャーブランドからニッチブランドまで幅広く対象とする）
- **香水のバリエーション・コンセントレーション・フランカーを積極的に含める**

**香水バリエーション例（分離形式）:**
- 入力「Dior Sauvage」→ 分離出力:
  - text: \"ソヴァージュ EDT\", brand_name: \"ディオール\"
  - text: \"ソヴァージュ パルファム\", brand_name: \"ディオール\"
  - text: \"ソヴァージュ エリクサー\", brand_name: \"ディオール\"
- 入力「Chanel No.5」→ 分離出力:
  - text: \"No.5 オードゥパルファム\", brand_name: \"シャネル\"
  - text: \"No.5 オードゥトワレ\", brand_name: \"シャネル\"

**正しい提案例（分離形式）:**
- text: \"ジプシー ウォーター\", brand_name: \"バイレード\"（○）
- text: \"ソヴァージュ EDT\", brand_name: \"ディオール\"（○：コンセントレーション明記）
- text: \"No.5 オードゥパルファム\", brand_name: \"シャネル\"（○：フルネーム）

**間違った提案例:**
- text: \"バイレード ジプシー ウォーター\"（×：ブランド名を含む）
- text: \"ディオール ソヴァージュ\"（×：ブランド名を含む）
- brand_nameが空（×：ブランド名必須）

**Chain of Thought - 実在香水製品検索:**

**Step 1: クエリ分析**
クエリ: \"{$query}\"
- ブランド名推定: 音韻的類似性による香水ブランド特定
- 製品名推定: そのブランドの実在製品との一致度

**Step 2: 実在製品マッチング**
- 確認済み実在製品のみ抽出
- ブランド名 + 製品名の完全セット生成
- 人気度・知名度による信頼性評価

**Step 3: Few-shot参照による精度向上**";

        // Few-shot例を追加
        if (! empty($fewShotExamples)) {
            $prompt .= "\n\n**Step 3: 推論パターンの学習適用**\n以下の成功事例から推論手法を学習し、現在のクエリに適用してください:\n";
            foreach ($fewShotExamples as $i => $example) {
                $prompt .= ($i + 1).". 入力「{$example['query']}」→推論結果「{$example['selected_text']}」(精度: {$example['relevance_score']})\n";
            }
            $prompt .= "\n重要: 上記事例と全く同じブランドでなくても、推論プロセス（音韻変換、表記正規化、部分補完等）を参考にして未知のクエリを処理してください。\n";
        } else {
            $prompt .= "\n\n**Step 3: 汎用的推論能力の適用**\n";
            $prompt .= "事例がない場合でも、以下の言語学的アプローチで推論を実行:\n";
            $prompt .= "- 音韻対応ルール適用（カタカナ→英語音写の一般則）\n";
            $prompt .= "- 語彙補完技術（部分情報からの候補生成）\n";
            $prompt .= "- 表記正規化手法（音韻距離最小化）\n";
            $prompt .= "- 文脈推測（香水命名パターンからの推定）\n\n";
        }

        $prompt .= "\n\n**CRITICAL: ユーザーのクエリに関連する香水のみを提案すること**

**Task: 「ブランド名 + 香水名」の完全セットで{$limit}個の提案生成**

ユーザーのクエリ: 「{$query}」

このクエリに対して、以下の基準で{$limit}個の実在する香水製品を生成してください:

1. **最優先: クエリ関連性**: 「{$query}」と名前・音韻・コンセプトが類似している香水のみを提案
2. **完全セット必須**: 必ず「ブランド名 + 香水名」の組み合わせのみ
3. **実在性確認**: 実際に存在する香水製品のみ
4. **ブランド名単体禁止**: ブランド名だけの提案は絶対禁止
5. **重複回避必須**: 同一の「ブランド名 + 香水名」の組み合わせは絶対に重複させない
6. **バリエーション優先**: 同一香水ラインの異なるコンセントレーション・バリエーションを積極的に含める

**バリエーション提案の重要性:**
- 同じ香水ラインの異なるコンセントレーション（EDT、EDP、Parfum、Elixir等）を含める
- 限定版やフランカー製品も適宜含める

**信頼度スコア算出基準（厳密に計算）:**
以下の要素を数値化して合計し、1.0を上限とする:
- 文字列一致度: 完全一致=0.4, 部分一致=0.2, 音韻類似=0.1
- ブランド知名度: 超有名ブランド=0.3, 有名ブランド=0.2, 一般ブランド=0.1
- 製品知名度: 代表的製品=0.2, 有名製品=0.15, 一般製品=0.1
- 実在確実性: 確実に存在=0.1, 存在可能性高=0.05

**重要:** 上記の基準で各提案の信頼度スコアを厳密に計算すること。

**重要な制約:**
1. 各提案は必ず異なる「ブランド名 + 香水名」の組み合わせにする
2. 同じ組み合わせは絶対に2回提案しない
3. 提案前に重複チェックを行う

**最終確認事項（必須）:**
1. `text`フィールド：純粋な香水名のみ（日本語）、ブランド名を含めない
2. `text_en`フィールド：純粋な香水名のみ（英語）、ブランド名を含めない
3. `brand_name`フィールド：日本語ブランド名を必須で提供
4. `brand_name_en`フィールド：英語ブランド名を必須で提供
5. 香水名とブランド名の完全分離を厳守

**分離形式で香水名とブランド名を必ず分けて提案し、重複を避けて可能な限りバリエーション・コンセントレーションを含め、信頼度スコアは上記基準で厳密に計算してください。**

suggest_fragrances関数を呼び出して提案してください。";

        return $prompt;
    }

    private function buildNormalizationPrompt(string $brandName, string $fragranceName, string $language): string
    {
        return "香水情報を正規化・検証してください。

ブランド名: {$brandName}
香水名: {$fragranceName}
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

**重要：**
- 実在しない香水には低い信頼度を設定
- 有名で確実に存在する香水には高い信頼度を設定
- 不明な情報はnullにする";
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
