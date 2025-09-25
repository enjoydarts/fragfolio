<?php

namespace App\UseCases\AI;

use App\Services\AI\CompletionService;
use App\Services\AI\AIProviderFactory;
use App\Services\AI\CostTrackingService;
use Illuminate\Support\Facades\Log;

class CompleteFragranceUseCase
{
    private CompletionService $completionService;
    private AIProviderFactory $providerFactory;
    private CostTrackingService $costTracker;

    public function __construct(
        CompletionService $completionService,
        AIProviderFactory $providerFactory,
        CostTrackingService $costTracker
    ) {
        $this->completionService = $completionService;
        $this->providerFactory = $providerFactory;
        $this->costTracker = $costTracker;
    }

    /**
     * 香水補完を実行
     *
     * @param string $query 検索クエリ
     * @param array $options オプション設定
     * @return array 補完結果
     */
    public function execute(string $query, array $options = []): array
    {
        // 前処理: クエリのサニタイズ
        $query = trim($query);

        if (empty($query)) {
            throw new \InvalidArgumentException('Query cannot be empty');
        }

        // ユーザーの制限チェック
        if (isset($options['user_id'])) {
            $this->validateUserLimits($options['user_id']);
        }

        // プロバイダーの可用性チェック
        if (isset($options['provider']) && !$this->providerFactory->isProviderAvailable($options['provider'])) {
            throw new \InvalidArgumentException("Provider {$options['provider']} is not available");
        }

        try {
            // 補完実行
            $result = $this->completionService->complete($query, $options);

            // 結果の後処理
            $result = $this->postProcessResult($result, $query, $options);

            // ログ記録
            $this->logCompletion($query, $options, $result);

            return $result;

        } catch (\Exception $e) {
            Log::error('Fragrance completion failed', [
                'query' => $query,
                'options' => $options,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 一括補完を実行
     *
     * @param array $queries クエリ配列
     * @param array $options オプション設定
     * @return array 補完結果
     */
    public function executeBatch(array $queries, array $options = []): array
    {
        // ユーザーの制限チェック
        if (isset($options['user_id'])) {
            $this->validateUserLimits($options['user_id']);
        }

        try {
            $result = $this->completionService->batchComplete($queries, $options);

            // バッチ処理ログ
            Log::info('Batch completion executed', [
                'queries_count' => count($queries),
                'total_cost' => $result['total_cost_estimate'] ?? 0,
                'user_id' => $options['user_id'] ?? null,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Batch fragrance completion failed', [
                'queries_count' => count($queries),
                'options' => $options,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 利用可能なプロバイダー一覧を取得
     *
     * @return array
     */
    public function getAvailableProviders(): array
    {
        $providers = $this->providerFactory->getAvailableProviders();
        $defaultProvider = $this->providerFactory->getDefaultProvider();

        return [
            'providers' => $providers,
            'default' => $defaultProvider,
            'total' => count($providers),
        ];
    }

    /**
     * プロバイダーのヘルスチェック
     *
     * @param string|null $provider
     * @return array
     */
    public function healthCheck(?string $provider = null): array
    {
        $results = [];

        if ($provider) {
            $providers = [$provider];
        } else {
            $providers = $this->providerFactory->getAvailableProviders();
        }

        foreach ($providers as $providerName) {
            try {
                $aiProvider = $this->providerFactory->create($providerName);

                // 簡単なテスト補完を実行
                $testResult = $aiProvider->complete('test', ['limit' => 1]);

                $results[$providerName] = [
                    'status' => 'healthy',
                    'response_time_ms' => $testResult['response_time_ms'] ?? 0,
                    'last_checked' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                $results[$providerName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'last_checked' => now()->toISOString(),
                ];
            }
        }

        return [
            'providers' => $results,
            'overall_status' => $this->determineOverallHealth($results),
        ];
    }

    /**
     * ユーザーの利用制限をチェック
     *
     * @param int $userId
     * @throws \Exception
     */
    private function validateUserLimits(int $userId): void
    {
        // 日次制限チェック
        if (!$this->costTracker->checkDailyLimit($userId)) {
            throw new \Exception('Daily AI usage limit exceeded');
        }

        // 月次制限チェック
        if (!$this->costTracker->checkMonthlyLimit($userId)) {
            throw new \Exception('Monthly AI usage limit exceeded');
        }

        // レート制限チェック
        if (!$this->costTracker->checkRateLimit($userId)) {
            throw new \Exception('Rate limit exceeded. Please wait before making another request');
        }
    }

    /**
     * 結果の後処理
     *
     * @param array $result
     * @param string $query
     * @param array $options
     * @return array
     */
    private function postProcessResult(array $result, string $query, array $options): array
    {
        // 結果にメタデータを追加
        $result['metadata'] = [
            'query' => $query,
            'type' => $options['type'] ?? 'unknown',
            'language' => $options['language'] ?? 'ja',
            'provider' => $result['provider'] ?? 'unknown',
            'timestamp' => now()->toISOString(),
        ];

        // 候補を信頼度順にソート
        if (isset($result['suggestions']) && is_array($result['suggestions'])) {
            usort($result['suggestions'], function ($a, $b) {
                return ($b['adjusted_confidence'] ?? $b['confidence'] ?? 0)
                    <=> ($a['adjusted_confidence'] ?? $a['confidence'] ?? 0);
            });
        }

        return $result;
    }

    /**
     * 補完ログを記録
     *
     * @param string $query
     * @param array $options
     * @param array $result
     */
    private function logCompletion(string $query, array $options, array $result): void
    {
        Log::info('Fragrance completion executed', [
            'query' => $query,
            'type' => $options['type'] ?? 'unknown',
            'provider' => $result['provider'] ?? 'unknown',
            'suggestions_count' => count($result['suggestions'] ?? []),
            'response_time_ms' => $result['response_time_ms'] ?? 0,
            'cost_estimate' => $result['cost_estimate'] ?? 0,
            'user_id' => $options['user_id'] ?? null,
            'cached' => $result['cached'] ?? false,
        ]);
    }

    /**
     * 全体的な健康状態を判定
     *
     * @param array $results
     * @return string
     */
    private function determineOverallHealth(array $results): string
    {
        $healthyCount = 0;
        $totalCount = count($results);

        foreach ($results as $result) {
            if ($result['status'] === 'healthy') {
                $healthyCount++;
            }
        }

        if ($healthyCount === 0) {
            return 'critical';
        } elseif ($healthyCount < $totalCount) {
            return 'degraded';
        } else {
            return 'healthy';
        }
    }
}