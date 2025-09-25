<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CostTrackingService
{
    /**
     * AI使用量を記録
     *
     * @param int $userId ユーザーID
     * @param array $usage 使用量データ
     * @return void
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
     * @param int $userId
     * @param string|null $month YYYY-MM形式（nullの場合は当月）
     * @return array
     */
    public function getMonthlyUsage(int $userId, ?string $month = null): array
    {
        $month = $month ?: now()->format('Y-m');

        $usage = DB::table('ai_cost_tracking')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $month . '-01 00:00:00')
            ->where('created_at', '<', now()->parse($month . '-01')->addMonth()->format('Y-m-d 00:00:00'))
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
     * @param int $userId
     * @param float $maxDailyCost 日次最大コスト（USD）
     * @return bool
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
     * @param int $userId
     * @param float $maxMonthlyCost 月次最大コスト（USD）
     * @return bool
     */
    public function checkMonthlyLimit(int $userId, float $maxMonthlyCost = 10.0): bool
    {
        $monthlyUsage = $this->getMonthlyUsage($userId);
        return $monthlyUsage['total_cost'] < $maxMonthlyCost;
    }

    /**
     * レート制限チェック（時間あたりのリクエスト数）
     *
     * @param int $userId
     * @param int $maxRequestsPerHour 時間あたり最大リクエスト数
     * @return bool
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
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getGlobalStats(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: now()->toDateString();

        $stats = DB::table('ai_cost_tracking')
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59')
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
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59')
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
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59')
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
     *
     * @param int $limit
     * @param string|null $month
     * @return array
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
            $query->where('ai_cost_tracking.created_at', '>=', $month . '-01 00:00:00')
                  ->where('ai_cost_tracking.created_at', '<', now()->parse($month . '-01')->addMonth()->format('Y-m-d 00:00:00'));
        }

        return $query->orderByDesc('total_cost')
                     ->limit($limit)
                     ->get()
                     ->toArray();
    }
}