<?php

namespace App\Services\AI;

/**
 * AI プロンプト構築サービス（安全・厳格版）
 * - 思考過程の逐語出力を禁止（rationale_brief の一文のみ）
 * - 出力はツール/関数呼び出しの JSON に限定（Schema で強制）
 */
class PromptBuilder
{
    /**
     * 補完（候補サジェスト）用プロンプト
     *
     * 各ベンダの推奨：
     * - OpenAI: Structured Outputs (response_format: json_schema) か Function Calling
     * - Anthropic: tools[].input_schema（JSON Schema）
     * - Gemini: Function Calling parameters（JSON Schema）
     */
    public static function buildCompletionPrompt(
        string $query,
        string $type,
        int $limit,
        string $language,
        array $fewShotExamples = []
    ): string {
        $typeText = $type === 'brand' ? 'ブランド名' : '香水名';

        $prompt = <<<EOT
あなたは香水データの正規化と候補提案に特化したアシスタントです。出力は**ツール/関数呼び出しの引数 JSON のみ**とし、余計な文章は一切含めません（説明文や思考過程の出力禁止）。

【対象範囲】
- 実在する香水ブランドとその製品のみ（メジャー〜ニッチ含む）
- 架空名・誤綴り・未確認品は除外。曖昧な場合は confidence を下げる

【多言語規則（厳守）】
- text: 日本語の香水名（ブランド名は含めない）
- text_en: 英語の香水名（ブランド名は含めない）
- brand_name: 日本語ブランド名
- brand_name_en: 英語ブランド名
- text と text_en は**異なる言語表記**（同一英語の再掲は禁止）

【分離規則（厳守）】
- 香水名とブランド名は必ず分離（text/text_en にブランド名を入れない）
- コンセントレーションやフランカーは香水名側に含めてよい（例: "ソヴァージュ EDP" / "Sauvage EDP"）

【提案要件】
- ユーザークエリ: "{$query}"
- 返す件数: **ちょうど {$limit} 件**
- 各件は { text, text_en, brand_name, brand_name_en, confidence, rationale_brief } を必須とする
- すべて「ブランド名 + 香水名」の**固有組み合わせ**（重複禁止）
- クエリ関連性の高い順。ライン内バリエーション（EDT/EDP/Parfum/Elixir、主要フランカー）を積極的に混在
- 確信が弱い候補は confidence を低くし、rationale_brief に理由を**一文**で記載

【信頼度スコア（上限 1.0）】
- 文字列一致度: 完全 0.4 / 部分 0.2 / 音韻類似 0.1
- ブランド知名度: 超有名 0.3 / 有名 0.2 / 一般 0.1
- 製品知名度: 代表 0.2 / 有名 0.15 / 一般 0.1
- 実在確実性: 確実 0.1 / 高い 0.05

【Few-shot（任意・ある場合のみ）】
EOT;

        if (! empty($fewShotExamples)) {
            $prompt .= "\n以下は推論パターンの参考例（**固有名詞は鵜呑みにしない**）：\n";
            foreach ($fewShotExamples as $i => $ex) {
                $prompt .= sprintf(
                    "- 例%d: 入力「%s」→ 採択「%s」 (関連度: %s)\n",
                    $i + 1,
                    $ex['query'] ?? '',
                    $ex['selected_text'] ?? '',
                    $ex['relevance_score'] ?? ''
                );
            }
        }

        $prompt .= <<<EOT


【禁止事項】
- 思考過程や手順の逐語出力（CoT）。必要なら rationale_brief の一文のみ
- ブランド名だけ、香水名だけの提案
- スキーマからの逸脱、余計な自然文の混入

【出力】
- suggest_fragrances ツール（または同等の function）を**1回**呼び出し、
  ちょうど {$limit} 件の配列を返すこと。
EOT;

        return $prompt;
    }

    /**
     * 正規化（入力のブランド名・香水名を正式表記へ）
     */
    public static function buildNormalizationPrompt(
        string $brandName,
        string $fragranceName,
        string $language
    ): string {
        return <<<EOT
あなたは香水データベースの専門家です。出力は**ツール/関数呼び出しの引数 JSON のみ**。説明文や思考過程は出力しません。

【入力】
- ブランド名: "{$brandName}"
- 香水名   : "{$fragranceName}"
- 言語     : {$language}

【タスク】
1) ブランド名/香水名を正式表記へ正規化（日本語/英語の両方）
2) 実在確認。曖昧な場合は confidence を下げ、rationale_brief に理由を**一文**で記載
3) スキーマ逸脱禁止

【出力】
- normalize_fragrance ツール（または同等 function）を**1回**呼び出す。
EOT;
    }

    /**
     * サジェスト用 共通スキーマ（OpenAI/Anthropic/Gemini で流用）
     */
    public static function suggestionJsonSchema(): array
    {
        return [
            'name' => 'suggest_fragrances',
            'description' => 'クエリに関連する実在香水の候補を返す',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'required' => [
                                'text', 'text_en',
                                'brand_name', 'brand_name_en',
                                'confidence', 'rationale_brief',
                            ],
                            'properties' => [
                                'text' => [
                                    'type' => 'string',
                                    'description' => '日本語の香水名（ブランド名は含めない／例: ソヴァージュ EDP）',
                                ],
                                'text_en' => [
                                    'type' => 'string',
                                    'description' => '英語の香水名（ブランド名は含めない／例: Sauvage EDP）',
                                ],
                                'brand_name' => [
                                    'type' => 'string',
                                    'description' => '日本語ブランド名（例: ディオール）',
                                ],
                                'brand_name_en' => [
                                    'type' => 'string',
                                    'description' => '英語ブランド名（例: Dior）',
                                ],
                                'confidence' => [
                                    'type' => 'number',
                                    'minimum' => 0.0,
                                    'maximum' => 1.0,
                                ],
                                'rationale_brief' => [
                                    'type' => 'string',
                                    'description' => '根拠を一文で（例: クエリと音韻が近くラインの主要フランカー）',
                                ],
                            ],
                        ],
                    ],
                ],
                'required' => ['items'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * 正規化用 共通スキーマ
     */
    public static function normalizationJsonSchema(): array
    {
        return [
            'name' => 'normalize_fragrance',
            'description' => 'ブランド名と香水名を正式表記に正規化する',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],           // 日本語香水名（ブランド名は含めない）
                    'text_en' => ['type' => 'string'],        // 英語香水名（ブランド名は含めない）
                    'brand_name' => ['type' => 'string'],     // 日本語ブランド名
                    'brand_name_en' => ['type' => 'string'],  // 英語ブランド名
                    'confidence' => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 1.0],
                    'rationale_brief' => ['type' => 'string'], // 根拠を一文
                ],
                'required' => ['text', 'text_en', 'brand_name', 'brand_name_en', 'confidence', 'rationale_brief'],
                'additionalProperties' => false,
            ],
        ];
    }
}
