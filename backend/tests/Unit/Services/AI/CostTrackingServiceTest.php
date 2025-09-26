<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\CostTrackingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CostTrackingServiceTest extends TestCase
{
    private CostTrackingService $costTrackingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->costTrackingService = new CostTrackingService;

        // Cache::spy() and Log::spy() for monitoring calls
        Cache::spy();
        Log::spy();
    }

    private function createTestUser(int $userId = 1): void
    {
        DB::table('users')->insert([
            'id' => $userId,
            'name' => "Test User {$userId}",
            'email' => "test{$userId}@example.com",
            'password' => bcrypt('password'),
            'role' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_track_usage_records_data(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $usage = [
            'provider' => 'openai',
            'operation_type' => 'completion',
            'tokens_used' => 100,
            'cost' => 0.002,
            'response_time_ms' => 250,
        ];

        // Act
        $this->costTrackingService->trackUsage($userId, $usage);

        // Assert
        $this->assertDatabaseHas('ai_cost_tracking', [
            'user_id' => $userId,
            'provider' => 'openai',
            'operation_type' => 'completion',
            'tokens_used' => 100,
            'estimated_cost' => 0.002,
            'api_response_time_ms' => 250,
        ]);
    }

    public function test_track_usage_handles_exception(): void
    {
        // Arrange - 不正なデータでDB例外を発生させる
        // 非常に大きな数値や無効なデータ型を使って例外を誘発
        $userId = 1;
        $usage = [
            'provider' => str_repeat('a', 300), // 長すぎる文字列でVARCHAR制限を超える
            'operation_type' => 'test',
        ];

        // Act
        $this->costTrackingService->trackUsage($userId, $usage);

        // Assert - ログが記録されることを確認
        Log::shouldHaveReceived('error')
            ->with('Failed to track AI usage', \Mockery::any())
            ->once();
    }

    public function test_get_monthly_usage_returns_correct_data(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $currentMonth = now()->format('Y-m');

        // テストデータを挿入
        DB::table('ai_cost_tracking')->insert([
            [
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'tokens_used' => 100,
                'estimated_cost' => 0.001,
                'api_response_time_ms' => 200,
                'created_at' => now(),
            ],
            [
                'user_id' => $userId,
                'provider' => 'anthropic',
                'operation_type' => 'normalization',
                'tokens_used' => 150,
                'estimated_cost' => 0.003,
                'api_response_time_ms' => 300,
                'created_at' => now(),
            ],
        ]);

        // Act
        $result = $this->costTrackingService->getMonthlyUsage($userId, $currentMonth);

        // Assert
        $this->assertEquals(0.004, $result['total_cost']);
        $this->assertEquals(2, $result['total_requests']);
        $this->assertEquals(250, $result['total_tokens']);
        $this->assertArrayHasKey('by_provider', $result);
        $this->assertArrayHasKey('by_operation', $result);
    }

    public function test_check_daily_limit_returns_true_when_under_limit(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $dailyLimit = 1.0;

        // 今日の使用量を少なめに設定
        DB::table('ai_cost_tracking')->insert([
            'user_id' => $userId,
            'provider' => 'openai',
            'operation_type' => 'completion',
            'estimated_cost' => 0.5,
            'created_at' => now(),
        ]);

        // Act
        $result = $this->costTrackingService->checkDailyLimit($userId, $dailyLimit);

        // Assert
        $this->assertTrue($result);
    }

    public function test_check_daily_limit_returns_false_when_over_limit(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $dailyLimit = 0.5;

        // 今日の使用量を制限を超えるよう設定
        DB::table('ai_cost_tracking')->insert([
            'user_id' => $userId,
            'provider' => 'openai',
            'operation_type' => 'completion',
            'estimated_cost' => 0.6,
            'created_at' => now(),
        ]);

        // Act
        $result = $this->costTrackingService->checkDailyLimit($userId, $dailyLimit);

        // Assert
        $this->assertFalse($result);
    }

    public function test_check_monthly_limit_works_correctly(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $monthlyLimit = 2.0;

        // 月初めのデータを挿入
        DB::table('ai_cost_tracking')->insert([
            'user_id' => $userId,
            'provider' => 'openai',
            'operation_type' => 'completion',
            'estimated_cost' => 1.5,
            'created_at' => now()->startOfMonth(),
        ]);

        // Act
        $result = $this->costTrackingService->checkMonthlyLimit($userId, $monthlyLimit);

        // Assert
        $this->assertTrue($result); // 1.5 < 2.0
    }

    public function test_check_rate_limit_works_correctly(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $hourlyLimit = 5;

        // 過去1時間のリクエストを3件作成
        for ($i = 0; $i < 3; $i++) {
            DB::table('ai_cost_tracking')->insert([
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => 0.001,
                'created_at' => now()->subMinutes(30),
            ]);
        }

        // Act
        $result = $this->costTrackingService->checkRateLimit($userId, $hourlyLimit);

        // Assert
        $this->assertTrue($result); // 3 < 5
    }

    public function test_check_all_limits_returns_comprehensive_results(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);

        // テストデータ作成
        DB::table('ai_cost_tracking')->insert([
            'user_id' => $userId,
            'provider' => 'openai',
            'operation_type' => 'completion',
            'estimated_cost' => 0.5, // 日次制限の50%
            'created_at' => now(),
        ]);

        // Act
        $result = $this->costTrackingService->checkAllLimits($userId, [
            'daily_limit' => 1.0,
            'monthly_limit' => 10.0,
            'hourly_requests_limit' => 100,
        ]);

        // Assert
        $this->assertArrayHasKey('can_proceed', $result);
        $this->assertArrayHasKey('limits', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertTrue($result['can_proceed']);

        // 制限の詳細確認
        $this->assertArrayHasKey('daily', $result['limits']);
        $this->assertArrayHasKey('monthly', $result['limits']);
        $this->assertArrayHasKey('hourly_requests', $result['limits']);

        // 50%使用なので警告は出ないはず
        $this->assertEmpty($result['warnings']);
    }

    public function test_check_all_limits_generates_warnings(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);

        // 日次制限の90%使用（警告レベル）
        DB::table('ai_cost_tracking')->insert([
            'user_id' => $userId,
            'provider' => 'openai',
            'operation_type' => 'completion',
            'estimated_cost' => 0.9,
            'created_at' => now(),
        ]);

        // Act
        $result = $this->costTrackingService->checkAllLimits($userId, [
            'daily_limit' => 1.0,
        ]);

        // Assert
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('daily', $result['warnings'][0]['type']);
        $this->assertEquals('warning', $result['warnings'][0]['level']);
    }

    public function test_analyze_cost_efficiency_with_no_data(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);

        // Act
        $result = $this->costTrackingService->analyzeCostEfficiency($userId);

        // Assert
        $this->assertEquals(0, $result['efficiency_score']);
        $this->assertContains('No usage data available for analysis', $result['insights']);
        $this->assertContains('Start using AI features to get efficiency insights', $result['recommendations']);
    }

    public function test_analyze_cost_efficiency_with_data(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $currentMonth = now()->format('Y-m');

        // 効率的な使用パターンのデータを作成
        DB::table('ai_cost_tracking')->insert([
            [
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => 0.005, // 高効率
                'api_response_time_ms' => 150, // 高速
                'created_at' => now(),
            ],
            [
                'user_id' => $userId,
                'provider' => 'anthropic',
                'operation_type' => 'normalization',
                'estimated_cost' => 0.003,
                'api_response_time_ms' => 200,
                'created_at' => now(),
            ],
        ]);

        // Act
        $result = $this->costTrackingService->analyzeCostEfficiency($userId, $currentMonth);

        // Assert
        $this->assertGreaterThan(0, $result['efficiency_score']);
        $this->assertArrayHasKey('cost_per_request', $result);
        $this->assertArrayHasKey('insights', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('most_efficient_provider', $result);
    }

    public function test_predict_monthly_cost_calculates_correctly(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);

        // 過去のデータパターンを作成（日平均0.1ドル）
        for ($i = 1; $i <= 5; $i++) {
            DB::table('ai_cost_tracking')->insert([
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => 0.1,
                'created_at' => now()->subDays($i),
            ]);
        }

        // Act
        $result = $this->costTrackingService->predictMonthlyCost($userId);

        // Assert
        $this->assertArrayHasKey('current_cost', $result);
        $this->assertArrayHasKey('daily_average', $result);
        $this->assertArrayHasKey('predicted_total', $result);
        $this->assertArrayHasKey('days_remaining', $result);
        $this->assertArrayHasKey('projected_overage', $result);
    }

    public function test_analyze_usage_patterns_returns_pattern_data(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);

        // 時間別・曜日別のテストデータを作成
        DB::table('ai_cost_tracking')->insert([
            [
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => 0.001,
                'created_at' => now()->setHour(9), // 午前9時
            ],
            [
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => 0.001,
                'created_at' => now()->setHour(14), // 午後2時
            ],
        ]);

        // Act
        $result = $this->costTrackingService->analyzeUsagePatterns($userId);

        // Assert
        $this->assertArrayHasKey('hourly_pattern', $result);
        $this->assertArrayHasKey('weekly_pattern', $result);
        $this->assertArrayHasKey('peak_hour', $result);
        $this->assertArrayHasKey('peak_day', $result);
        $this->assertArrayHasKey('usage_insights', $result);
    }

    public function test_get_global_stats_returns_aggregated_data(): void
    {
        // Arrange - 複数ユーザーのデータを作成
        $users = [1, 2, 3];
        foreach ($users as $userId) {
            $this->createTestUser($userId);
            DB::table('ai_cost_tracking')->insert([
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => 0.01 * $userId,
                'api_response_time_ms' => 200,
                'created_at' => now(),
            ]);
        }

        // Act
        $result = $this->costTrackingService->getGlobalStats();

        // Assert
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('by_provider', $result);
        $this->assertArrayHasKey('daily_breakdown', $result);

        $this->assertEquals(3, $result['summary']->total_requests);
        $this->assertEquals(3, $result['summary']->active_users);
    }

    public function test_get_top_users_returns_ranked_list(): void
    {
        // Arrange - 異なるコストのユーザーを作成
        $userCosts = [
            1 => 0.10, // 最高コスト
            2 => 0.05,
            3 => 0.02, // 最低コスト
        ];

        foreach ($userCosts as $userId => $cost) {
            DB::table('users')->insert([
                'id' => $userId,
                'name' => "User {$userId}",
                'email' => "user{$userId}@example.com",
                'password' => bcrypt('password'),
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_cost_tracking')->insert([
                'user_id' => $userId,
                'provider' => 'openai',
                'operation_type' => 'completion',
                'estimated_cost' => $cost,
                'created_at' => now(),
            ]);
        }

        // Act
        $result = $this->costTrackingService->getTopUsers(3);

        // Assert
        $this->assertCount(3, $result);
        // 最高コストのユーザーが最初に来ることを確認
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(0.10, $result[0]['total_cost']);
    }

    public function test_process_alerts_creates_cache(): void
    {
        // Arrange
        $userId = 1;
        $this->createTestUser($userId);
        $limitResults = [
            'warnings' => [
                [
                    'type' => 'daily',
                    'level' => 'warning',
                    'message' => 'Test warning message',
                ],
            ],
            'limits' => [
                'daily' => [
                    'current' => 0.8,
                    'limit' => 1.0,
                    'percentage' => 80,
                ],
            ],
        ];

        // Act
        $reflection = new \ReflectionClass($this->costTrackingService);
        $method = $reflection->getMethod('processAlerts');
        $method->setAccessible(true);
        $method->invoke($this->costTrackingService, $userId, $limitResults);

        // Assert
        Cache::shouldHaveReceived('has')->once();
        Cache::shouldHaveReceived('put')->once();
        Log::shouldHaveReceived('warning')
            ->with('AI Cost Alert', \Mockery::any())
            ->once();
    }
}
