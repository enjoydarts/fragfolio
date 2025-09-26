<?php

namespace Tests\Feature\Api\AI;

use App\Models\User;
use App\Services\AI\CostTrackingService;
use Mockery;
use Tests\TestCase;

class CostControllerTest extends TestCase
{
    private $costTrackingServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // CostTrackingServiceのモック設定
        $this->costTrackingServiceMock = Mockery::mock(CostTrackingService::class);
        $this->app->instance(CostTrackingService::class, $this->costTrackingServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_usage_endpoint_returns_user_usage_data(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockUsageData = [
            'total_cost' => 5.25,
            'total_requests' => 100,
            'total_tokens' => 5000,
            'avg_response_time' => 250.5,
            'by_provider' => [
                'openai' => ['cost' => 3.00, 'requests' => 60],
                'anthropic' => ['cost' => 2.25, 'requests' => 40],
            ],
            'by_operation' => [
                'completion' => ['cost' => 2.50, 'requests' => 50],
                'normalization' => ['cost' => 1.75, 'requests' => 30],
                'note_suggestion' => ['cost' => 1.00, 'requests' => 20],
            ],
        ];

        $mockLimitCheck = [
            'can_proceed' => true,
            'limits' => [
                'daily' => ['current' => 0.25, 'limit' => 1.0, 'percentage' => 25, 'exceeded' => false],
                'monthly' => ['current' => 5.25, 'limit' => 10.0, 'percentage' => 52.5, 'exceeded' => false],
                'hourly_requests' => ['current' => 5, 'limit' => 100, 'percentage' => 5, 'exceeded' => false],
            ],
            'warnings' => [],
        ];

        $mockPrediction = [
            'current_cost' => 5.25,
            'daily_average' => 0.25,
            'predicted_total' => 7.50,
            'days_remaining' => 9,
            'projected_overage' => 0.0,
        ];

        $mockEfficiency = [
            'efficiency_score' => 85.2,
            'cost_per_request' => 0.0525,
            'avg_response_time' => 250.5,
            'insights' => ['Good cost efficiency'],
            'recommendations' => ['Continue current usage pattern'],
            'most_efficient_provider' => 'anthropic',
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('getMonthlyUsage')
            ->with($user->id, null)
            ->once()
            ->andReturn($mockUsageData);

        $this->costTrackingServiceMock
            ->shouldReceive('checkAllLimits')
            ->with($user->id)
            ->once()
            ->andReturn($mockLimitCheck);

        $this->costTrackingServiceMock
            ->shouldReceive('predictMonthlyCost')
            ->with($user->id)
            ->once()
            ->andReturn($mockPrediction);

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeCostEfficiency')
            ->with($user->id, null)
            ->once()
            ->andReturn($mockEfficiency);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/usage');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'month',
                    'usage' => [
                        'total_cost',
                        'total_requests',
                        'by_provider',
                        'by_operation',
                    ],
                    'limits' => [
                        'can_proceed',
                        'limits' => ['daily', 'monthly', 'hourly_requests'],
                    ],
                    'cost_prediction',
                    'efficiency',
                ],
            ])
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.usage.total_cost', 5.25)
            ->assertJsonPath('data.limits.can_proceed', true)
            ->assertJsonPath('data.efficiency.efficiency_score', 85.2);
    }

    public function test_usage_endpoint_with_patterns_included(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockPatterns = [
            'hourly_pattern' => [
                '9' => ['requests' => 10, 'avg_cost' => 0.05],
                '14' => ['requests' => 8, 'avg_cost' => 0.04],
            ],
            'weekly_pattern' => [
                '2' => ['requests' => 25, 'avg_cost' => 0.125], // Monday
                '6' => ['requests' => 15, 'avg_cost' => 0.075],  // Friday
            ],
            'peak_hour' => 9,
            'peak_day' => 2,
            'usage_insights' => ['Peak usage during business hours'],
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('getMonthlyUsage')
            ->andReturn(['total_cost' => 1.0]);

        $this->costTrackingServiceMock
            ->shouldReceive('checkAllLimits')
            ->andReturn(['can_proceed' => true, 'limits' => [], 'warnings' => []]);

        $this->costTrackingServiceMock
            ->shouldReceive('predictMonthlyCost')
            ->andReturn(['predicted_total' => 2.0]);

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeCostEfficiency')
            ->andReturn(['efficiency_score' => 80]);

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeUsagePatterns')
            ->with($user->id)
            ->once()
            ->andReturn($mockPatterns);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/usage?include_patterns=1');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.patterns.peak_hour', 9)
            ->assertJsonPath('data.patterns.peak_day', 2);
    }

    public function test_usage_endpoint_requires_authentication(): void
    {
        // Act
        $response = $this->getJson('/api/ai/cost/usage');

        // Assert
        $response->assertStatus(401);
    }

    public function test_limits_endpoint_returns_limit_information(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockLimitCheck = [
            'can_proceed' => false,
            'limits' => [
                'daily' => ['current' => 1.2, 'limit' => 1.0, 'percentage' => 120, 'exceeded' => true],
                'monthly' => ['current' => 8.5, 'limit' => 10.0, 'percentage' => 85, 'exceeded' => false],
                'hourly_requests' => ['current' => 95, 'limit' => 100, 'percentage' => 95, 'exceeded' => false],
            ],
            'warnings' => [
                [
                    'type' => 'daily',
                    'level' => 'critical',
                    'message' => 'Daily limit exceeded',
                ],
            ],
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('checkAllLimits')
            ->with($user->id)
            ->once()
            ->andReturn($mockLimitCheck);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/limits');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'can_proceed',
                    'limits',
                    'warnings',
                    'checked_at',
                ],
            ])
            ->assertJsonPath('data.can_proceed', false)
            ->assertJsonPath('data.limits.daily.exceeded', true)
            ->assertJsonPath('data.warnings.0.type', 'daily');
    }

    public function test_patterns_endpoint_returns_usage_patterns(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockPatterns = [
            'hourly_pattern' => [
                '9' => ['requests' => 12, 'avg_cost' => 0.06],
                '13' => ['requests' => 8, 'avg_cost' => 0.04],
                '17' => ['requests' => 15, 'avg_cost' => 0.075],
            ],
            'weekly_pattern' => [
                '2' => ['requests' => 30, 'avg_cost' => 0.15], // Monday
                '3' => ['requests' => 25, 'avg_cost' => 0.125], // Tuesday
                '6' => ['requests' => 10, 'avg_cost' => 0.05],   // Friday
            ],
            'peak_hour' => 17,
            'peak_day' => 2,
            'usage_insights' => [
                'High usage during evening hours',
                'Monday is your most active day',
            ],
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeUsagePatterns')
            ->with($user->id)
            ->once()
            ->andReturn($mockPatterns);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/patterns');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.peak_hour', 17)
            ->assertJsonPath('data.peak_day', 2)
            ->assertJsonPath('data.usage_insights.0', 'High usage during evening hours');
    }

    public function test_efficiency_endpoint_returns_efficiency_analysis(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockEfficiency = [
            'efficiency_score' => 72.5,
            'cost_per_request' => 0.0825,
            'avg_response_time' => 320.2,
            'insights' => [
                'Moderate cost per request: $0.0825',
                'Consider optimizing prompts to reduce token usage',
            ],
            'recommendations' => [
                'Try using more concise prompts',
                'Most cost-efficient provider: anthropic',
            ],
            'most_efficient_provider' => 'anthropic',
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeCostEfficiency')
            ->with($user->id, '2025-09')
            ->once()
            ->andReturn($mockEfficiency);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/efficiency?month=2025-09');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.efficiency_score', 72.5)
            ->assertJsonPath('data.most_efficient_provider', 'anthropic')
            ->assertJsonPath('data.insights.0', 'Moderate cost per request: $0.0825');
    }

    public function test_prediction_endpoint_returns_monthly_prediction(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockPrediction = [
            'current_cost' => 4.75,
            'daily_average' => 0.31,
            'predicted_total' => 9.45,
            'days_remaining' => 15,
            'projected_overage' => 0.0,
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('predictMonthlyCost')
            ->with($user->id)
            ->once()
            ->andReturn($mockPrediction);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/prediction');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.current_cost', 4.75)
            ->assertJsonPath('data.predicted_total', 9.45)
            ->assertJsonPath('data.projected_overage', 0);
    }

    public function test_history_endpoint_returns_usage_history(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/history?months=6&group_by=week');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['months', 'group_by', 'start_date', 'end_date'],
                    'history',
                ],
            ])
            ->assertJsonPath('data.period.months', '6')
            ->assertJsonPath('data.period.group_by', 'week');
    }

    public function test_global_stats_endpoint_requires_admin(): void
    {
        // Arrange
        $user = User::factory()->create(); // 通常ユーザー

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/global-stats');

        // Assert
        $response->assertStatus(403);
    }

    public function test_global_stats_endpoint_works_for_admin(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        $mockGlobalStats = [
            'summary' => [
                'total_requests' => 1500,
                'active_users' => 45,
                'total_cost' => 125.50,
                'avg_cost_per_request' => 0.0837,
                'avg_response_time' => 275.2,
            ],
            'by_provider' => [
                'openai' => ['requests' => 900, 'cost' => 75.30],
                'anthropic' => ['requests' => 600, 'cost' => 50.20],
            ],
            'daily_breakdown' => [
                ['date' => '2025-09-20', 'requests' => 150, 'cost' => 12.50],
                ['date' => '2025-09-21', 'requests' => 180, 'cost' => 15.25],
            ],
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('getGlobalStats')
            ->with(null, null)
            ->once()
            ->andReturn($mockGlobalStats);

        // Act
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ai/cost/global-stats');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_requests', 1500)
            ->assertJsonPath('data.summary.active_users', 45);
    }

    public function test_top_users_endpoint_requires_admin(): void
    {
        // Arrange
        $user = User::factory()->create(); // 通常ユーザー

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/cost/top-users');

        // Assert
        $response->assertStatus(403);
    }

    public function test_top_users_endpoint_works_for_admin(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        $mockTopUsers = [
            ['id' => 1, 'name' => 'Heavy User', 'email' => 'heavy@example.com', 'total_cost' => 25.50],
            ['id' => 2, 'name' => 'Medium User', 'email' => 'medium@example.com', 'total_cost' => 15.25],
            ['id' => 3, 'name' => 'Light User', 'email' => 'light@example.com', 'total_cost' => 8.75],
        ];

        $this->costTrackingServiceMock
            ->shouldReceive('getTopUsers')
            ->with(20, '2025-09')
            ->once()
            ->andReturn($mockTopUsers);

        // Act
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ai/cost/top-users?limit=20&month=2025-09');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.users.0.name', 'Heavy User')
            ->assertJsonPath('data.users.0.total_cost', 25.50)
            ->assertJsonPath('data.period', '2025-09')
            ->assertJsonPath('data.limit', '20');
    }

    public function test_generate_report_endpoint_returns_json_report(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Insert test data for ai_cost_tracking
        \DB::table('ai_cost_tracking')->insert([
            'user_id' => $user->id,
            'operation_type' => 'completion',
            'provider' => 'openai',
            'estimated_cost' => 0.05,
            'api_response_time_ms' => 200,
            'tokens_used' => 10,
            'created_at' => now(),
        ]);

        // Mock the required service methods
        $this->costTrackingServiceMock
            ->shouldReceive('getMonthlyUsage')
            ->with($user->id)
            ->once()
            ->andReturn(['total_cost' => 5.0, 'total_requests' => 10]);

        $this->costTrackingServiceMock
            ->shouldReceive('predictMonthlyCost')
            ->with($user->id)
            ->once()
            ->andReturn(['predicted_total' => 10.0]);

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeCostEfficiency')
            ->with($user->id)
            ->once()
            ->andReturn(['efficiency_score' => 85]);

        $this->costTrackingServiceMock
            ->shouldReceive('analyzeUsagePatterns')
            ->with($user->id)
            ->once()
            ->andReturn(['hourly_pattern' => [], 'weekly_pattern' => []]);

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/cost/report', [
                'report_type' => 'monthly',
                'format' => 'json',
                'include_patterns' => true,
                'include_efficiency' => true,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'report_type',
                    'generated_at',
                    'report',
                ],
            ])
            ->assertJsonPath('data.report_type', 'monthly');
    }

    public function test_generate_report_validates_report_type(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/cost/report', [
                'report_type' => 'invalid_type',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_type']);
    }
}
