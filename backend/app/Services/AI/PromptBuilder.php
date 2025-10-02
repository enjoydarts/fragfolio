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
- text: 日本語の香水名（**ブランド名は絶対に含めない**）
- text_en: 英語の香水名（**ブランド名は絶対に含めない**）
- brand_name: 日本語ブランド名
- brand_name_en: 英語ブランド名
- text と text_en は**異なる言語表記**（同一英語の再掲は禁止）

【分離規則（最重要・厳守）】
- 香水名とブランド名は**必ず完全に分離**する
- **禁止例**:
  - ❌ text: "シャネル No.5" → ブランド名が含まれている（誤り）
  - ✅ text: "No.5", brand_name: "シャネル" → 正しく分離（正解）
  - ❌ text: "Chanel Sauvage" → ブランド名が含まれている（誤り）
  - ✅ text_en: "Sauvage", brand_name_en: "Dior" → 正しく分離（正解）
- **許可**: コンセントレーション・フランカー名は香水名に含めてよい
  - ✅ text: "ソヴァージュ EDP"
  - ✅ text: "No.5 パルファム"

【提案要件】
- ユーザークエリ: "{$query}"
- 返す件数: **ちょうど {$limit} 件**
- 各件は { text, text_en, brand_name, brand_name_en, confidence, rationale_brief } を必須とする
- すべて「ブランド名 + 香水名」の**固有組み合わせ**（重複禁止）

【ソート順序（最重要・厳守）】
1. **クエリとの直接一致を最優先**
   - クエリが「トムフォード」→ brand_name または brand_name_en が "トム フォード" / "Tom Ford" のものを**先頭に配置**
   - クエリが「ソヴァージュ」→ text または text_en が "ソヴァージュ" / "Sauvage" を含むものを**先頭に配置**
   - **禁止**: クエリ「トムフォード」で「ディオール」や「シャネル」を上位に出すのは完全に誤り
2. 次に音韻類似・部分一致
3. 最後に同カテゴリー・関連製品

- ライン内バリエーション（EDT/EDP/Parfum/Elixir、主要フランカー）を積極的に混在
- 確信が弱い候補は confidence を低くし、rationale_brief に理由を**一文**で記載

【重要：音韻マッチング精度向上】
- カタカナ/英語の音韻類似性を**厳密に**判断すること
- **文字数・音節数の一致を最優先**
  - 「カルトゥージア」(7文字) vs 「カルティエ」(5文字) → 明確に異なる
  - 「カルトゥージア」(7文字) vs 「Carthusia」(9文字) → 音韻的に一致
- **具体例で混同を防止**
  - 「カルトゥージア」→「Carthusia」（イタリア・カプリ島の香水ブランド）
  - 「カルティエ」→「Cartier」（フランスのジュエラー・香水ブランド）
  - これらは**完全に別のブランド**。発音が似ていても混同は厳禁
- **マッチング優先順位**
  1. 完全文字列一致（カナ→カナ、英→英）
  2. 音節数・文字数が一致する音韻類似
  3. 部分一致
  4. 音韻が近いが文字数が異なる場合は confidence 0.5以下

【信頼度スコア（上限 1.0）】
- 文字列一致度: 完全 0.4 / 部分 0.2 / 音韻類似 0.1
- ブランド知名度: 超有名 0.3 / 有名 0.2 / 一般 0.1
- 製品知名度: 代表 0.2 / 有名 0.15 / 一般 0.1
- 実在確実性: 確実 0.1 / 高い 0.05
- **音韻誤認リスク**: 類似ブランド混同の可能性がある場合は -0.3〜-0.5 減点

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

【対象範囲】
- 実在する香水ブランドとその製品のみ（メジャー・ニッチ・インディペンデント含む）
- 架空名・誤綴り・未確認品は除外
- 曖昧な場合は実在する最も近い製品を推定し、confidence を下げる

【多言語規則（厳守）】
- text: 日本語の香水名（**ブランド名は絶対に含めない**）
- text_en: 英語の香水名（**ブランド名は絶対に含めない**）
- brand_name: 日本語ブランド名
- brand_name_en: 英語ブランド名
- text と text_en は**異なる言語表記**（同一英語の再掲は禁止）

【分離規則（最重要・厳守）】
- 香水名とブランド名は**必ず完全に分離**する
- **禁止例**:
  - ❌ text: "シャネル No.5" → ブランド名が含まれている（誤り）
  - ✅ text: "No.5", brand_name: "シャネル" → 正しく分離（正解）
- **許可**: コンセントレーション・フランカー名は香水名に含めてよい
  - ✅ text: "ソヴァージュ EDP"

【正規化ルール】
1. **ブランド名の正規化**
   - 公式表記への統一（例: "dior" → "ディオール" / "Dior"）
   - カタカナ表記の統一（例: "シャネル" / "CHANEL"）
   - スペース・記号の正規化

2. **香水名の正規化**
   - 公式製品名への統一（例: "サベージ" → "サヴァージュ" / "Sauvage"）
   - コンセントレーション表記の統一（EDT/EDP/Parfum/Elixir等）
   - フランカー名の正確な表記（例: "Eau Fraîche", "Intense", "Noir"）

3. **信頼度スコア（上限 1.0）**
   - 完全一致・公式確認済み: 0.9-1.0
   - 高い確度での推定: 0.7-0.9
   - 部分一致・音韻類似: 0.5-0.7
   - 低い確度・推測含む: 0.3-0.5
   - 不明・未確認: 0.0-0.3

4. **実在確認（厳密に実施）**
   - exists = true: 公式サイト・有名ECサイト・香水データベースで確認できる製品
   - exists = false: 架空のブランド・製品、または確認できない未知のもの
   - 実在しない場合は confidence を 0.3 以下に設定
   - rationale_brief に実在確認の根拠を**必ず記載**

【禁止事項】
- 思考過程や手順の逐語出力（CoT）。必要なら rationale_brief の一文のみ
- ブランド名と香水名の混在
- スキーマからの逸脱、余計な自然文の混入

【出力】
- normalize_fragrance ツール（または同等 function）を**1回**呼び出す
- 必須フィールド: text, text_en, brand_name, brand_name_en, confidence, exists, rationale_brief
- rationale_brief には実在確認の結果を含める（例：「公式サイトで確認済み」「実在するブランドだが製品名は未確認」「架空のブランド」）
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
                    'suggestions' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'required' => [
                                'text', 'text_en',
                                'brand_name', 'brand_name_en',
                                'confidence',
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
                'required' => ['suggestions'],
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
            'description' => 'ブランド名と香水名を正式表記に正規化し、実在確認を行う',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],           // 日本語香水名（ブランド名は含めない）
                    'text_en' => ['type' => 'string'],        // 英語香水名（ブランド名は含めない）
                    'brand_name' => ['type' => 'string'],     // 日本語ブランド名
                    'brand_name_en' => ['type' => 'string'],  // 英語ブランド名
                    'confidence' => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 1.0],
                    'exists' => [
                        'type' => 'boolean',
                        'description' => '実在する香水かどうか（true: 実在する, false: 実在しない・未確認）',
                    ],
                    'rationale_brief' => ['type' => 'string'], // 根拠を一文
                ],
                'required' => ['text', 'text_en', 'brand_name', 'brand_name_en', 'confidence', 'exists'],
                'additionalProperties' => false,
            ],
        ];
    }
}
