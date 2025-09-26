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

class FallbackMechanismTest extends TestCase
{
    private $providerFactoryMock;

    private $costTrackerMock;

    private $aiProviderMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache と Log のモック
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

    public function test_normalization_fallback_on_api_exception(): void
    {
        // AIプロバイダーがAPI例外を投げる
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->andThrow(new \Exception('API request failed'));

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);

        $result = $service->normalize('Test Brand', 'Test Fragrance');

        // フォールバック処理の検証
        $this->assertEquals('fallback', $result['provider']);
        $this->assertEquals(0.0, $result['cost_estimate']);
        $this->assertEquals(10, $result['response_time_ms']);
        $this->assertArrayHasKey('normalized_data', $result);
        $this->assertEquals('Test Brand', $result['normalized_data']['normalized_brand']);
        $this->assertEquals('Test Fragrance', $result['normalized_data']['normalized_fragrance_name']);
        $this->assertEquals(0.6, $result['normalized_data']['confidence_score']);
        $this->assertEquals('AI provider unavailable', $result['normalized_data']['fallback_reason']);
    }

    public function test_completion_fallback_on_timeout_exception(): void
    {
        // AIプロバイダーがタイムアウト例外を投げる
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->andThrow(new \Exception('Request timeout'));

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);

        $result = $service->complete('chan', ['type' => 'brand']);

        // フォールバック処理の検証
        $this->assertEquals('fallback', $result['provider']);
        $this->assertEquals(0.0, $result['cost_estimate']);
        $this->assertEquals(0, $result['response_time_ms']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('AI provider unavailable, using fallback suggestions', $result['error']);
        $this->assertIsArray($result['suggestions']);
    }

    public function test_normalization_fallback_applies_correct_rules(): void
    {
        // ブランド正規化ルールのテスト
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->andThrow(new \Exception('API error'));

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);

        // 日本語ブランド名のテスト
        $result = $service->normalize('シャネル', 'No.5');
        $this->assertEquals('CHANEL', $result['normalized_data']['normalized_brand']);

        // 香水名の正規化ルールテスト
        $result = $service->normalize('Test', 'No 5');
        $this->assertEquals('No.5', $result['normalized_data']['normalized_fragrance_name']);

        // 商標記号の正規化テスト
        $result = $service->normalize('Test', 'Brand(tm)');
        $this->assertEquals('Brand™', $result['normalized_data']['normalized_fragrance_name']);
    }

    public function test_fallback_suggestions_for_completion(): void
    {
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->andThrow(new \Exception('API error'));

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);

        // ブランド補完のフォールバック
        $result = $service->complete('chan', ['type' => 'brand']);
        $suggestions = $result['suggestions'];

        $this->assertNotEmpty($suggestions);
        foreach ($suggestions as $suggestion) {
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('confidence', $suggestion);
            $this->assertArrayHasKey('type', $suggestion);
            $this->assertEquals('brand', $suggestion['type']);
            $this->assertStringContainsString('chan', strtolower($suggestion['text']));
        }

        // 香水補完のフォールバック
        $result = $service->complete('no', ['type' => 'fragrance']);
        $suggestions = $result['suggestions'];

        $this->assertNotEmpty($suggestions);
        foreach ($suggestions as $suggestion) {
            $this->assertEquals('fragrance', $suggestion['type']);
            $this->assertStringContainsString('no', strtolower($suggestion['text']));
        }
    }

    public function test_batch_operation_fallback(): void
    {
        // 一括処理で一部が失敗する場合のテスト
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->times(2)
            ->andReturn($this->aiProviderMock);

        // 1つ目は成功、2つ目は失敗
        $successResponse = [
            'normalized_data' => [
                'normalized_brand' => 'CHANEL',
                'normalized_fragrance_name' => 'No.5',
                'confidence_score' => 0.95,
            ],
            'provider' => 'openai',
            'response_time_ms' => 200,
            'cost_estimate' => 0.01,
        ];

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->with('Chanel', 'No.5', Mockery::any())
            ->once()
            ->andReturn($successResponse);

        $this->aiProviderMock
            ->shouldReceive('normalize')
            ->with('Invalid', 'Brand', Mockery::any())
            ->once()
            ->andThrow(new \Exception('API error'));

        $service = new NormalizationService($this->providerFactoryMock, $this->costTrackerMock);

        $fragrances = [
            ['brand_name' => 'Chanel', 'fragrance_name' => 'No.5'],
            ['brand_name' => 'Invalid', 'fragrance_name' => 'Brand'],
        ];

        $result = $service->batchNormalize($fragrances);

        $this->assertEquals(2, $result['total_processed']);
        $this->assertEquals(2, $result['successful_count']); // フォールバックも成功として扱う
        $this->assertCount(2, $result['results']);

        // 1つ目: API成功
        $this->assertEquals('openai', $result['results'][0]['provider']);

        // 2つ目: フォールバック
        $this->assertEquals('fallback', $result['results'][1]['provider']);
        $this->assertEquals('Invalid', $result['results'][1]['normalized_data']['normalized_brand']);
    }
}
