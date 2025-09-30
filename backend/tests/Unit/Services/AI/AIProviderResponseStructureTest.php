<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\CompletionService;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\Services\AI\NormalizationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class AIProviderResponseStructureTest extends TestCase
{
    private $providerFactoryMock;

    private $costTrackerMock;

    private $aiProviderMock;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $minutes, $callback) {
                return $callback();
            });
        Cache::shouldReceive('has')->andReturn(false);
        Log::spy();

        $this->providerFactoryMock = Mockery::mock(AIProviderFactory::class);
        $this->costTrackerMock = Mockery::mock(CostTrackingService::class);
        $this->aiProviderMock = Mockery::mock(AIProviderInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_normalization_service_processes_open_ai_response_correctly(): void
    {
        // OpenAI APIの典型的なレスポンスをモック
        $mockOpenAIResponse = [
            'normalized_data' => [
                'normalized_brand' => 'CHANEL',
                'normalized_fragrance_name' => 'N°5',
                'concentration_type' => 'EDP',
                'launch_year' => 1921,
                'fragrance_family' => 'Floral',
                'confidence_score' => 0.95,
                'description_ja' => 'シャネル No.5は1921年に発売されたフローラル系の香水です。',
                'description_en' => 'Chanel No.5 is a floral fragrance launched in 1921.',
            ],
            'response_time_ms' => 1250.5,
            'provider' => 'openai',
            'cost_estimate' => 0.015,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with(null)
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->with('Chanel', 'No.5', ['language' => 'ja'])
            ->once()
            ->andReturn($mockOpenAIResponse);

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->normalize('Chanel', 'No.5', ['language' => 'ja']);

        // サービスがAIプロバイダーのレスポンスを正しく処理することを検証
        $this->assertArrayHasKey('normalized_data', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('openai', $result['provider']);
        $this->assertEquals(0.015, $result['cost_estimate']);

        // 正規化データの構造を検証
        $normalizedData = $result['normalized_data'];
        $this->assertEquals('CHANEL', $normalizedData['normalized_brand']);
        $this->assertEquals('N°5', $normalizedData['normalized_fragrance_name']);
        $this->assertEquals(0.95, $normalizedData['confidence_score']);
        $this->assertArrayHasKey('final_confidence_score', $normalizedData);

        // メタデータの構造を検証
        $metadata = $result['metadata'];
        $this->assertEquals('Chanel', $metadata['original_brand']);
        $this->assertEquals('No.5', $metadata['original_fragrance']);
        $this->assertEquals('ja', $metadata['language']);
        $this->assertEquals('openai', $metadata['provider']);
    }

    public function test_normalization_service_processes_anthropic_response_correctly(): void
    {
        // Anthropic APIの典型的なレスポンスをモック
        $mockAnthropicResponse = [
            'normalized_data' => [
                'normalized_brand' => 'Dior',
                'normalized_fragrance_name' => 'J\'adore',
                'concentration_type' => 'EDP',
                'launch_year' => 1999,
                'fragrance_family' => 'Floral',
                'confidence_score' => 0.92,
                'description_ja' => 'J\'adoreはDiorによって1999年に発売されたフローラル系の香水です。',
                'description_en' => 'J\'adore is a floral fragrance launched by Dior in 1999.',
            ],
            'response_time_ms' => 1800.2,
            'provider' => 'anthropic',
            'cost_estimate' => 0.008,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('anthropic');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->with('Dior', 'Jadore', ['language' => 'en'])
            ->once()
            ->andReturn($mockAnthropicResponse);

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->normalize('Dior', 'Jadore', ['provider' => 'anthropic', 'language' => 'en']);

        // Anthropicレスポンスの処理を検証
        $this->assertEquals('anthropic', $result['provider']);
        $this->assertEquals(0.008, $result['cost_estimate']);
        $this->assertEquals('Dior', $result['normalized_data']['normalized_brand']);
        $this->assertEquals('J\'adore', $result['normalized_data']['normalized_fragrance_name']);
    }

    public function test_completion_service_processes_correct_response_structure(): void
    {
        // Completion APIの典型的なレスポンスをモック
        $mockCompletionResponse = [
            'suggestions' => [
                [
                    'text' => 'シャネル',
                    'text_en' => 'CHANEL',
                    'confidence' => 0.95,
                    'type' => 'brand',
                    'brand_name' => null,
                    'brand_name_en' => null,
                ],
                [
                    'text' => 'チャンネル',
                    'text_en' => 'Channel',
                    'confidence' => 0.75,
                    'type' => 'brand',
                    'brand_name' => null,
                    'brand_name_en' => null,
                ],
            ],
            'response_time_ms' => 850.3,
            'provider' => 'openai',
            'cost_estimate' => 0.002,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with(null)
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->with('chan', [
                'type' => 'brand',
                'limit' => 5,
                'language' => 'ja',
                'contextBrand' => null,
            ])
            ->once()
            ->andReturn($mockCompletionResponse);

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->complete('chan', ['type' => 'brand', 'limit' => 5]);

        // CompletionServiceの処理を検証
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('cost_estimate', $result);
        $this->assertEquals('openai', $result['provider']);
        $this->assertEquals(0.002, $result['cost_estimate']);

        // 補完候補の構造を検証
        $suggestions = $result['suggestions'];
        $this->assertCount(2, $suggestions);

        foreach ($suggestions as $suggestion) {
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('confidence', $suggestion);
            $this->assertArrayHasKey('type', $suggestion);
            $this->assertEquals('brand', $suggestion['type']);
            $this->assertIsFloat($suggestion['confidence']);
            $this->assertGreaterThanOrEqual(0.0, $suggestion['confidence']);
            $this->assertLessThanOrEqual(1.0, $suggestion['confidence']);
        }
    }

    public function test_normalization_handles_invalid_response_structure(): void
    {
        // 不正な構造のレスポンスをモック
        $invalidResponse = [
            'invalid_field' => 'invalid_data',
            'response_time_ms' => 1000,
            'provider' => 'openai',
            // normalized_data が欠如
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->andReturn($invalidResponse);

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->normalize('Test', 'Brand');

        // 不正なレスポンスでも正常に処理されることを検証
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('Test', $result['metadata']['original_brand']);
        $this->assertEquals('Brand', $result['metadata']['original_fragrance']);
    }

    public function test_cost_tracking_with_correct_parameters(): void
    {
        $mockResponse = [
            'normalized_data' => [
                'normalized_brand' => 'TEST',
                'normalized_fragrance_name' => 'BRAND',
                'confidence_score' => 0.8,
            ],
            'response_time_ms' => 1500,
            'provider' => 'openai',
            'cost_estimate' => 0.025,
        ];

        $userId = 42;

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->andReturn($mockResponse);

        // コスト追跡の呼び出しを検証
        $this->costTrackerMock
            ->shouldReceive('trackUsage')
            ->with($userId, Mockery::on(function ($usage) {
                return $usage['provider'] === 'openai'
                    && $usage['operation_type'] === 'normalization'
                    && $usage['cost'] === 0.025
                    && $usage['response_time_ms'] === 1500
                    && isset($usage['metadata']);
            }))
            ->once();

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->normalize('Test', 'Brand', ['user_id' => $userId]);

        // アサーションを追加してテストが正常に動作することを確認
        $this->assertEquals('openai', $result['provider']);
    }
}
