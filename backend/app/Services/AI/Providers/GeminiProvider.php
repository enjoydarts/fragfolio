<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    private string $projectId;

    private string $location;

    private string $model;

    private array $costPerToken;

    private CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
        $this->projectId = config('services.gemini.project_id');
        $this->location = config('services.gemini.location', 'us-central1');
        $this->model = config('services.ai.gemini_model', 'gemini-2.5-flash');

        // コスト設定を外部ファイルから取得し、1M tokens → 1 token に変換
        $costs = config('ai_costs.gemini', []);
        $this->costPerToken = [];

        foreach ($costs as $model => $rates) {
            $this->costPerToken[$model] = [
                'input' => ($rates['input'] ?? 0) / 1000000,   // $per 1M tokens → $per token
                'output' => ($rates['output'] ?? 0) / 1000000,  // $per 1M tokens → $per token
            ];
        }

        if (! $this->projectId) {
            throw new \Exception('Gemini project ID is not configured');
        }
    }

    public function complete(string $query, array $options = []): array
    {
        $type = $options['type'] ?? 'fragrance';
        $limit = $options['limit'] ?? 10;
        $language = $options['language'] ?? 'ja';

        return $this->completion($query, $type, $limit, $language);
    }

    public function completion(string $query, string $type, int $limit = 10, string $language = 'ja'): array
    {
        $prompt = $this->buildCompletionPrompt($query, $type, $limit, $language);
        $tools = $this->getCompletionTool($type, $limit);

        $response = $this->makeRequestWithTools($prompt, $tools);
        $result = $this->parseToolResponse($response, 'suggest_fragrances');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'completion', $costEstimate);

        return [
            'suggestions' => $result['suggestions'] ?? [],
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
            'cost_estimate' => $costEstimate,
        ];
    }

    public function normalize(string $brandName, string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';
        $prompt = $this->buildNormalizationPrompt($brandName, $fragranceName, $language);
        $tools = $this->getNormalizationTool();

        $response = $this->makeRequestWithTools($prompt, $tools);
        $result = $this->parseToolResponse($response, 'normalize_fragrance');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'normalization', $costEstimate);

        return [
            'normalized_brand' => $result['normalized_brand'] ?? $brandName,
            'normalized_brand_ja' => $result['normalized_brand_ja'] ?? $brandName,
            'normalized_brand_en' => $result['normalized_brand_en'] ?? $brandName,
            'normalized_fragrance_name' => $result['normalized_fragrance_name'] ?? $fragranceName,
            'normalized_fragrance_ja' => $result['normalized_fragrance_ja'] ?? $fragranceName,
            'normalized_fragrance_en' => $result['normalized_fragrance_en'] ?? $fragranceName,
            'confidence_score' => $result['confidence_score'] ?? 0.5,
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
            'cost_estimate' => $costEstimate,
        ];
    }

    private function buildCompletionPrompt(string $query, string $type, int $limit, string $language): string
    {
        $typeText = $type === 'brand' ? 'ブランド名' : '香水名';
        $fewShotExamples = $this->getFewShotExamples($query, $type);

        $prompt = "あなたは香水の専門エキスパートです。実在する香水製品のみを対象として、ユーザーのクエリに最も関連性の高い香水を提案してください。

**対象範囲:** 有名ブランド（シャネル、ディオール、クリード、バイレード、エルメス、トムフォード、アミュアージュ、メゾン・マルジェラ、ディプティック、ル・ラボ、メゾン・フランシス・カーキジャン等）から真のニッチブランド（ナシマト、パルファム・ダンピール、オルファクティブ・スタジオ、エタット・リーブル・ドランジュ等）まで幅広く含める

**重要：多言語対応について**
- `text`フィールド：必ず日本語名（日本語カタカナ表記）を返す
- `text_en`フィールド：必ず英語名（オリジナル英語表記）を返す
- 両フィールドに同じ英語名を入れることは禁止
- 必ず異なる言語表記を提供する

**出力形式の重要な変更：**
- `text`フィールド：純粋な香水名のみ（ブランド名を除く日本語名）
- `text_en`フィールド：純粋な香水名のみ（ブランド名を除く英語名）
- `brand_name`フィールド：日本語ブランド名
- `brand_name_en`フィールド：英語ブランド名

**多言語表記の正しい例：**
- text: \"ソヴァージュ EDT\", text_en: \"Sauvage EDT\", brand_name: \"ディオール\", brand_name_en: \"Dior\"
- text: \"No.5 オードゥパルファム\", text_en: \"No.5 Eau de Parfum\", brand_name: \"シャネル\", brand_name_en: \"Chanel\"
- text: \"ジプシー ウォーター\", text_en: \"Gypsy Water\", brand_name: \"バイレード\", brand_name_en: \"Byredo\"
- text: \"ブラック オーキッド\", text_en: \"Black Orchid\", brand_name: \"トム フォード\", brand_name_en: \"Tom Ford\"
- text: \"アベントゥス\", text_en: \"Aventus\", brand_name: \"クリード\", brand_name_en: \"Creed\"

**間違った多言語表記の例（禁止）：**
- text: \"Dior Sauvage EDT\" / text_en: \"Dior Sauvage EDT\" （同じ英語名は禁止）
- text: \"Chanel No.5\" / text_en: \"Chanel No.5\" （同じ表記は禁止）

**絶対的な制約（厳守）:**
- `text`フィールドには純粋な香水名のみ（ブランド名を含めない）
- `brand_name`フィールドには対応するブランド名を別途提供
- 架空の香水や存在しない製品は提案しない
- 実在する香水ブランドの製品のみ（メジャーブランドからニッチブランドまで幅広く対象とする）
- 一般企業名やファッションブランド名単体は除外
- **香水のバリエーション・コンセントレーション・フランカーを積極的に含める**

**香水バリエーション例（重要）:**
- Dior → text: \"ソヴァージュ EDT\", brand_name: \"ディオール\", text: \"ソヴァージュ パルファム\", brand_name: \"ディオール\"
- Chanel → text: \"No.5 オードゥパルファム\", brand_name: \"シャネル\", text: \"No.5 オードゥトワレ\", brand_name: \"シャネル\"

**正しい提案例:**
- text: \"ジプシー ウォーター\", brand_name: \"バイレード\"（○：純粋な香水名とブランド名分離）
- text: \"ソヴァージュ EDT\", brand_name: \"ディオール\"（○：コンセントレーション明記）
- text: \"ブラック オーキッド\", brand_name: \"トム フォード\"（○：純粋な香水名）

**間違った提案例:**
- text: \"ディオール ソヴァージュ\"（×：ブランド名が香水名に含まれている）
- text: \"バイレード\"（×：ブランド名のみ）
- text: \"Apple Fragrance\"（×：香水ブランドではない）

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

        // Few-shot examples
        if (! empty($fewShotExamples)) {
            $prompt .= "\n\n**Few-shot成功例からの学習:**\n";
            foreach ($fewShotExamples as $example) {
                // 古い形式（ブランド名+香水名）を新形式に変換
                $selectedText = $example['selected_text'];
                $brandInfo = $this->extractBrandFromCombinedText($selectedText);

                $prompt .= "- クエリ「{$example['query']}」→ text: \"{$brandInfo['fragrance_name']}\", brand_name: \"{$brandInfo['brand_name']}\" (関連度: {$example['relevance_score']})\n";
            }
        }

        $prompt .= "\n\n**Task: 香水名とブランド名を分離した{$limit}個の提案生成**
クエリ「{$query}」に対して、以下の基準で{$limit}個の実在する香水製品を生成してください:

1. **分離必須**: `text`には純粋な香水名のみ、`brand_name`には対応するブランド名
2. **実在性確認**: 実際に存在する香水製品のみ
3. **ブランド名混入禁止**: `text`フィールドにブランド名を含めることは絶対禁止
4. **重複回避必須**: 同一の香水名は絶対に重複させない
5. **バリエーション優先**: 同一香水ラインの異なるコンセントレーション・バリエーションを積極的に含める
6. **音韻的類似性**: クエリとの音韻・文字列的近さ
7. **製品の知名度**: 実際に流通している有名な香水製品

**バリエーション提案の重要性:**
- クエリが「ソヴァージュ」「Sauvage」の場合、以下のような具体的バリエーションを提案:
  - text: \"ソヴァージュ EDT\", brand_name: \"ディオール\"
  - text: \"ソヴァージュ パルファム\", brand_name: \"ディオール\"
  - text: \"ソヴァージュ エリクサー\", brand_name: \"ディオール\"
- クエリが「No.5」の場合:
  - text: \"No.5 オードゥパルファム\", brand_name: \"シャネル\"
  - text: \"No.5 オードゥトワレ\", brand_name: \"シャネル\"
  - text: \"No.5 ロー\", brand_name: \"シャネル\"

**信頼度スコア算出基準（厳密に計算）:**
以下の要素を数値化して合計し、1.0を上限とする:
- 文字列一致度: 完全一致=0.4, 部分一致=0.2, 音韻類似=0.1
- ブランド知名度: 超有名ブランド=0.3, 有名ブランド=0.2, ニッチブランド=0.15, マイナーブランド=0.1
- 製品知名度: 代表的製品=0.2, 有名製品=0.15, 一般製品=0.1
- 実在確実性: 確実に存在=0.1, 存在可能性高=0.05

**スコア例:**
- \\\"バイレード\\\" → text: \\\"ジプシー ウォーター\\\", brand_name: \\\"バイレード\\\": 文字列一致0.2 + ブランド知名度0.2 + 製品知名度0.15 + 実在確実性0.1 = 0.65
- \\\"シャネル5\\\" → text: \\\"No.5 オードゥパルファム\\\", brand_name: \\\"シャネル\\\": 文字列一致0.3 + ブランド知名度0.3 + 製品知名度0.2 + 実在確実性0.1 = 0.9

**提案形式の例:**
- 正しい: text: \\\"ジプシー ウォーター\\\", brand_name: \\\"バイレード\\\"（信頼度: 0.65）
- 正しい: text: \\\"No.5 オードゥパルファム\\\", brand_name: \\\"シャネル\\\"（信頼度: 0.9、バリエーション明記）
- 正しい: text: \\\"ソヴァージュ EDT\\\", brand_name: \\\"ディオール\\\"（信頼度: 0.85、コンセントレーション明記）
- 間違い: text: \\\"バイレード\\\"（ブランド名のみは禁止）
- 間違い: text: \\\"ディオール ソヴァージュ\\\"（ブランド名混入は禁止）

**重要な制約:**
1. 各提案は必ず異なる香水名にする（`text`フィールドベース）
2. 同じ香水名は絶対に2回提案しない
3. 提案前に重複チェックを行う

**最終確認事項（必須）:**
1. `text`フィールド：純粋な香水名のみ（日本語カタカナ表記）
2. `text_en`フィールド：純粋な香水名のみ（英語オリジナル表記）
3. `brand_name`フィールド：日本語ブランド名
4. `brand_name_en`フィールド：英語ブランド名
5. `text`フィールドにブランド名を含めることは絶対禁止

**例外なく香水名とブランド名を分離して提案し、重複を避けて可能な限りバリエーション・コンセントレーションを含め、信頼度スコアは上記基準で厳密に計算し、多言語表記を正しく実装してください。**

suggest_fragrances関数を呼び出して提案してください。";

        return $prompt;
    }

    private function buildNormalizationPrompt(string $brandName, string $fragranceName, string $language): string
    {
        return "香水情報の正規化を行ってください。

ブランド名: {$brandName}
香水名: {$fragranceName}
言語: {$language}

以下の形式で正規化してください:
- 表記ゆれの統一
- 日本語・英語表記の両方を提供
- 信頼度スコアを算出

normalize_fragrance関数を呼び出して正規化してください。";
    }

    private function makeRequestWithTools(string $prompt, array $tools): array
    {
        try {
            Log::info('AI Request Started (Vertex AI Gemini)', [
                'provider' => 'gemini',
                'model' => $this->model,
                'project_id' => $this->projectId,
                'location' => $this->location,
                'prompt_length' => strlen($prompt),
                'tools_count' => count($tools),
            ]);

            // サービスアカウント認証でアクセストークンを取得
            $accessToken = $this->getAccessToken();

            $requestData = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'tools' => [
                    [
                        'function_declarations' => $tools,
                    ],
                ],
                'tool_config' => [
                    'function_calling_config' => [
                        'mode' => 'ANY',
                        'allowed_function_names' => [
                            $tools[0]['name'],
                        ],
                    ],
                ],
            ];

            $url = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/{$this->model}:generateContent";

            $response = $this->makeRequestWithRetry($url, $requestData, $accessToken);

            if (! $response->successful()) {
                throw new \Exception('Vertex AI Gemini API request failed: '.$response->body());
            }

            $responseData = $response->json();

            Log::info('AI Request Completed (Vertex AI Gemini)', [
                'provider' => 'gemini',
                'model' => $this->model,
                'input_tokens' => $responseData['usageMetadata']['promptTokenCount'] ?? 0,
                'output_tokens' => $responseData['usageMetadata']['candidatesTokenCount'] ?? 0,
                'cost_estimate' => $this->estimateCost($responseData),
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('AI Request Failed (Vertex AI Gemini)', [
                'provider' => 'gemini',
                'model' => $this->model,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseToolResponse(array $response, string $toolName): array
    {
        $candidates = $response['candidates'] ?? [];

        // デバッグログを追加
        Log::info('parseToolResponse Debug (Gemini)', [
            'tool_name' => $toolName,
            'candidates_count' => count($candidates),
            'response_structure' => array_map(function ($candidate) {
                $content = $candidate['content'] ?? [];
                $parts = $content['parts'] ?? [];

                return [
                    'parts_count' => count($parts),
                    'parts_structure' => array_map(function ($part) {
                        return [
                            'has_function_call' => isset($part['functionCall']),
                            'function_name' => $part['functionCall']['name'] ?? null,
                            'has_args' => isset($part['functionCall']['args']),
                        ];
                    }, $parts),
                ];
            }, $candidates),
        ]);

        foreach ($candidates as $candidate) {
            $content = $candidate['content'] ?? [];
            $parts = $content['parts'] ?? [];

            foreach ($parts as $part) {
                if (isset($part['functionCall']) && $part['functionCall']['name'] === $toolName) {
                    Log::info('Found function call (Gemini)', [
                        'args' => $part['functionCall']['args'],
                        'suggestions_count' => count($part['functionCall']['args']['suggestions'] ?? []),
                    ]);

                    return $part['functionCall']['args'];
                }
            }
        }

        Log::error('No valid function call found (Gemini)', ['response' => $response]);
        throw new \Exception('No valid function call found');
    }

    private function getCompletionTool(string $type, int $limit): array
    {
        return [
            [
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
                        ],
                    ],
                    'required' => ['suggestions'],
                ],
            ],
        ];
    }

    private function getNormalizationTool(): array
    {
        return [
            [
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
                        'confidence_score' => [
                            'type' => 'number',
                            'description' => '正規化の信頼度（0.0-1.0）',
                            'minimum' => 0.0,
                            'maximum' => 1.0,
                        ],
                    ],
                    'required' => [
                        'normalized_brand',
                        'normalized_brand_ja',
                        'normalized_brand_en',
                        'normalized_fragrance_name',
                        'normalized_fragrance_ja',
                        'normalized_fragrance_en',
                        'confidence_score',
                    ],
                ],
            ],
        ];
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

    /**
     * 結合テキストからブランド名と香水名を分離
     */
    private function extractBrandFromCombinedText(string $combinedText): array
    {
        // 主要ブランド名のパターン（日本語・英語両方）
        $brandPatterns = [
            'シャネル|Chanel' => ['ja' => 'シャネル', 'en' => 'Chanel'],
            'ディオール|Dior' => ['ja' => 'ディオール', 'en' => 'Dior'],
            'バイレード|Byredo' => ['ja' => 'バイレード', 'en' => 'Byredo'],
            'トム\s*フォード|Tom\s*Ford' => ['ja' => 'トム フォード', 'en' => 'Tom Ford'],
            'クリード|Creed' => ['ja' => 'クリード', 'en' => 'Creed'],
            'エルメス|Hermes|Hermès' => ['ja' => 'エルメス', 'en' => 'Hermès'],
            'ゲラン|Guerlain' => ['ja' => 'ゲラン', 'en' => 'Guerlain'],
            'ジバンシー|Givenchy' => ['ja' => 'ジバンシー', 'en' => 'Givenchy'],
            'イヴ\s*サンローラン|YSL|Yves\s*Saint\s*Laurent' => ['ja' => 'イヴ サンローラン', 'en' => 'Yves Saint Laurent'],
        ];

        foreach ($brandPatterns as $pattern => $brandNames) {
            if (preg_match('/^('.$pattern.')\s+(.+)$/iu', trim($combinedText), $matches)) {
                return [
                    'brand_name' => $brandNames['ja'],
                    'brand_name_en' => $brandNames['en'],
                    'fragrance_name' => trim($matches[2]),
                ];
            }
        }

        // パターンマッチしない場合はそのまま返す
        return [
            'brand_name' => '不明',
            'brand_name_en' => 'Unknown',
            'fragrance_name' => $combinedText,
        ];
    }

    private function estimateCost(array $response): float
    {
        $usage = $response['usageMetadata'] ?? [];

        return $this->calculateCost([
            'model' => $this->model,
            'input_tokens' => $usage['promptTokenCount'] ?? 0,
            'output_tokens' => $usage['candidatesTokenCount'] ?? 0,
        ]);
    }

    public function calculateCost(array $usage): float
    {
        $model = $usage['model'] ?? $this->model;
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $defaultModel = config('ai_costs.defaults.gemini', 'gemini-2.5-flash');
        $rates = $this->costPerToken[$model] ?? $this->costPerToken[$defaultModel] ?? ['input' => 0, 'output' => 0];

        return ($inputTokens * $rates['input']) + ($outputTokens * $rates['output']);
    }

    public function normalizeFromInput(string $input, array $options = []): array
    {
        // 統一入力を解析してブランド名と香水名に分離
        $parts = explode(' ', $input, 2);
        $brandName = $parts[0] ?? '';
        $fragranceName = $parts[1] ?? $input;

        return $this->normalize($brandName, $fragranceName, $options);
    }

    public function suggestNotes(string $brandName, string $fragranceName, array $options = []): array
    {
        // ノート推定機能（今後実装）
        return [
            'top_notes' => [],
            'middle_notes' => [],
            'base_notes' => [],
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
        ];
    }

    public function suggestAttributes(string $fragranceName, array $options = []): array
    {
        // 季節・シーン適性推定機能（今後実装）
        return [
            'seasons' => [],
            'scenes' => [],
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
        ];
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    private function getAccessToken(): string
    {
        $serviceAccountPath = config('services.gemini.service_account_path');

        if ($serviceAccountPath) {
            // 相対パスの場合は絶対パスに変換
            if (! str_starts_with($serviceAccountPath, '/')) {
                $serviceAccountPath = base_path($serviceAccountPath);
            }

            if (file_exists($serviceAccountPath)) {
                // サービスアカウントキーファイルを使用
                putenv('GOOGLE_APPLICATION_CREDENTIALS='.$serviceAccountPath);
                Log::info('Using service account credentials', ['path' => $serviceAccountPath]);
            } else {
                Log::error('Service account key file not found', ['path' => $serviceAccountPath]);
                throw new \Exception('Service account key file not found: '.$serviceAccountPath);
            }
        }

        try {
            $credentials = ApplicationDefaultCredentials::getCredentials([
                'https://www.googleapis.com/auth/cloud-platform',
            ]);

            $token = $credentials->fetchAuthToken();

            if (! isset($token['access_token'])) {
                throw new \Exception('Failed to get access token from credentials');
            }

            Log::info('Successfully obtained access token');

            return $token['access_token'];
        } catch (\Exception $e) {
            Log::error('Failed to get access token', [
                'error' => $e->getMessage(),
                'service_account_path' => $serviceAccountPath ?? 'not set',
            ]);
            throw new \Exception('Failed to authenticate with Google Cloud: '.$e->getMessage());
        }
    }

    private function recordCost(array $response, string $operation, float $cost): void
    {
        try {
            $usage = $response['usageMetadata'] ?? [];

            $this->costTrackingService->recordCost(
                provider: 'gemini',
                model: $this->model,
                operation: $operation,
                inputTokens: $usage['promptTokenCount'] ?? 0,
                outputTokens: $usage['candidatesTokenCount'] ?? 0,
                cost: $cost
            );
        } catch (\Exception $e) {
            Log::error('Failed to record cost tracking', [
                'provider' => 'gemini',
                'model' => $this->model,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function makeRequestWithRetry(string $url, array $requestData, string $accessToken, int $maxRetries = 3): \Illuminate\Http\Client\Response
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(30)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ])->post($url, $requestData);

                // 429エラー（レート制限）の場合はリトライ
                if ($response->status() === 429) {
                    $attempt++;
                    $waitTime = min(2 ** $attempt, 8); // 指数バックオフ（最大8秒）

                    Log::warning('Rate limit hit, retrying...', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'wait_time' => $waitTime,
                        'response' => $response->body(),
                    ]);

                    if ($attempt < $maxRetries) {
                        sleep($waitTime);

                        continue;
                    }
                }

                return $response;

            } catch (\Exception $e) {
                $attempt++;

                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                $waitTime = min(2 ** $attempt, 8);
                Log::warning('Request failed, retrying...', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'wait_time' => $waitTime,
                    'error' => $e->getMessage(),
                ]);

                sleep($waitTime);
            }
        }

        throw new \Exception('Max retries exceeded');
    }
}
