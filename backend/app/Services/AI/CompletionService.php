<?php

namespace App\Services\AI;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\CostTrackingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompletionService
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
     * リアルタイム補完機能
     *
     * @param string $query 検索クエリ
     * @param array $options オプション設定
     * @return array 補完結果
     */
    public function complete(string $query, array $options = []): array
    {
        // 入力バリデーション
        if (strlen(trim($query)) < 2) {
            return [
                'suggestions' => [],
                'message' => 'Query must be at least 2 characters long',
                'response_time_ms' => 0,
                'provider' => null,
                'cost_estimate' => 0.0,
            ];
        }

        $provider = $options['provider'] ?? null;
        $type = $options['type'] ?? 'brand';
        $limit = min($options['limit'] ?? 10, 20); // 最大20件に制限
        $language = $options['language'] ?? 'ja';
        $userId = $options['user_id'] ?? null;

        // キャッシュキー生成
        $cacheKey = $this->generateCacheKey('completion', $query, $type, $provider, $language);

        try {
            // キャッシュから結果を取得（5分間キャッシュ）
            $result = Cache::remember($cacheKey, 300, function () use ($query, $provider, $type, $limit, $language) {
                $aiProvider = $this->providerFactory->create($provider);

                return $aiProvider->complete($query, [
                    'type' => $type,
                    'limit' => $limit,
                    'language' => $language,
                ]);
            });

            // コスト追跡
            if ($userId && isset($result['cost_estimate'])) {
                $this->costTracker->trackUsage($userId, [
                    'provider' => $result['provider'],
                    'operation_type' => 'completion',
                    'cost' => $result['cost_estimate'],
                    'response_time_ms' => $result['response_time_ms'],
                    'query_length' => strlen($query),
                ]);
            }

            // 結果の後処理
            $result['suggestions'] = $this->processSuggestions($result['suggestions'] ?? [], $query);
            $result['cached'] = Cache::has($cacheKey);

            return $result;

        } catch (\Exception $e) {
            Log::error('Completion service failed', [
                'query' => $query,
                'type' => $type,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            // フォールバック処理
            return $this->handleCompletionFallback($query, $type, $language);
        }
    }

    /**
     * 複数クエリの一括補完
     *
     * @param array $queries クエリ配列
     * @param array $options オプション設定
     * @return array 結果配列
     */
    public function batchComplete(array $queries, array $options = []): array
    {
        $results = [];

        foreach ($queries as $query) {
            $results[] = $this->complete($query, $options);
        }

        return [
            'results' => $results,
            'total_queries' => count($queries),
            'total_cost_estimate' => array_sum(array_column($results, 'cost_estimate')),
        ];
    }

    /**
     * 補完候補の品質評価
     *
     * @param string $query 元のクエリ
     * @param array $suggestions 補完候補
     * @return array 評価済み候補
     */
    private function processSuggestions(array $suggestions, string $query): array
    {
        $query = strtolower(trim($query));

        return array_map(function ($suggestion) use ($query) {
            // 文字列類似度の計算
            $similarity = $this->calculateSimilarity($query, strtolower($suggestion['text'] ?? ''));

            // 信頼度調整（類似度も考慮）
            $adjustedConfidence = ($suggestion['confidence'] ?? 0.0) * (0.7 + 0.3 * $similarity);

            return array_merge($suggestion, [
                'similarity_score' => round($similarity, 3),
                'adjusted_confidence' => round($adjustedConfidence, 3),
            ]);
        }, $suggestions);
    }

    /**
     * 文字列類似度の計算
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $len1 = mb_strlen($str1);
        $len2 = mb_strlen($str2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        // レーベンシュタイン距離を使用
        $distance = levenshtein($str1, $str2);
        $maxLength = max($len1, $len2);

        return 1.0 - ($distance / $maxLength);
    }

    /**
     * キャッシュキー生成
     *
     * @param string $operation
     * @param string $query
     * @param string $type
     * @param string|null $provider
     * @param string $language
     * @return string
     */
    private function generateCacheKey(string $operation, string $query, string $type, ?string $provider, string $language): string
    {
        $provider = $provider ?: $this->providerFactory->getDefaultProvider();
        return "ai:{$operation}:" . md5("{$query}:{$type}:{$provider}:{$language}");
    }

    /**
     * 補完失敗時のフォールバック処理
     *
     * @param string $query
     * @param string $type
     * @param string $language
     * @return array
     */
    private function handleCompletionFallback(string $query, string $type, string $language): array
    {
        // 基本的なパターンマッチングによる簡易補完
        $fallbackSuggestions = $this->generateFallbackSuggestions($query, $type);

        return [
            'suggestions' => $fallbackSuggestions,
            'response_time_ms' => 0,
            'provider' => 'fallback',
            'cost_estimate' => 0.0,
            'error' => 'AI provider unavailable, using fallback suggestions',
        ];
    }

    /**
     * フォールバック用の簡易補完候補生成
     *
     * @param string $query
     * @param string $type
     * @return array
     */
    private function generateFallbackSuggestions(string $query, string $type): array
    {
        // 簡単な補完例（実際の実装では既存のマスタデータから検索）
        $commonBrands = ['CHANEL', 'Dior', 'Tom Ford', 'Creed', 'Jo Malone'];
        $commonFragrances = ['No.5', 'Sauvage', 'Black Orchid', 'Aventus', 'Lime Basil & Mandarin'];

        $suggestions = [];
        $queryLower = strtolower($query);

        $candidates = $type === 'brand' ? $commonBrands : $commonFragrances;

        foreach ($candidates as $candidate) {
            if (stripos($candidate, $query) !== false) {
                $suggestions[] = [
                    'text' => $candidate,
                    'confidence' => 0.5,
                    'type' => $type,
                    'metadata' => [
                        'source' => 'fallback',
                    ],
                ];
            }
        }

        return array_slice($suggestions, 0, 5);
    }
}