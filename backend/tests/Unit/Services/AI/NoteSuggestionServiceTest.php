<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\Services\AI\NoteSuggestionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class NoteSuggestionServiceTest extends TestCase
{
    private NoteSuggestionService $noteSuggestionService;

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

        $this->noteSuggestionService = new NoteSuggestionService(
            $this->providerFactoryMock,
            $this->costTrackerMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_suggest_notes_with_valid_input(): void
    {
        // Arrange
        $brandName = 'CHANEL';
        $fragranceName = 'No.5';
        $options = ['language' => 'ja'];

        $mockResponse = [
            'notes' => [
                'top' => [
                    ['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.9],
                ],
                'middle' => [
                    ['name' => 'rose', 'intensity' => 'strong', 'confidence' => 0.95],
                ],
                'base' => [
                    ['name' => 'sandalwood', 'intensity' => 'moderate', 'confidence' => 0.8],
                ],
            ],
            'attributes' => [
                'seasons' => ['spring', 'summer'],
                'occasions' => ['casual', 'business'],
                'time_of_day' => ['morning', 'afternoon'],
                'intensity_rating' => 'moderate',
                'longevity_hours' => 6,
                'sillage' => 'moderate',
            ],
            'response_time_ms' => 250,
            'provider' => 'openai',
            'cost_estimate' => 0.003,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('openai')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->with($brandName, $fragranceName, array_merge($options, ['language' => 'ja']))
            ->once()
            ->andReturn($mockResponse);

        // Act
        $result = $this->noteSuggestionService->suggestNotes($brandName, $fragranceName, $options);

        // Assert
        $this->assertArrayHasKey('notes', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('openai', $result['provider']);

        // ノート構造の検証
        $this->assertArrayHasKey('top', $result['notes']);
        $this->assertArrayHasKey('middle', $result['notes']);
        $this->assertArrayHasKey('base', $result['notes']);

        // 各ノートの構造検証
        foreach (['top', 'middle', 'base'] as $category) {
            foreach ($result['notes'][$category] as $note) {
                $this->assertArrayHasKey('name', $note);
                $this->assertArrayHasKey('intensity', $note);
                $this->assertArrayHasKey('confidence', $note);
                $this->assertArrayHasKey('category', $note);
            }
        }
    }

    public function test_suggest_notes_with_short_brand_name(): void
    {
        // Arrange & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Brand name and fragrance name must be at least 2 characters long');

        // Act
        $this->noteSuggestionService->suggestNotes('A', 'TestFragrance');
    }

    public function test_suggest_notes_with_short_fragrance_name(): void
    {
        // Arrange & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Brand name and fragrance name must be at least 2 characters long');

        // Act
        $this->noteSuggestionService->suggestNotes('TestBrand', 'A');
    }

    public function test_suggest_notes_with_user_id_tracks_usage(): void
    {
        // Arrange
        $brandName = 'Dior';
        $fragranceName = 'J\'adore';
        $userId = 123;
        $options = ['user_id' => $userId, 'language' => 'en'];

        $mockResponse = [
            'notes' => [
                'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.8]],
                'middle' => [['name' => 'jasmine', 'intensity' => 'strong', 'confidence' => 0.9]],
                'base' => [['name' => 'musk', 'intensity' => 'moderate', 'confidence' => 0.7]],
            ],
            'attributes' => [
                'seasons' => ['spring'],
                'occasions' => ['formal'],
                'time_of_day' => ['evening'],
                'intensity_rating' => 'strong',
                'longevity_hours' => 8,
                'sillage' => 'heavy',
            ],
            'response_time_ms' => 300,
            'provider' => 'anthropic',
            'cost_estimate' => 0.005,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('anthropic');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->andReturn($mockResponse);

        $this->costTrackerMock
            ->shouldReceive('trackUsage')
            ->with($userId, Mockery::on(function ($usage) {
                return $usage['operation_type'] === 'note_suggestion'
                    && $usage['provider'] === 'anthropic'
                    && $usage['cost'] === 0.005
                    && $usage['response_time_ms'] === 300
                    && isset($usage['metadata']);
            }))
            ->once();

        // Act
        $result = $this->noteSuggestionService->suggestNotes($brandName, $fragranceName, $options);

        // Assert - Mockeryの期待値で検証済み
        $this->assertEquals('anthropic', $result['provider']);
    }

    public function test_suggest_notes_handles_provider_exception(): void
    {
        // Arrange
        $brandName = 'TestBrand';
        $fragranceName = 'TestFragrance';

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('openai')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->andThrow(new \Exception('AI provider error'));

        // Act
        $result = $this->noteSuggestionService->suggestNotes($brandName, $fragranceName);

        // Assert - フォールバック結果が返される
        $this->assertEquals('fallback', $result['provider']);
        $this->assertEquals(0.0, $result['cost_estimate']);
        $this->assertArrayHasKey('notes', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertEquals(0.5, $result['confidence_score']);
        $this->assertEquals('AI provider unavailable', $result['metadata']['fallback_reason']);
    }

    public function test_batch_suggest_notes(): void
    {
        // Arrange
        $fragrances = [
            ['brand_name' => 'CHANEL', 'fragrance_name' => 'No.5'],
            ['brand_name' => 'Dior', 'fragrance_name' => 'J\'adore'],
        ];
        $options = ['language' => 'ja'];

        $mockResponse = [
            'notes' => [
                'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.8]],
                'middle' => [['name' => 'rose', 'intensity' => 'strong', 'confidence' => 0.9]],
                'base' => [['name' => 'sandalwood', 'intensity' => 'moderate', 'confidence' => 0.7]],
            ],
            'attributes' => [],
            'response_time_ms' => 200,
            'provider' => 'openai',
            'cost_estimate' => 0.002,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('openai')
            ->twice()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->twice()
            ->andReturn($mockResponse);

        // Act
        $result = $this->noteSuggestionService->batchSuggestNotes($fragrances, $options);

        // Assert
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEquals(2, $result['summary']['total_processed']);
        $this->assertEquals(2, $result['summary']['successful_count']);
        $this->assertCount(2, $result['results']);
    }

    public function test_process_note_category(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->noteSuggestionService);
        $method = $reflection->getMethod('processNoteCategory');
        $method->setAccessible(true);

        $notes = [
            'bergamot',
            ['name' => 'rose', 'intensity' => 'strong', 'confidence' => 0.95],
            ['name' => 'sandalwood', 'intensity' => 'moderate', 'confidence' => 0.8],
        ];

        $result = $method->invoke($this->noteSuggestionService, $notes, 'top');

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        foreach ($result as $note) {
            $this->assertArrayHasKey('name', $note);
            $this->assertArrayHasKey('intensity', $note);
            $this->assertArrayHasKey('confidence', $note);
            $this->assertArrayHasKey('category', $note);
            $this->assertArrayHasKey('original_name', $note);
        }

        // 信頼度順にソートされていることを確認
        $this->assertGreaterThanOrEqual($result[1]['confidence'], $result[0]['confidence']);
    }

    public function test_normalize_note_name(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->noteSuggestionService);
        $method = $reflection->getMethod('normalizeNoteName');
        $method->setAccessible(true);

        // Test cases
        $testCases = [
            ['bergamotte', 'bergamot'],
            ['rosa', 'rose'],
            ['sandal', 'sandalwood'],
            ['vanille', 'vanilla'],
            ['unknown note', 'unknown note'], // 変更されないケース
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $method->invoke($this->noteSuggestionService, $input);
            $this->assertEquals($expected, $result,
                "Note '{$input}' should be normalized to '{$expected}'");
        }
    }

    public function test_detect_note_category(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->noteSuggestionService);
        $method = $reflection->getMethod('detectNoteCategory');
        $method->setAccessible(true);

        $testCases = [
            ['bergamot', 'citrus'],
            ['rose', 'floral'],
            ['sandalwood', 'woody'],
            ['vanilla', 'oriental'],
            ['mint', 'fresh'],
            ['cinnamon', 'spicy'],
            ['apple', 'fruity'],
            ['grass', 'green'],
            ['chocolate', 'gourmand'],
            ['unknown', 'other'],
        ];

        foreach ($testCases as [$note, $expectedCategory]) {
            $result = $method->invoke($this->noteSuggestionService, $note);
            $this->assertEquals($expectedCategory, $result,
                "Note '{$note}' should be categorized as '{$expectedCategory}'");
        }
    }

    public function test_calculate_overall_confidence(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->noteSuggestionService);
        $method = $reflection->getMethod('calculateOverallConfidence');
        $method->setAccessible(true);

        $notes = [
            'top' => [
                ['confidence' => 0.9],
                ['confidence' => 0.8],
            ],
            'middle' => [
                ['confidence' => 0.85],
            ],
            'base' => [
                ['confidence' => 0.7],
            ],
        ];

        $result = $method->invoke($this->noteSuggestionService, $notes);

        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.0, $result);
        $this->assertLessThanOrEqual(1.0, $result);

        // 平均信頼度の計算: (0.9 + 0.8 + 0.85 + 0.7) / 4 = 0.8125 ≈ 0.81
        $this->assertEquals(0.81, $result);
    }

    public function test_get_fallback_notes_by_brand(): void
    {
        // プライベートメソッドのテスト
        $reflection = new \ReflectionClass($this->noteSuggestionService);
        $method = $reflection->getMethod('getFallbackNotesByBrand');
        $method->setAccessible(true);

        // 既知ブランドのテスト
        $chanelResult = $method->invoke($this->noteSuggestionService, 'CHANEL');
        $this->assertArrayHasKey('top', $chanelResult);
        $this->assertArrayHasKey('middle', $chanelResult);
        $this->assertArrayHasKey('base', $chanelResult);

        $diorResult = $method->invoke($this->noteSuggestionService, 'Dior');
        $this->assertArrayHasKey('top', $diorResult);
        $this->assertArrayHasKey('middle', $diorResult);
        $this->assertArrayHasKey('base', $diorResult);

        // 未知ブランドのテスト（デフォルト値）
        $unknownResult = $method->invoke($this->noteSuggestionService, 'UnknownBrand');
        $this->assertArrayHasKey('top', $unknownResult);
        $this->assertArrayHasKey('middle', $unknownResult);
        $this->assertArrayHasKey('base', $unknownResult);
    }
}
