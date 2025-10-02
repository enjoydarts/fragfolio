<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\CompletionService;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CompletionServiceTest extends TestCase
{
    private CompletionService $completionService;

    private $providerFactoryMock;

    private $costTrackerMock;

    private $aiProviderMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock facades
        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $minutes, $callback) {
                return $callback();
            });
        Cache::shouldReceive('has')->andReturn(false);
        Log::spy();

        $this->providerFactoryMock = Mockery::mock(AIProviderFactory::class);
        $this->costTrackerMock = Mockery::mock(CostTrackingService::class);
        $this->aiProviderMock = Mockery::mock(AIProviderInterface::class);

        $this->completionService = new CompletionService(
            $this->providerFactoryMock,
            $this->costTrackerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complete_with_valid_query(): void
    {
        // Arrange
        $query = 'シャネル';
        $options = ['type' => 'brand', 'limit' => 5];
        $expectedResult = [
            'suggestions' => [
                [
                    'text' => 'シャネル',
                    'text_en' => 'CHANEL',
                    'confidence' => 0.95,
                    'type' => 'brand',
                    'brand_name' => null,
                    'brand_name_en' => null,
                ],
            ],
            'response_time_ms' => 250,
            'provider' => 'openai',
            'cost_estimate' => 0.001,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        // Fallback mechanism tries anthropic first
        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->with($query, [
                'type' => 'brand',
                'limit' => 5,
                'language' => 'ja',
                'contextBrand' => null,
            ])
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->completionService->complete($query, $options);

        // Assert
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('response_time_ms', $result);
        $this->assertArrayHasKey('cost_estimate', $result);
        $this->assertNotEmpty($result['suggestions']);
    }

    public function test_complete_with_short_query(): void
    {
        // Arrange
        $query = 'a';
        $options = ['type' => 'brand'];

        // Act
        $result = $this->completionService->complete($query, $options);

        // Assert
        $this->assertEmpty($result['suggestions']);
        $this->assertEquals(0, $result['response_time_ms']);
        $this->assertNull($result['provider']);
        $this->assertEquals('Query must be at least 2 characters long', $result['message']);
    }

    public function test_complete_with_user_id_tracks_usage(): void
    {
        // Arrange
        $query = 'テスト';
        $userId = 123;
        $options = ['type' => 'brand', 'user_id' => $userId];
        $mockResult = [
            'suggestions' => [
                [
                    'text' => 'テストブランド',
                    'text_en' => 'Test Brand',
                    'confidence' => 0.9,
                    'type' => 'brand',
                ],
            ],
            'response_time_ms' => 200,
            'provider' => 'openai',
            'cost_estimate' => 0.002,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        // Fallback mechanism tries anthropic first
        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn($mockResult);

        $this->costTrackerMock
            ->shouldReceive('trackUsage')
            ->with($userId, Mockery::on(function ($usage) {
                return $usage['provider'] === 'openai'
                    && $usage['operation_type'] === 'completion'
                    && $usage['cost'] === 0.002;
            }))
            ->once();

        // Act
        $this->completionService->complete($query, $options);

        // Assert - Mockeryの期待値で検証済み
        $this->assertTrue(true);
    }

    public function test_complete_handles_provider_exception(): void
    {
        // Arrange
        $query = 'テスト';
        $options = ['type' => 'brand'];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->andThrow(new \Exception('API error'));

        // Act
        $result = $this->completionService->complete($query, $options);

        // Assert
        $this->assertEquals('fallback', $result['provider']);
        $this->assertArrayHasKey('error', $result);
        $this->assertIsArray($result['suggestions']); // フォールバック候補は配列（空の可能性あり）
    }

    public function test_batch_complete(): void
    {
        // Arrange
        $queries = ['シャネル', 'ディオール'];
        $options = ['type' => 'brand'];
        $mockResult = [
            'suggestions' => [],
            'response_time_ms' => 100,
            'provider' => 'openai',
            'cost_estimate' => 0.001,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        // Fallback mechanism tries anthropic first for each query
        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->twice() // Once for each query
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->twice()
            ->andReturn($mockResult);

        // Act
        $result = $this->completionService->batchComplete($queries, $options);

        // Assert
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total_queries', $result);
        $this->assertArrayHasKey('total_cost_estimate', $result);
        $this->assertEquals(2, $result['total_queries']);
        $this->assertCount(2, $result['results']);
    }

    public function test_calculate_similarity(): void
    {
        // プライベートメソッドのテストのためリフレクションを使用
        $reflection = new \ReflectionClass($this->completionService);
        $method = $reflection->getMethod('calculateSimilarity');
        $method->setAccessible(true);

        // Test cases
        $testCases = [
            ['test', 'test', 1.0],
            ['test', 'Test', 0.75], // 大文字小文字の違い
            ['chanel', 'channel', 0.83], // 類似した文字列
            ['', 'test', 0.0], // 空文字列
            ['abc', 'def', 0.0], // 完全に異なる文字列
        ];

        foreach ($testCases as [$str1, $str2, $expected]) {
            $result = $method->invoke($this->completionService, $str1, $str2);
            $this->assertEqualsWithDelta($expected, $result, 0.1,
                "Similarity between '{$str1}' and '{$str2}' should be around {$expected}");
        }
    }

    public function test_generate_fallback_suggestions(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->completionService);
        $method = $reflection->getMethod('generateFallbackSuggestions');
        $method->setAccessible(true);

        // Test brand fallback
        $result = $method->invoke($this->completionService, 'chan', 'brand');
        $this->assertNotEmpty($result);
        $this->assertEquals('fallback', $result[0]['metadata']['source']);

        // Test fragrance fallback
        $result = $method->invoke($this->completionService, 'No', 'fragrance');
        $this->assertNotEmpty($result);
        $this->assertEquals('fragrance', $result[0]['type']);
    }

    public function test_process_suggestions_adds_scores(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->completionService);
        $method = $reflection->getMethod('processSuggestions');
        $method->setAccessible(true);

        $suggestions = [
            ['text' => 'CHANEL', 'confidence' => 0.9],
            ['text' => 'Channel', 'confidence' => 0.7],
        ];

        $result = $method->invoke($this->completionService, $suggestions, 'chan');

        foreach ($result as $suggestion) {
            $this->assertArrayHasKey('similarity_score', $suggestion);
            $this->assertArrayHasKey('adjusted_confidence', $suggestion);
            $this->assertIsFloat($suggestion['similarity_score']);
            $this->assertIsFloat($suggestion['adjusted_confidence']);
        }
    }
}
