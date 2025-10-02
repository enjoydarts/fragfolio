<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizationService
{
    private AIProviderFactory $providerFactory;

    private CostTrackingService $costTracker;

    public function __construct(
        AIProviderFactory $providerFactory,
        CostTrackingService $costTracker
    ) {
        $this->providerFactory = $providerFactory;
        $this->costTracker = $costTracker;
    }

    /**
     * 包括的正規化処理
     *
     * @param  string  $brandName  ブランド名
     * @param  string  $fragranceName  香水名
     * @param  array  $options  オプション設定
     * @return array 正規化結果
     */
    public function normalize(string $brandName, string $fragranceName, array $options = []): array
    {
        $provider = $options['provider'] ?? null;
        $language = $options['language'] ?? 'ja';
        $userId = $options['user_id'] ?? null;

        // 入力検証
        if (empty(trim($brandName)) || empty(trim($fragranceName))) {
            throw new \InvalidArgumentException('Brand name and fragrance name are required');
        }

        // キャッシュキー生成
        $cacheKey = $this->generateCacheKey('normalization', $brandName, $fragranceName, $provider, $language);

        try {
            // キャッシュから結果を取得（30分間キャッシュ）
            $result = Cache::remember($cacheKey, 1800, function () use ($brandName, $fragranceName, $provider, $language) {
                try {
                    $aiProvider = $this->providerFactory->create($provider);

                    return $aiProvider->normalize($brandName, $fragranceName, [
                        'language' => $language,
                    ]);
                } catch (\Exception $e) {
                    // AI APIが失敗した場合のフォールバック処理
                    Log::warning('AI normalization failed, using fallback', [
                        'brand_name' => $brandName,
                        'fragrance_name' => $fragranceName,
                        'error' => $e->getMessage(),
                    ]);

                    return $this->generateFallbackNormalization($brandName, $fragranceName, $language);
                }
            });

            // マスタデータとの照合
            $result = $this->matchWithMasterData($result);

            // 正規化ルールの適用
            $result = $this->applyNormalizationRules($result);

            // HTMLエンティティのデコード（&amp; → & など）
            $result = $this->decodeHtmlEntities($result);

            // 信頼度スコアの計算
            $result = $this->calculateConfidenceScore($result, $brandName, $fragranceName);

            // コスト追跡
            if ($userId && isset($result['cost_estimate'])) {
                $this->costTracker->trackUsage($userId, [
                    'provider' => $result['provider'],
                    'operation_type' => 'normalization',
                    'cost' => $result['cost_estimate'],
                    'response_time_ms' => $result['response_time_ms'],
                    'metadata' => [
                        'brand_name' => $brandName,
                        'fragrance_name' => $fragranceName,
                    ],
                ]);
            }

            // メタデータの追加
            $result['metadata'] = [
                'original_brand' => $brandName,
                'original_fragrance' => $fragranceName,
                'language' => $language,
                'provider' => $result['provider'] ?? 'unknown',
                'timestamp' => now()->toISOString(),
                'cached' => Cache::has($cacheKey),
            ];

            return $result;

        } catch (\Exception $e) {
            Log::error('Normalization service failed', [
                'brand_name' => $brandName,
                'fragrance_name' => $fragranceName,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 一括正規化処理
     *
     * @param  array  $fragrances  香水データ配列
     * @param  array  $options  オプション設定
     * @return array 結果配列
     */
    public function batchNormalize(array $fragrances, array $options = []): array
    {
        $results = [];
        $totalCost = 0.0;

        foreach ($fragrances as $fragrance) {
            if (! isset($fragrance['brand_name']) || ! isset($fragrance['fragrance_name'])) {
                continue;
            }

            try {
                $result = $this->normalize($fragrance['brand_name'], $fragrance['fragrance_name'], $options);
                $results[] = $result;
                $totalCost += $result['cost_estimate'] ?? 0;
            } catch (\Exception $e) {
                $results[] = [
                    'error' => $e->getMessage(),
                    'original_brand' => $fragrance['brand_name'],
                    'original_fragrance' => $fragrance['fragrance_name'],
                ];
            }
        }

        return [
            'results' => $results,
            'total_processed' => count($fragrances),
            'successful_count' => count(array_filter($results, fn ($r) => ! isset($r['error']))),
            'total_cost_estimate' => $totalCost,
        ];
    }

    /**
     * マスタデータとの照合
     *
     * @param  array  $normalizedData  AI正規化結果
     * @return array 照合済み結果
     */
    private function matchWithMasterData(array $normalizedData): array
    {
        if (! isset($normalizedData['normalized_data'])) {
            return $normalizedData;
        }

        $data = $normalizedData['normalized_data'];

        // ブランド名の照合
        if (isset($data['normalized_brand'])) {
            $matchedBrand = $this->findMatchingBrand($data['normalized_brand']);
            if ($matchedBrand) {
                $data['matched_brand'] = $matchedBrand;
                $data['brand_match_confidence'] = $this->calculateBrandMatchConfidence($data['normalized_brand'], $matchedBrand);
            }
        }

        // 香水名の照合（ブランドが特定できた場合のみ）
        if (isset($data['matched_brand']) && isset($data['normalized_fragrance_name'])) {
            $matchedFragrance = $this->findMatchingFragrance(
                $data['matched_brand']['id'],
                $data['normalized_fragrance_name']
            );
            if ($matchedFragrance) {
                $data['matched_fragrance'] = $matchedFragrance;
                $data['fragrance_match_confidence'] = $this->calculateFragranceMatchConfidence(
                    $data['normalized_fragrance_name'],
                    $matchedFragrance
                );
            }
        }

        $normalizedData['normalized_data'] = $data;

        return $normalizedData;
    }

    /**
     * ブランド名マスタ検索
     */
    private function findMatchingBrand(string $brandName): ?array
    {
        // 完全一致
        $exactMatch = DB::table('brands')
            ->where('name_ja', $brandName)
            ->orWhere('name_en', $brandName)
            ->where('is_active', true)
            ->first();

        if ($exactMatch) {
            return (array) $exactMatch;
        }

        // 部分一致（類似度順）
        $partialMatches = DB::table('brands')
            ->where(function ($query) use ($brandName) {
                $query->where('name_ja', 'like', "%{$brandName}%")
                    ->orWhere('name_en', 'like', "%{$brandName}%");
            })
            ->where('is_active', true)
            ->get();

        if ($partialMatches->isNotEmpty()) {
            // 類似度計算して最適なマッチを返す
            $bestMatch = null;
            $bestSimilarity = 0;

            foreach ($partialMatches as $match) {
                $similarityJa = $this->calculateSimilarity($brandName, $match->name_ja);
                $similarityEn = $this->calculateSimilarity($brandName, $match->name_en);
                $maxSimilarity = max($similarityJa, $similarityEn);

                if ($maxSimilarity > $bestSimilarity) {
                    $bestSimilarity = $maxSimilarity;
                    $bestMatch = (array) $match;
                }
            }

            return $bestSimilarity > 0.6 ? $bestMatch : null;
        }

        return null;
    }

    /**
     * 香水名マスタ検索
     */
    private function findMatchingFragrance(int $brandId, string $fragranceName): ?array
    {
        // 完全一致
        $exactMatch = DB::table('fragrances')
            ->where('brand_id', $brandId)
            ->where(function ($query) use ($fragranceName) {
                $query->where('name_ja', $fragranceName)
                    ->orWhere('name_en', $fragranceName);
            })
            ->where('is_active', true)
            ->first();

        if ($exactMatch) {
            return (array) $exactMatch;
        }

        // 部分一致
        $partialMatches = DB::table('fragrances')
            ->where('brand_id', $brandId)
            ->where(function ($query) use ($fragranceName) {
                $query->where('name_ja', 'like', "%{$fragranceName}%")
                    ->orWhere('name_en', 'like', "%{$fragranceName}%");
            })
            ->where('is_active', true)
            ->get();

        if ($partialMatches->isNotEmpty()) {
            $bestMatch = null;
            $bestSimilarity = 0;

            foreach ($partialMatches as $match) {
                $similarityJa = $this->calculateSimilarity($fragranceName, $match->name_ja);
                $similarityEn = $this->calculateSimilarity($fragranceName, $match->name_en);
                $maxSimilarity = max($similarityJa, $similarityEn);

                if ($maxSimilarity > $bestSimilarity) {
                    $bestSimilarity = $maxSimilarity;
                    $bestMatch = (array) $match;
                }
            }

            return $bestSimilarity > 0.7 ? $bestMatch : null;
        }

        return null;
    }

    /**
     * HTMLエンティティのデコード（&amp; → & など）
     */
    private function decodeHtmlEntities(array $result): array
    {
        if (! isset($result['normalized_data'])) {
            return $result;
        }

        foreach ($result['normalized_data'] as $key => $value) {
            if (is_string($value)) {
                $result['normalized_data'][$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $result;
    }

    /**
     * 正規化ルールの適用
     */
    private function applyNormalizationRules(array $result): array
    {
        if (! isset($result['normalized_data'])) {
            return $result;
        }

        $data = &$result['normalized_data'];

        // ブランド名正規化ルール
        if (isset($data['normalized_brand'])) {
            $data['normalized_brand'] = $this->applyBrandNormalizationRules($data['normalized_brand']);
        }

        // 香水名正規化ルール
        if (isset($data['normalized_fragrance_name'])) {
            $data['normalized_fragrance_name'] = $this->applyFragranceNormalizationRules($data['normalized_fragrance_name']);
        }

        // 濃度タイプ正規化
        if (isset($data['concentration_type'])) {
            $data['concentration_type'] = $this->normalizeConcentrationType($data['concentration_type']);
        }

        return $result;
    }

    /**
     * ブランド名正規化ルール
     */
    private function applyBrandNormalizationRules(string $brandName): string
    {
        $rules = [
            // 略語の正式名称化
            'ディオール' => 'Dior',
            'シャネル' => 'CHANEL',
            'グッチ' => 'Gucci',
            'エルメス' => 'Hermès',
            'YSL' => 'Yves Saint Laurent',
            'TF' => 'Tom Ford',

            // 大文字小文字統一
            'chanel' => 'CHANEL',
            'dior' => 'Dior',
            'gucci' => 'Gucci',
        ];

        return $rules[trim($brandName)] ?? trim($brandName);
    }

    /**
     * 香水名正規化ルール
     */
    private function applyFragranceNormalizationRules(string $fragranceName): string
    {
        $fragranceName = trim($fragranceName);

        // 商標記号の統一
        $fragranceName = str_replace(['(tm)', '(TM)'], '™', $fragranceName);
        $fragranceName = str_replace(['(r)', '(R)'], '®', $fragranceName);

        // 数字の統一
        $fragranceName = preg_replace('/No\.?\s*(\d+)/', 'No.$1', $fragranceName);

        return $fragranceName;
    }

    /**
     * 濃度タイプ正規化
     */
    private function normalizeConcentrationType(string $concentrationType): string
    {
        $normalizedTypes = [
            'edp' => 'EDP',
            'edt' => 'EDT',
            'parfum' => 'Parfum',
            'extrait' => 'Extrait',
            'cologne' => 'EDC',
            'eau de parfum' => 'EDP',
            'eau de toilette' => 'EDT',
            'eau de cologne' => 'EDC',
        ];

        $lower = strtolower(trim($concentrationType));

        return $normalizedTypes[$lower] ?? $concentrationType;
    }

    /**
     * 信頼度スコア計算
     */
    private function calculateConfidenceScore(array $result, string $originalBrand, string $originalFragrance): array
    {
        if (! isset($result['normalized_data'])) {
            return $result;
        }

        $data = &$result['normalized_data'];
        $totalConfidence = $data['confidence_score'] ?? 0.8;

        // マスタマッチの信頼度を加味
        if (isset($data['brand_match_confidence'])) {
            $totalConfidence = ($totalConfidence + $data['brand_match_confidence']) / 2;
        }

        if (isset($data['fragrance_match_confidence'])) {
            $totalConfidence = ($totalConfidence + $data['fragrance_match_confidence']) / 2;
        }

        // AIが判断した信頼度をそのまま使用（独自計算は行わない）
        if (! isset($data['final_confidence_score']) || $data['final_confidence_score'] === 0) {
            // AIが信頼度を返していない場合のみ、デフォルト値を設定
            $data['final_confidence_score'] = 0.75; // 中程度の信頼度
        }

        return $result;
    }

    /**
     * 文字列類似度計算
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower(trim($str1));
        $str2 = mb_strtolower(trim($str2));

        if (empty($str1) || empty($str2)) {
            return 0.0;
        }

        if ($str1 === $str2) {
            return 1.0;
        }

        $len1 = mb_strlen($str1);
        $len2 = mb_strlen($str2);
        $maxLength = max($len1, $len2);

        if ($maxLength === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);

        return 1.0 - ($distance / $maxLength);
    }

    /**
     * ブランドマッチ信頼度計算
     */
    private function calculateBrandMatchConfidence(string $normalized, array $matched): float
    {
        $similarityJa = $this->calculateSimilarity($normalized, $matched['name_ja']);
        $similarityEn = $this->calculateSimilarity($normalized, $matched['name_en']);

        return max($similarityJa, $similarityEn);
    }

    /**
     * 香水マッチ信頼度計算
     */
    private function calculateFragranceMatchConfidence(string $normalized, array $matched): float
    {
        $similarityJa = $this->calculateSimilarity($normalized, $matched['name_ja']);
        $similarityEn = $this->calculateSimilarity($normalized, $matched['name_en']);

        return max($similarityJa, $similarityEn);
    }

    /**
     * フォールバック正規化処理
     */
    private function generateFallbackNormalization(string $brandName, string $fragranceName, string $language): array
    {
        // 基本的な正規化ルールを適用
        $normalizedBrand = $this->applyBrandNormalizationRules(trim($brandName));
        $normalizedFragrance = $this->applyFragranceNormalizationRules(trim($fragranceName));

        return [
            'normalized_data' => [
                'normalized_brand' => $normalizedBrand,
                'normalized_fragrance_name' => $normalizedFragrance,
                'confidence_score' => 0.6, // フォールバックは低い信頼度
                'fallback_reason' => 'AI provider unavailable',
            ],
            'response_time_ms' => 10,
            'provider' => 'fallback',
            'cost_estimate' => 0.0,
        ];
    }

    /**
     * 統一入力からの正規化処理
     *
     * @param  string  $input  統一入力テキスト（ブランド名、香水名、またはその両方）
     * @param  array  $options  オプション設定
     * @return array 正規化結果
     */
    public function normalizeFromInput(string $input, array $options = []): array
    {
        $provider = $options['provider'] ?? null;
        $language = $options['language'] ?? 'mixed';
        $userId = $options['user_id'] ?? null;

        // 入力検証
        if (empty(trim($input))) {
            throw new \InvalidArgumentException('Input text is required');
        }

        // キャッシュキー生成（統一入力用）
        $cacheKey = $this->generateSmartInputCacheKey('smart_normalization', $input, $provider, $language);

        try {
            // キャッシュから結果を取得（30分間キャッシュ）
            $result = Cache::remember($cacheKey, 1800, function () use ($input, $provider, $language) {
                try {
                    $aiProvider = $this->providerFactory->create($provider);

                    return $aiProvider->normalizeFromInput($input, [
                        'language' => $language,
                    ]);
                } catch (\Exception $e) {
                    // AI APIが失敗した場合のフォールバック処理
                    Log::warning('AI smart normalization failed, using fallback', [
                        'input' => $input,
                        'error' => $e->getMessage(),
                    ]);

                    return $this->generateFallbackSmartNormalization($input, $language);
                }
            });

            // マスタデータとの照合
            $result = $this->matchWithMasterDataFromSmartInput($result);

            // 正規化ルールの適用
            $result = $this->applyNormalizationRules($result);

            // 信頼度スコアの計算（統一入力用）
            $result = $this->calculateSmartInputConfidenceScore($result, $input);

            // コスト追跡
            if ($userId && isset($result['cost_estimate'])) {
                $this->costTracker->trackUsage($userId, [
                    'provider' => $result['provider'],
                    'operation_type' => 'smart_normalization',
                    'cost' => $result['cost_estimate'],
                    'response_time_ms' => $result['response_time_ms'],
                    'metadata' => [
                        'input' => $input,
                    ],
                ]);
            }

            // メタデータの追加
            $result['metadata'] = [
                'original_input' => $input,
                'language' => $language,
                'provider' => $result['provider'] ?? 'unknown',
                'timestamp' => now()->toISOString(),
                'cached' => Cache::has($cacheKey),
            ];

            return $result;

        } catch (\Exception $e) {
            Log::error('Smart normalization service failed', [
                'input' => $input,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 統一入力からのマスタデータ照合
     */
    private function matchWithMasterDataFromSmartInput(array $normalizedData): array
    {
        if (! isset($normalizedData['normalized_data'])) {
            return $normalizedData;
        }

        $data = $normalizedData['normalized_data'];

        // ブランド名の照合（日本語・英語両方を試行）
        if (isset($data['normalized_brand_ja']) || isset($data['normalized_brand_en'])) {
            $brandToMatch = $data['normalized_brand_ja'] ?? $data['normalized_brand_en'];
            $matchedBrand = $this->findMatchingBrand($brandToMatch);
            if ($matchedBrand) {
                $data['matched_brand'] = $matchedBrand;
                $data['brand_match_confidence'] = $this->calculateBrandMatchConfidence($brandToMatch, $matchedBrand);
            }
        }

        // 香水名の照合（ブランドが特定できた場合のみ）
        if (isset($data['matched_brand']) && (isset($data['normalized_fragrance_ja']) || isset($data['normalized_fragrance_en']))) {
            $fragranceToMatch = $data['normalized_fragrance_ja'] ?? $data['normalized_fragrance_en'];
            $matchedFragrance = $this->findMatchingFragrance(
                $data['matched_brand']['id'],
                $fragranceToMatch
            );
            if ($matchedFragrance) {
                $data['matched_fragrance'] = $matchedFragrance;
                $data['fragrance_match_confidence'] = $this->calculateFragranceMatchConfidence(
                    $fragranceToMatch,
                    $matchedFragrance
                );
            }
        }

        $normalizedData['normalized_data'] = $data;

        return $normalizedData;
    }

    /**
     * 統一入力用の信頼度スコア計算
     */
    private function calculateSmartInputConfidenceScore(array $result, string $originalInput): array
    {
        if (! isset($result['normalized_data'])) {
            return $result;
        }

        $data = &$result['normalized_data'];
        $totalConfidence = $data['confidence_score'] ?? 0.8;

        // マスタマッチの信頼度を加味
        if (isset($data['brand_match_confidence'])) {
            $totalConfidence = ($totalConfidence + $data['brand_match_confidence']) / 2;
        }

        if (isset($data['fragrance_match_confidence'])) {
            $totalConfidence = ($totalConfidence + $data['fragrance_match_confidence']) / 2;
        }

        // AIが判断した信頼度をそのまま使用（独自計算は行わない）
        if (! isset($data['final_confidence_score']) || $data['final_confidence_score'] === 0) {
            // AIが信頼度を返していない場合のみ、デフォルト値を設定
            $data['final_confidence_score'] = 0.75; // 中程度の信頼度
        }

        return $result;
    }

    /**
     * 統一入力用フォールバック正規化処理
     */
    private function generateFallbackSmartNormalization(string $input, string $language): array
    {
        // シンプルなパターンマッチングで分割を試行
        $patterns = [
            '/(.+?)\s+(.+)/',  // スペース区切り
            '/(.+?)・(.+)/',   // 中点区切り
            '/(.+?)\/(.+)/',   // スラッシュ区切り
        ];

        $brandName = '';
        $fragranceName = '';

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($input), $matches)) {
                $brandName = trim($matches[1]);
                $fragranceName = trim($matches[2]);
                break;
            }
        }

        // パターンマッチしなかった場合は入力全体を香水名とみなす
        if (empty($brandName) && empty($fragranceName)) {
            $fragranceName = trim($input);
        }

        return [
            'normalized_data' => [
                'normalized_brand_ja' => $brandName,
                'normalized_brand_en' => '',
                'normalized_fragrance_ja' => $fragranceName,
                'normalized_fragrance_en' => '',
                'confidence_score' => 0.3, // フォールバックは低い信頼度
                'fallback_reason' => 'AI provider unavailable',
            ],
            'response_time_ms' => 10,
            'provider' => 'fallback',
            'cost_estimate' => 0.0,
        ];
    }

    /**
     * 統一入力用キャッシュキー生成
     */
    private function generateSmartInputCacheKey(string $operation, string $input, ?string $provider, string $language): string
    {
        $provider = $provider ?: $this->providerFactory->getDefaultProvider();

        return "ai:{$operation}:".md5("{$input}:{$provider}:{$language}");
    }

    /**
     * キャッシュキー生成
     */
    private function generateCacheKey(string $operation, string $brandName, string $fragranceName, ?string $provider, string $language): string
    {
        $provider = $provider ?: $this->providerFactory->getDefaultProvider();

        return "ai:{$operation}:".md5("{$brandName}:{$fragranceName}:{$provider}:{$language}");
    }
}
