<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\CostTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostController extends Controller
{
    private CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
    }

    /**
     * ユーザーの使用量とコスト情報を取得
     */
    public function usage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'include_patterns' => 'sometimes|boolean',
        ]);

        try {
            $userId = $request->user()->id;
            $month = $validated['month'] ?? null;

            // 基本的な使用量情報
            $monthlyUsage = $this->costTrackingService->getMonthlyUsage($userId, $month);
            $limitCheck = $this->costTrackingService->checkAllLimits($userId);

            $result = [
                'user_id' => $userId,
                'month' => $month ?? now()->format('Y-m'),
                'usage' => $monthlyUsage,
                'limits' => $limitCheck,
                'cost_prediction' => $this->costTrackingService->predictMonthlyCost($userId),
                'efficiency' => $this->costTrackingService->analyzeCostEfficiency($userId, $month),
            ];

            // 使用パターン分析を含める場合
            if ($validated['include_patterns'] ?? false) {
                $result['patterns'] = $this->costTrackingService->analyzeUsagePatterns($userId);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.cost_usage_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 制限チェック
     */
    public function limits(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $limitCheck = $this->costTrackingService->checkAllLimits($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'can_proceed' => $limitCheck['can_proceed'],
                    'limits' => $limitCheck['limits'],
                    'warnings' => $limitCheck['warnings'],
                    'checked_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.limit_check_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 使用パターン分析
     */
    public function patterns(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $patterns = $this->costTrackingService->analyzeUsagePatterns($userId);

            return response()->json([
                'success' => true,
                'data' => $patterns,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.pattern_analysis_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * コスト効率分析
     */
    public function efficiency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        try {
            $userId = $request->user()->id;
            $month = $validated['month'] ?? null;

            $efficiency = $this->costTrackingService->analyzeCostEfficiency($userId, $month);

            return response()->json([
                'success' => true,
                'data' => $efficiency,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.efficiency_analysis_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 月次コスト予測
     */
    public function prediction(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $prediction = $this->costTrackingService->predictMonthlyCost($userId);

            return response()->json([
                'success' => true,
                'data' => $prediction,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.prediction_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 履歴データ取得
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'months' => 'sometimes|integer|min:1|max:12',
            'group_by' => ['sometimes', 'string', Rule::in(['day', 'week', 'month'])],
        ]);

        try {
            $userId = $request->user()->id;
            $months = $validated['months'] ?? 3;
            $groupBy = $validated['group_by'] ?? 'day';

            $history = $this->getUsageHistory($userId, $months, $groupBy);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'months' => $months,
                        'group_by' => $groupBy,
                        'start_date' => now()->subMonths($months)->startOfMonth()->toDateString(),
                        'end_date' => now()->toDateString(),
                    ],
                    'history' => $history,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.history_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 管理者向け全体統計（管理者専用）
     */
    public function globalStats(Request $request): JsonResponse
    {
        // 管理者権限チェック
        if (! $request->user() || $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 403);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d',
        ]);

        try {
            $stats = $this->costTrackingService->getGlobalStats(
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.global_stats_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 管理者向けコスト上位ユーザー（管理者専用）
     */
    public function topUsers(Request $request): JsonResponse
    {
        // 管理者権限チェック
        if (! $request->user() || $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 403);
        }

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'month' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        try {
            $topUsers = $this->costTrackingService->getTopUsers(
                $validated['limit'] ?? 10,
                $validated['month'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $validated['month'] ?? 'all_time',
                    'limit' => $validated['limit'] ?? 10,
                    'users' => $topUsers,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.top_users_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 使用状況レポート生成
     */
    public function generateReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'format' => ['sometimes', 'string', Rule::in(['json', 'csv'])],
            'include_patterns' => 'sometimes|boolean',
            'include_efficiency' => 'sometimes|boolean',
        ]);

        try {
            $userId = $request->user()->id;
            $reportType = $validated['report_type'];
            $format = $validated['format'] ?? 'json';

            $report = $this->generateUsageReport($userId, $reportType, [
                'include_patterns' => $validated['include_patterns'] ?? false,
                'include_efficiency' => $validated['include_efficiency'] ?? false,
            ]);

            if ($format === 'csv') {
                return $this->exportReportAsCsv($report, $reportType);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => $reportType,
                    'generated_at' => now()->toISOString(),
                    'report' => $report,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.report_generation_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    /**
     * 使用量履歴を取得（内部メソッド）
     */
    private function getUsageHistory(int $userId, int $months, string $groupBy): array
    {
        try {
            $startDate = now()->subMonths($months)->startOfMonth();
            $endDate = now();

            $dateFormat = match ($groupBy) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d'
            };

            $history = \DB::table('ai_cost_tracking')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->selectRaw("
                    DATE_FORMAT(created_at, '{$dateFormat}') as period,
                    COUNT(*) as requests,
                    SUM(estimated_cost) as cost,
                    AVG(api_response_time_ms) as avg_response_time
                ")
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            return $history->toArray();
        } catch (\Exception $e) {
            // Return empty array if table doesn't exist or query fails
            return [];
        }
    }

    /**
     * 使用状況レポート生成（内部メソッド）
     */
    private function generateUsageReport(int $userId, string $reportType, array $options): array
    {
        $months = match ($reportType) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 12,
            default => 1
        };

        $report = [
            'summary' => $this->costTrackingService->getMonthlyUsage($userId),
            'history' => $this->getUsageHistory($userId, $months, 'day'),
            'prediction' => $this->costTrackingService->predictMonthlyCost($userId),
        ];

        if ($options['include_patterns']) {
            $report['patterns'] = $this->costTrackingService->analyzeUsagePatterns($userId);
        }

        if ($options['include_efficiency']) {
            $report['efficiency'] = $this->costTrackingService->analyzeCostEfficiency($userId);
        }

        return $report;
    }

    /**
     * レポートをCSV形式でエクスポート
     */
    private function exportReportAsCsv(array $report, string $reportType): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $fileName = "ai_usage_report_{$reportType}_".now()->format('Y_m_d').'.csv';

        $csv = "Date,Requests,Cost,Avg Response Time\n";

        foreach ($report['history'] as $row) {
            $csv .= "{$row->period},{$row->requests},{$row->cost},{$row->avg_response_time}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
