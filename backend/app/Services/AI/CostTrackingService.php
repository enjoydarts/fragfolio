<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CostTrackingService
{
    // デフォルト制限値
    private const DEFAULT_DAILY_LIMIT = 1.0; // $1.00 per day

    private const DEFAULT_MONTHLY_LIMIT = 10.0; // $10.00 per month

    private const DEFAULT_HOURLY_REQUESTS_LIMIT = 100;

    // アラート閾値
    private const WARNING_THRESHOLD = 0.8; // 80%で警告

    private const CRITICAL_THRESHOLD = 0.95; // 95%で重要警告

    /**
     * AI使用量を記録
     *
     * @param  int  $userId  ユーザーID
     * @param  array  $usage  使用量データ
     */
    public function trackUsage(int $userId, array $usage): void
    {
        try {
            DB::table('ai_cost_tracking')->insert([
                'user_id' => $userId,
                'provider' => $usage['provider'] ?? 'unknown',
                'operation_type' => $usage['operation_type'] ?? 'unknown',
                'tokens_used' => $usage['tokens_used'] ?? 0,
                'estimated_cost' => $usage['cost'] ?? 0.0,
                'api_response_time_ms' => $usage['response_time_ms'] ?? 0,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to track AI usage', [
                'user_id' => $userId,
                'usage' => $usage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ユーザーの月次コスト取得
     *
     * @param  string|null  $month  YYYY-MM形式（nullの場合は当月）
     */
    public function getMonthlyUsage(int $userId, ?string $month = null): array
    {
        $month = $month ?: now()->format('Y-m');

        $usage = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $month.'-01 00:00:00')
            ->where('created_at', '<', now()->parse($month.'-01')->addMonth()->format('Y-m-d 00:00:00'))
            ->selectRaw('
                provider,
                operation_type,
                COUNT(*) as request_count,
                SUM(tokens_used) as total_tokens,
                SUM(estimated_cost) as total_cost,
                AVG(api_response_time_ms) as avg_response_time
            ')
            ->groupBy(['provider', 'operation_type'])
            ->get();

        $summary = [
            'total_cost' => $usage->sum('total_cost'),
            'total_requests' => $usage->sum('request_count'),
            'total_tokens' => $usage->sum('total_tokens'),
            'avg_response_time' => $usage->avg('avg_response_time'),
            'by_provider' => [],
            'by_operation' => [],
        ];

        // プロバイダー別集計
        foreach ($usage->groupBy('provider') as $provider => $providerUsage) {
            $summary['by_provider'][$provider] = [
                'cost' => $providerUsage->sum('total_cost'),
                'requests' => $providerUsage->sum('request_count'),
                'tokens' => $providerUsage->sum('total_tokens'),
            ];
        }

        // 操作タイプ別集計
        foreach ($usage->groupBy('operation_type') as $operation => $operationUsage) {
            $summary['by_operation'][$operation] = [
                'cost' => $operationUsage->sum('total_cost'),
                'requests' => $operationUsage->sum('request_count'),
                'tokens' => $operationUsage->sum('total_tokens'),
            ];
        }

        return $summary;
    }

    /**
     * ユーザーの日次使用量制限チェック
     *
     * @param  float  $maxDailyCost  日次最大コスト（USD）
     */
    public function checkDailyLimit(int $userId, float $maxDailyCost = 1.0): bool
    {
        $todayCost = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('estimated_cost');

        return $todayCost < $maxDailyCost;
    }

    /**
     * 月次制限チェック
     *
     * @param  float  $maxMonthlyCost  月次最大コスト（USD）
     */
    public function checkMonthlyLimit(int $userId, float $maxMonthlyCost = 10.0): bool
    {
        $monthlyUsage = $this->getMonthlyUsage($userId);

        return $monthlyUsage['total_cost'] < $maxMonthlyCost;
    }

    /**
     * レート制限チェック（時間あたりのリクエスト数）
     *
     * @param  int  $maxRequestsPerHour  時間あたり最大リクエスト数
     */
    public function checkRateLimit(int $userId, int $maxRequestsPerHour = 100): bool
    {
        $hourlyRequests = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $hourlyRequests < $maxRequestsPerHour;
    }

    /**
     * 全体のコスト統計（管理者向け）
     */
    public function getGlobalStats(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: now()->toDateString();

        $stats = DB::table('ai_cost_tracking')
            ->where('created_at', '>=', $startDate.' 00:00:00')
            ->where('created_at', '<=', $endDate.' 23:59:59')
            ->selectRaw('
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_id) as active_users,
                SUM(estimated_cost) as total_cost,
                AVG(estimated_cost) as avg_cost_per_request,
                AVG(api_response_time_ms) as avg_response_time
            ')
            ->first();

        // プロバイダー別統計
        $providerStats = DB::table('ai_cost_tracking')
            ->where('created_at', '>=', $startDate.' 00:00:00')
            ->where('created_at', '<=', $endDate.' 23:59:59')
            ->selectRaw('
                provider,
                COUNT(*) as requests,
                SUM(estimated_cost) as cost,
                AVG(api_response_time_ms) as avg_response_time
            ')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        // 日別統計
        $dailyStats = DB::table('ai_cost_tracking')
            ->where('created_at', '>=', $startDate.' 00:00:00')
            ->where('created_at', '<=', $endDate.' 23:59:59')
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as requests,
                SUM(estimated_cost) as cost
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'summary' => $stats,
            'by_provider' => $providerStats,
            'daily_breakdown' => $dailyStats,
        ];
    }

    /**
     * コスト上位ユーザー取得（管理者向け）
     */
    public function getTopUsers(int $limit = 10, ?string $month = null): array
    {
        $query = DB::table('ai_cost_tracking')
            ->join('users', 'ai_cost_tracking.user_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name,
                users.email,
                COUNT(*) as total_requests,
                SUM(ai_cost_tracking.estimated_cost) as total_cost,
                AVG(ai_cost_tracking.api_response_time_ms) as avg_response_time
            ')
            ->groupBy(['users.id', 'users.name', 'users.email']);

        if ($month) {
            $query->where('ai_cost_tracking.created_at', '>=', $month.'-01 00:00:00')
                ->where('ai_cost_tracking.created_at', '<', now()->parse($month.'-01')->addMonth()->format('Y-m-d 00:00:00'));
        }

        return $query->orderByDesc('total_cost')
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                return (array) $user;
            })
            ->toArray();
    }

    /**
     * 包括的な制限チェック（使用前に呼び出し）
     *
     * @param  array  $options  制限設定のオーバーライド
     * @return array チェック結果と詳細情報
     */
    public function checkAllLimits(int $userId, array $options = []): array
    {
        $dailyLimit = $options['daily_limit'] ?? self::DEFAULT_DAILY_LIMIT;
        $monthlyLimit = $options['monthly_limit'] ?? self::DEFAULT_MONTHLY_LIMIT;
        $hourlyRequestsLimit = $options['hourly_requests_limit'] ?? self::DEFAULT_HOURLY_REQUESTS_LIMIT;

        // 現在の使用量を取得
        $dailyUsage = $this->getDailyUsage($userId);
        $monthlyUsage = $this->getMonthlyUsage($userId);
        $hourlyRequests = $this->getHourlyRequestCount($userId);

        $results = [
            'can_proceed' => true,
            'limits' => [
                'daily' => [
                    'current' => $dailyUsage,
                    'limit' => $dailyLimit,
                    'percentage' => ($dailyUsage / $dailyLimit) * 100,
                    'exceeded' => $dailyUsage >= $dailyLimit,
                ],
                'monthly' => [
                    'current' => $monthlyUsage['total_cost'],
                    'limit' => $monthlyLimit,
                    'percentage' => ($monthlyUsage['total_cost'] / $monthlyLimit) * 100,
                    'exceeded' => $monthlyUsage['total_cost'] >= $monthlyLimit,
                ],
                'hourly_requests' => [
                    'current' => $hourlyRequests,
                    'limit' => $hourlyRequestsLimit,
                    'percentage' => ($hourlyRequests / $hourlyRequestsLimit) * 100,
                    'exceeded' => $hourlyRequests >= $hourlyRequestsLimit,
                ],
            ],
            'warnings' => [],
        ];

        // 制限超過チェック
        foreach ($results['limits'] as $type => $limit) {
            if ($limit['exceeded']) {
                $results['can_proceed'] = false;
            }

            // 警告レベルチェック
            if ($limit['percentage'] >= self::CRITICAL_THRESHOLD * 100) {
                $results['warnings'][] = [
                    'type' => $type,
                    'level' => 'critical',
                    'message' => sprintf('Critical: %s usage at %.1f%%', $type, $limit['percentage']),
                ];
            } elseif ($limit['percentage'] >= self::WARNING_THRESHOLD * 100) {
                $results['warnings'][] = [
                    'type' => $type,
                    'level' => 'warning',
                    'message' => sprintf('Warning: %s usage at %.1f%%', $type, $limit['percentage']),
                ];
            }
        }

        // アラート処理
        $this->processAlerts($userId, $results);

        return $results;
    }

    /**
     * ユーザーの日次使用量取得
     */
    private function getDailyUsage(int $userId): float
    {
        return DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('estimated_cost');
    }

    /**
     * 時間あたりのリクエスト数取得
     */
    private function getHourlyRequestCount(int $userId): int
    {
        return DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }

    /**
     * アラート処理
     */
    private function processAlerts(int $userId, array $limitResults): void
    {
        foreach ($limitResults['warnings'] as $warning) {
            $alertKey = "cost_alert:{$userId}:{$warning['type']}:{$warning['level']}";

            // 重複アラート防止（1時間に1回まで）
            if (Cache::has($alertKey)) {
                continue;
            }

            // アラートをキャッシュに記録
            Cache::put($alertKey, true, 3600); // 1時間

            Log::warning('AI Cost Alert', [
                'user_id' => $userId,
                'type' => $warning['type'],
                'level' => $warning['level'],
                'message' => $warning['message'],
                'limits' => $limitResults['limits'][$warning['type']],
            ]);

            // 必要に応じてユーザーへの通知も可能
            // $this->sendUserAlert($userId, $warning);
        }
    }

    /**
     * コスト効率の分析
     */
    public function analyzeCostEfficiency(int $userId, ?string $month = null): array
    {
        $usage = $this->getMonthlyUsage($userId, $month);

        if ($usage['total_requests'] === 0) {
            return [
                'efficiency_score' => 0,
                'insights' => ['No usage data available for analysis'],
                'recommendations' => ['Start using AI features to get efficiency insights'],
            ];
        }

        $costPerRequest = $usage['total_cost'] / $usage['total_requests'];
        $avgResponseTime = $usage['avg_response_time'];

        $insights = [];
        $recommendations = [];

        // コスト効率の評価
        if ($costPerRequest > 0.05) {
            $insights[] = sprintf('High cost per request: $%.4f', $costPerRequest);
            $recommendations[] = 'Consider optimizing prompts to reduce token usage';
        } elseif ($costPerRequest < 0.01) {
            $insights[] = sprintf('Excellent cost efficiency: $%.4f per request', $costPerRequest);
        }

        // レスポンス時間の評価
        if ($avgResponseTime > 2000) {
            $insights[] = sprintf('Slow average response time: %.0fms', $avgResponseTime);
            $recommendations[] = 'Consider using faster models for simple tasks';
        }

        // プロバイダー効率の比較
        $mostEfficientProvider = null;
        $lowestCostPerRequest = PHP_FLOAT_MAX;

        foreach ($usage['by_provider'] as $provider => $providerData) {
            if ($providerData['requests'] > 0) {
                $providerCostPerRequest = $providerData['cost'] / $providerData['requests'];
                if ($providerCostPerRequest < $lowestCostPerRequest) {
                    $lowestCostPerRequest = $providerCostPerRequest;
                    $mostEfficientProvider = $provider;
                }
            }
        }

        if ($mostEfficientProvider) {
            $recommendations[] = "Most cost-efficient provider: {$mostEfficientProvider}";
        }

        // 効率スコア計算（0-100）
        $efficiencyScore = max(0, min(100, 100 - ($costPerRequest * 1000) - ($avgResponseTime / 50)));

        return [
            'efficiency_score' => round($efficiencyScore, 1),
            'cost_per_request' => $costPerRequest,
            'avg_response_time' => $avgResponseTime,
            'insights' => $insights,
            'recommendations' => $recommendations,
            'most_efficient_provider' => $mostEfficientProvider,
        ];
    }

    /**
     * 予測コスト計算
     */
    public function predictMonthlyCost(int $userId): array
    {
        // 過去30日の平均日次コストから予測
        $dailyCosts = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(estimated_cost) as daily_cost')
            ->groupBy('date')
            ->get();

        $dailyAverage = $dailyCosts->avg('daily_cost') ?? 0;

        $daysInMonth = now()->daysInMonth;
        $daysElapsed = now()->day;
        $daysRemaining = $daysInMonth - $daysElapsed;

        $currentMonthCost = $this->getMonthlyUsage($userId)['total_cost'];
        $predictedTotalCost = $currentMonthCost + ($dailyAverage * $daysRemaining);

        return [
            'current_cost' => $currentMonthCost,
            'daily_average' => $dailyAverage,
            'predicted_total' => $predictedTotalCost,
            'days_remaining' => $daysRemaining,
            'projected_overage' => max(0, $predictedTotalCost - self::DEFAULT_MONTHLY_LIMIT),
        ];
    }

    /**
     * 使用パターン分析
     */
    public function analyzeUsagePatterns(int $userId): array
    {
        // 時間別使用パターン
        $hourlyPattern = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as requests, AVG(estimated_cost) as avg_cost')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        // 曜日別使用パターン
        $weeklyPattern = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subWeeks(4))
            ->selectRaw('DAYOFWEEK(created_at) as day_of_week, COUNT(*) as requests, AVG(estimated_cost) as avg_cost')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        // ピーク使用時間の特定
        $peakHour = $hourlyPattern->sortByDesc('requests')->first()?->hour ?? 12;
        $peakDay = $weeklyPattern->sortByDesc('requests')->first()?->day_of_week ?? 2; // Monday

        return [
            'hourly_pattern' => $hourlyPattern->toArray(),
            'weekly_pattern' => $weeklyPattern->toArray(),
            'peak_hour' => $peakHour,
            'peak_day' => $peakDay,
            'usage_insights' => $this->generateUsageInsights($hourlyPattern, $weeklyPattern),
        ];
    }

    /**
     * 使用パターンからインサイト生成
     */
    private function generateUsageInsights($hourlyPattern, $weeklyPattern): array
    {
        $insights = [];

        // 夜間使用が多い場合
        $nightUsage = $hourlyPattern->whereBetween('hour', [22, 6])->sum('requests');
        $totalUsage = $hourlyPattern->sum('requests');
        if ($nightUsage > $totalUsage * 0.3) {
            $insights[] = 'High nighttime usage detected - consider scheduling batch operations during off-peak hours';
        }

        // 週末使用パターン
        $weekendUsage = $weeklyPattern->whereIn('day_of_week', [1, 7])->sum('requests'); // Sunday = 1, Saturday = 7
        if ($weekendUsage > $totalUsage * 0.4) {
            $insights[] = "Significant weekend usage - you're an active user!";
        }

        return $insights;
    }
}
