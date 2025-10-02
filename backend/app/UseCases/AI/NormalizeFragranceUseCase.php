<?php

namespace App\UseCases\AI;

use App\Services\AI\NormalizationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class NormalizeFragranceUseCase
{
    private NormalizationService $normalizationService;

    public function __construct(NormalizationService $normalizationService)
    {
        $this->normalizationService = $normalizationService;
    }

    /**
     * 香水情報の正規化
     *
     * @param  string  $brandName  ブランド名
     * @param  string  $fragranceName  香水名
     * @param  array  $options  オプション設定
     * @return array 正規化結果
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function normalize(string $brandName, string $fragranceName, array $options = []): array
    {
        $userId = $options['user_id'] ?? null;

        // ユーザー固有の制限チェック
        if ($userId) {
            $this->checkUserLimits($userId, 'normalization');
        }

        // 入力値のサニタイズ
        $brandName = $this->sanitizeInput($brandName);
        $fragranceName = $this->sanitizeInput($fragranceName);

        // 実行時間計測開始
        $startTime = microtime(true);

        try {
            $result = $this->normalizationService->normalize($brandName, $fragranceName, $options);

            // 実行時間を結果に追加
            $executionTime = round((microtime(true) - $startTime) * 1000);
            $result['execution_time_ms'] = $executionTime;

            // ユーザーフィードバック処理
            $this->processUserFeedback($result, $options);

            // 使用状況ログ
            Log::info('Normalization completed', [
                'user_id' => $userId,
                'brand_name' => $brandName,
                'fragrance_name' => $fragranceName,
                'provider' => $result['provider'] ?? 'unknown',
                'execution_time_ms' => $executionTime,
                'confidence_score' => $result['normalized_data']['final_confidence_score'] ?? null,
            ]);

            return $result;

        } catch (\InvalidArgumentException $e) {
            Log::warning('Normalization validation failed', [
                'user_id' => $userId,
                'brand_name' => $brandName,
                'fragrance_name' => $fragranceName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Normalization failed', [
                'user_id' => $userId,
                'brand_name' => $brandName,
                'fragrance_name' => $fragranceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function batchNormalize(array $fragrances, array $options = []): array
    {
        $userId = $options['user_id'] ?? null;

        // バッチサイズ制限チェック
        if (count($fragrances) > 10) {
            throw new \InvalidArgumentException('Batch size cannot exceed 10 items');
        }

        // ユーザー固有の制限チェック
        if ($userId) {
            $this->checkUserLimits($userId, 'batch_normalization');
        }

        // 入力データのサニタイズ
        $sanitizedFragrances = [];
        foreach ($fragrances as $fragrance) {
            if (! isset($fragrance['brand_name']) || ! isset($fragrance['fragrance_name'])) {
                throw new \InvalidArgumentException('Each fragrance must have brand_name and fragrance_name');
            }

            $sanitizedFragrances[] = [
                'brand_name' => $this->sanitizeInput($fragrance['brand_name']),
                'fragrance_name' => $this->sanitizeInput($fragrance['fragrance_name']),
            ];
        }

        // 実行時間計測開始
        $startTime = microtime(true);

        try {
            $result = $this->normalizationService->batchNormalize($sanitizedFragrances, $options);

            // 実行時間を結果に追加
            $executionTime = round((microtime(true) - $startTime) * 1000);
            $result['execution_time_ms'] = $executionTime;

            // 成功率の計算
            $successRate = $result['successful_count'] > 0
                ? round(($result['successful_count'] / $result['total_processed']) * 100, 2)
                : 0;
            $result['success_rate'] = $successRate;

            // 使用状況ログ
            Log::info('Batch normalization completed', [
                'user_id' => $userId,
                'total_items' => count($fragrances),
                'successful_count' => $result['successful_count'],
                'success_rate' => $successRate,
                'total_cost' => $result['total_cost_estimate'],
                'execution_time_ms' => $executionTime,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Batch normalization failed', [
                'user_id' => $userId,
                'total_items' => count($fragrances),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * ユーザー制限チェック
     *
     * @throws \Exception
     */
    private function checkUserLimits(int $userId, string $operation): void
    {
        $key = "normalization:{$operation}:{$userId}";
        $maxAttempts = match ($operation) {
            'normalization' => 100, // 1時間に100回まで
            'batch_normalization' => 10, // 1時間に10回まで
            default => 50,
        };

        $executed = RateLimiter::attempt($key, $maxAttempts, function () {
            // 実際の処理は後で実行
        }, 3600); // 1時間

        if (! $executed) {
            $remainingTime = RateLimiter::availableIn($key);
            throw new \Exception(
                __('ai.rate_limit_exceeded', [
                    'operation' => $operation,
                    'minutes' => ceil($remainingTime / 60),
                ])
            );
        }
    }

    /**
     * 入力値のサニタイズ
     */
    private function sanitizeInput(string $input): string
    {
        // 前後の空白を削除
        $input = trim($input);

        // 連続する空白を単一の空白に変換
        $input = preg_replace('/\s+/', ' ', $input);

        // HTML タグを削除（セキュリティ対策）
        $input = strip_tags($input);

        // 特殊文字のエスケープ
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        return $input;
    }

    /**
     * ユーザーフィードバック処理
     */
    private function processUserFeedback(array &$result, array $options): void
    {
        $userId = $options['user_id'] ?? null;

        if (! $userId) {
            return;
        }

        // フィードバック情報を結果に追加
        $result['feedback'] = [
            'can_provide_feedback' => true,
            'feedback_url' => url("/api/ai/normalization/feedback/{$userId}"),
            'rating_options' => [
                'excellent' => __('ai.feedback.excellent'),
                'good' => __('ai.feedback.good'),
                'acceptable' => __('ai.feedback.acceptable'),
                'poor' => __('ai.feedback.poor'),
            ],
        ];
    }

    /**
     * 統一入力からの正規化処理
     *
     * @param  string  $input  統一入力テキスト（ブランド名、香水名、またはその両方）
     * @param  array  $options  オプション設定
     * @return array 正規化結果
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function normalizeFromInput(string $input, array $options = []): array
    {
        $userId = $options['user_id'] ?? null;

        // ユーザー固有の制限チェック
        if ($userId) {
            $this->checkUserLimits($userId, 'normalization');
        }

        // 入力値のサニタイズ
        $input = $this->sanitizeInput($input);

        // 実行時間計測開始
        $startTime = microtime(true);

        try {
            $result = $this->normalizationService->normalizeFromInput($input, $options);

            // 実行時間を結果に追加
            $executionTime = round((microtime(true) - $startTime) * 1000);
            $result['execution_time_ms'] = $executionTime;

            // ユーザーフィードバック処理
            $this->processUserFeedback($result, $options);

            // 使用状況ログ
            Log::info('Smart input normalization completed', [
                'user_id' => $userId,
                'input' => $input,
                'provider' => $result['provider'] ?? 'unknown',
                'execution_time_ms' => $executionTime,
                'confidence_score' => $result['normalized_data']['final_confidence_score'] ?? null,
            ]);

            return $result;

        } catch (\InvalidArgumentException $e) {
            Log::warning('Smart input normalization validation failed', [
                'user_id' => $userId,
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Smart input normalization failed', [
                'user_id' => $userId,
                'input' => $input,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 正規化結果の品質評価
     */
    public function evaluateQuality(array $result): array
    {
        $quality = [
            'overall_score' => 0.0,
            'factors' => [],
        ];

        if (! isset($result['normalized_data'])) {
            return $quality;
        }

        $data = $result['normalized_data'];
        $factors = [];

        // 信頼度スコア
        if (isset($data['final_confidence_score'])) {
            $confidenceScore = $data['final_confidence_score'];
            $factors['confidence'] = [
                'score' => $confidenceScore,
                'weight' => 0.4,
                'weighted_score' => $confidenceScore * 0.4,
            ];
        }

        // マスタデータマッチング
        $hasMatchedBrand = isset($data['matched_brand']);
        $hasMatchedFragrance = isset($data['matched_fragrance']);
        $matchScore = 0.0;
        if ($hasMatchedBrand) {
            $matchScore += 0.5;
        }
        if ($hasMatchedFragrance) {
            $matchScore += 0.5;
        }

        $factors['master_match'] = [
            'score' => $matchScore,
            'weight' => 0.3,
            'weighted_score' => $matchScore * 0.3,
        ];

        // レスポンス時間（速いほど良い）
        $responseTime = $result['response_time_ms'] ?? 5000;
        $timeScore = max(0, 1 - ($responseTime / 10000)); // 10秒以上で0点
        $factors['response_time'] = [
            'score' => $timeScore,
            'weight' => 0.1,
            'weighted_score' => $timeScore * 0.1,
        ];

        // プロバイダー信頼性
        $providerScore = match ($result['provider'] ?? 'unknown') {
            'openai' => 0.9,
            'anthropic' => 0.85,
            'fallback' => 0.3,
            default => 0.5,
        };
        $factors['provider_reliability'] = [
            'score' => $providerScore,
            'weight' => 0.2,
            'weighted_score' => $providerScore * 0.2,
        ];

        // 総合スコア計算
        $totalWeightedScore = array_sum(array_column($factors, 'weighted_score'));
        $quality['overall_score'] = round($totalWeightedScore, 3);
        $quality['factors'] = $factors;

        return $quality;
    }
}
