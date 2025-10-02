<?php

namespace Tests\Unit\UseCases\AI;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\CompletionService;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\UseCases\AI\CompleteFragranceUseCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CompleteFragranceUseCaseTest extends TestCase
{
    private $completionServiceMock;

    private $providerFactoryMock;

    private $costTrackerMock;

    private $aiProviderMock;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::spy();
        Log::spy();

        $this->completionServiceMock = Mockery::mock(CompletionService::class);
        $this->providerFactoryMock = Mockery::mock(AIProviderFactory::class);
        $this->costTrackerMock = Mockery::mock(CostTrackingService::class);
        $this->aiProviderMock = Mockery::mock(AIProviderInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_separated_brand_fragrance_format(): void
    {
        // 分離されたブランド/香水形式のレスポンスをモック
        $mockResponse = [
            'suggestions' => [
                [
                    'text' => 'ソヴァージュ EDT',
                    'text_en' => 'Sauvage EDT',
                    'brand_name' => 'ディオール',
                    'brand_name_en' => 'Dior',
                    'confidence' => 0.95,
                    'type' => 'fragrance',
                    'source' => 'gemini',
                ],
                [
                    'text' => 'ソヴァージュ パルファム',
                    'text_en' => 'Sauvage Parfum',
                    'brand_name' => 'ディオール',
                    'brand_name_en' => 'Dior',
                    'confidence' => 0.93,
                    'type' => 'fragrance',
                    'source' => 'gemini',
                ],
            ],
            'response_time_ms' => 1200.5,
            'provider' => 'gemini',
            'cost_estimate' => 0.008,
        ];

        Cache::shouldReceive('get')->with(Mockery::any())->andReturn(null);
        Cache::shouldReceive('put')->with(Mockery::any(), Mockery::any(), Mockery::any())->andReturn(true);

        $this->completionServiceMock
            ->shouldReceive('complete')
            ->with('ソヴァージュ', [
                'type' => 'fragrance',
            ])
            ->once()
            ->andReturn($mockResponse);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $result = $useCase->execute('ソヴァージュ', ['type' => 'fragrance']);

        // 基本的なレスポンス構造を検証
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertCount(2, $result['suggestions']);

        // 各提案が分離形式を持つことを検証
        foreach ($result['suggestions'] as $suggestion) {
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('text_en', $suggestion);
            $this->assertArrayHasKey('brand_name', $suggestion);
            $this->assertArrayHasKey('brand_name_en', $suggestion);
            $this->assertArrayHasKey('confidence', $suggestion);

            // 分離が正しく行われていることを検証
            $this->assertStringNotContainsString(
                $suggestion['brand_name'],
                $suggestion['text'],
                'Fragrance name should not contain brand name'
            );
        }
    }

    public function test_execute_uses_cache_correctly(): void
    {
        $cacheKey = 'ai_completion:'.md5('test_query').':fragrance:ja:default:10:none';
        $cachedResult = [
            'suggestions' => [
                [
                    'text' => 'キャッシュされた香水',
                    'text_en' => 'Cached Fragrance',
                    'brand_name' => 'キャッシュブランド',
                    'brand_name_en' => 'Cache Brand',
                    'confidence' => 0.85,
                    'type' => 'fragrance',
                ],
            ],
            'cached' => true,
        ];

        // キャッシュヒットをモック
        Cache::shouldReceive('get')->with(Mockery::pattern('/ai_completion:.*/'))->andReturn($cachedResult);

        // CompletionServiceが呼び出されないことを確認
        $this->completionServiceMock->shouldNotReceive('complete');

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $result = $useCase->execute('test_query');

        // キャッシュされた結果が返されることを確認
        $this->assertEquals($cachedResult, $result);
        Log::shouldHaveReceived('info')
            ->with('AI completion cache hit', Mockery::any())
            ->once();
    }

    public function test_execute_with_user_limits_validation(): void
    {
        $userId = 123;

        // 制限チェックのモック
        $this->costTrackerMock
            ->shouldReceive('checkDailyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkMonthlyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkRateLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->andReturn(true);

        $mockResponse = [
            'suggestions' => [
                [
                    'text' => 'テスト香水',
                    'text_en' => 'Test Fragrance',
                    'brand_name' => 'テストブランド',
                    'brand_name_en' => 'Test Brand',
                    'confidence' => 0.8,
                    'type' => 'fragrance',
                ],
            ],
            'provider' => 'openai',
        ];

        $this->completionServiceMock
            ->shouldReceive('complete')
            ->andReturn($mockResponse);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $result = $useCase->execute('test', ['user_id' => $userId]);

        // 正常に実行されることを確認
        $this->assertArrayHasKey('suggestions', $result);
    }

    public function test_execute_throws_exception_when_daily_limit_exceeded(): void
    {
        $userId = 123;

        $this->costTrackerMock
            ->shouldReceive('checkDailyLimit')
            ->with($userId)
            ->once()
            ->andReturn(false);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Daily AI usage limit exceeded');

        $useCase->execute('test', ['user_id' => $userId]);
    }

    public function test_execute_throws_exception_when_monthly_limit_exceeded(): void
    {
        $userId = 123;

        $this->costTrackerMock
            ->shouldReceive('checkDailyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkMonthlyLimit')
            ->with($userId)
            ->once()
            ->andReturn(false);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Monthly AI usage limit exceeded');

        $useCase->execute('test', ['user_id' => $userId]);
    }

    public function test_execute_throws_exception_when_rate_limit_exceeded(): void
    {
        $userId = 123;

        $this->costTrackerMock
            ->shouldReceive('checkDailyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkMonthlyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkRateLimit')
            ->with($userId)
            ->once()
            ->andReturn(false);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded. Please wait before making another request');

        $useCase->execute('test', ['user_id' => $userId]);
    }

    public function test_execute_batch_processes_multiple_queries(): void
    {
        $queries = ['香水1', '香水2', '香水3'];
        $userId = 456;

        $this->costTrackerMock
            ->shouldReceive('checkDailyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkMonthlyLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->costTrackerMock
            ->shouldReceive('checkRateLimit')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $mockBatchResponse = [
            'results' => [
                [
                    'suggestions' => [
                        [
                            'text' => '香水1結果',
                            'text_en' => 'Fragrance1 Result',
                            'brand_name' => 'ブランド1',
                            'brand_name_en' => 'Brand1',
                            'confidence' => 0.9,
                            'type' => 'fragrance',
                        ],
                    ],
                ],
                [
                    'suggestions' => [
                        [
                            'text' => '香水2結果',
                            'text_en' => 'Fragrance2 Result',
                            'brand_name' => 'ブランド2',
                            'brand_name_en' => 'Brand2',
                            'confidence' => 0.85,
                            'type' => 'fragrance',
                        ],
                    ],
                ],
            ],
            'total_cost_estimate' => 0.02,
        ];

        $this->completionServiceMock
            ->shouldReceive('batchComplete')
            ->with($queries, ['user_id' => $userId])
            ->once()
            ->andReturn($mockBatchResponse);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $result = $useCase->executeBatch($queries, ['user_id' => $userId]);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total_cost_estimate', $result);
        $this->assertEquals(0.02, $result['total_cost_estimate']);

        // ログが記録されることを確認
        Log::shouldHaveReceived('info')
            ->with('Batch completion executed', Mockery::any())
            ->once();
    }

    public function test_get_available_providers_returns_correct_structure(): void
    {
        $providers = ['openai', 'anthropic', 'gemini'];
        $defaultProvider = 'gemini';

        $this->providerFactoryMock
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn($providers);

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->once()
            ->andReturn($defaultProvider);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $result = $useCase->getAvailableProviders();

        $this->assertArrayHasKey('providers', $result);
        $this->assertArrayHasKey('default', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertEquals($providers, $result['providers']);
        $this->assertEquals($defaultProvider, $result['default']);
        $this->assertEquals(3, $result['total']);
    }

    public function test_health_check_validates_all_providers(): void
    {
        $providers = ['openai', 'anthropic', 'gemini'];

        $this->providerFactoryMock
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn($providers);

        // 各プロバイダーのモック
        foreach ($providers as $providerName) {
            $providerMock = Mockery::mock(AIProviderInterface::class);

            $this->providerFactoryMock
                ->shouldReceive('create')
                ->with($providerName)
                ->once()
                ->andReturn($providerMock);

            $providerMock
                ->shouldReceive('complete')
                ->with('test', ['limit' => 1])
                ->once()
                ->andReturn(['response_time_ms' => 500]);
        }

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $result = $useCase->healthCheck();

        $this->assertArrayHasKey('providers', $result);
        $this->assertArrayHasKey('overall_status', $result);

        // 各プロバイダーの健康状態を確認
        foreach ($providers as $provider) {
            $this->assertArrayHasKey($provider, $result['providers']);
            $this->assertEquals('healthy', $result['providers'][$provider]['status']);
        }

        $this->assertEquals('healthy', $result['overall_status']);
    }

    public function test_empty_query_throws_exception(): void
    {
        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query cannot be empty');

        $useCase->execute('');
    }

    public function test_unavailable_provider_throws_exception(): void
    {
        $unavailableProvider = 'unavailable_provider';

        $this->providerFactoryMock
            ->shouldReceive('isProviderAvailable')
            ->with($unavailableProvider)
            ->once()
            ->andReturn(false);

        Cache::shouldReceive('get')->andReturn(null);

        $useCase = new CompleteFragranceUseCase(
            $this->completionServiceMock,
            $this->providerFactoryMock,
            $this->costTrackerMock
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Provider {$unavailableProvider} is not available");

        $useCase->execute('test', ['provider' => $unavailableProvider]);
    }
}
