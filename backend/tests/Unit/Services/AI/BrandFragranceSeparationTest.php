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

class BrandFragranceSeparationTest extends TestCase
{
    private $providerFactoryMock;

    private $costTrackerMock;

    private $aiProviderMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache mock setup
        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $minutes, $callback) {
                return $callback();
            });
        Cache::shouldReceive('has')->andReturn(false);
        Log::spy();

        $this->providerFactoryMock = Mockery::mock(AIProviderFactory::class);
        $this->costTrackerMock = Mockery::mock(CostTrackingService::class);
        $this->aiProviderMock = Mockery::mock(AIProviderInterface::class);

        // Default provider factory setup
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('gemini');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_completion_response_contains_separated_brand_and_fragrance_fields(): void
    {
        // 分離されたブランド名と香水名を含む新しいレスポンス構造をモック
        $mockSeparatedResponse = [
            'suggestions' => [
                [
                    'text' => 'ソヴァージュ EDT',  // 純粋な香水名（日本語）
                    'text_en' => 'Sauvage EDT',  // 純粋な香水名（英語）
                    'brand_name' => 'ディオール',  // ブランド名（日本語）
                    'brand_name_en' => 'Dior',  // ブランド名（英語）
                    'confidence' => 0.95,
                    'type' => 'fragrance',
                ],
                [
                    'text' => 'No.5 オードゥパルファム',
                    'text_en' => 'No.5 Eau de Parfum',
                    'brand_name' => 'シャネル',
                    'brand_name_en' => 'Chanel',
                    'confidence' => 0.90,
                    'type' => 'fragrance',
                ],
                [
                    'text' => 'ブラック オーキッド',
                    'text_en' => 'Black Orchid',
                    'brand_name' => 'トム フォード',
                    'brand_name_en' => 'Tom Ford',
                    'confidence' => 0.88,
                    'type' => 'fragrance',
                ],
            ],
            'response_time_ms' => 1200.5,
            'provider' => 'gemini',
            'cost_estimate' => 0.005,
        ];

        // Cost tracking mock setup
        $this->costTrackerMock
            ->shouldReceive('trackUsage')
            ->withAnyArgs()
            ->andReturn(true);

        // Fallback mechanism tries anthropic first
        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->with('サヴァージュ', [
                'type' => 'fragrance',
                'limit' => 5,
                'language' => 'ja',
                'contextBrand' => null,
            ])
            ->once()
            ->andReturn($mockSeparatedResponse);

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->complete('サヴァージュ', ['type' => 'fragrance', 'limit' => 5]);

        // 基本的なレスポンス構造を検証
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertCount(3, $result['suggestions']);

        // 各提案が新しい分離形式の構造を持つことを検証
        foreach ($result['suggestions'] as $suggestion) {
            // 従来のフィールド
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('confidence', $suggestion);
            $this->assertArrayHasKey('type', $suggestion);

            // 新しい分離フィールド
            $this->assertArrayHasKey('text_en', $suggestion, 'text_en field must be present');
            $this->assertArrayHasKey('brand_name', $suggestion, 'brand_name field must be present');
            $this->assertArrayHasKey('brand_name_en', $suggestion, 'brand_name_en field must be present');

            // フィールドの内容検証
            $this->assertIsString($suggestion['text']);
            $this->assertIsString($suggestion['text_en']);
            $this->assertIsString($suggestion['brand_name']);
            $this->assertIsString($suggestion['brand_name_en']);

            // 香水名にブランド名が含まれていないことを確認
            $this->assertStringNotContainsString($suggestion['brand_name'], $suggestion['text'],
                'Fragrance name should not contain brand name');
            $this->assertStringNotContainsString($suggestion['brand_name_en'], $suggestion['text_en'],
                'English fragrance name should not contain English brand name');

            // 信頼度スコアの検証
            $this->assertIsFloat($suggestion['confidence']);
            $this->assertGreaterThanOrEqual(0.0, $suggestion['confidence']);
            $this->assertLessThanOrEqual(1.0, $suggestion['confidence']);
        }
    }

    public function test_brand_completion_response_structure(): void
    {
        // ブランド名補完の新しいレスポンス構造をモック
        $mockBrandResponse = [
            'suggestions' => [
                [
                    'text' => 'シャネル',
                    'text_en' => 'Chanel',
                    'confidence' => 0.98,
                    'type' => 'brand',
                    // ブランドの場合はbrand_nameフィールドは自分自身またはnull
                    'brand_name' => null,
                    'brand_name_en' => null,
                ],
                [
                    'text' => 'ディオール',
                    'text_en' => 'Dior',
                    'confidence' => 0.95,
                    'type' => 'brand',
                    'brand_name' => null,
                    'brand_name_en' => null,
                ],
            ],
            'response_time_ms' => 800.3,
            'provider' => 'openai',
            'cost_estimate' => 0.003,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->with('シャン', ['type' => 'brand', 'limit' => 5, 'language' => 'ja'])
            ->andReturn($mockBrandResponse);

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->complete('シャン', ['type' => 'brand', 'limit' => 5]);

        // ブランド補完の構造を検証
        $this->assertArrayHasKey('suggestions', $result);

        foreach ($result['suggestions'] as $suggestion) {
            $this->assertEquals('brand', $suggestion['type']);
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('text_en', $suggestion);

            // ブランドの場合、brand_nameは自分自身かnull
            if (isset($suggestion['brand_name'])) {
                $this->assertTrue(
                    $suggestion['brand_name'] === null ||
                    $suggestion['brand_name'] === $suggestion['text'],
                    'For brand suggestions, brand_name should be null or same as text'
                );
            }
        }
    }

    public function test_fragrance_with_variations_separation(): void
    {
        // バリエーションを持つ香水の分離構造をモック
        $mockVariationResponse = [
            'suggestions' => [
                [
                    'text' => 'ソヴァージュ EDT',
                    'text_en' => 'Sauvage EDT',
                    'brand_name' => 'ディオール',
                    'brand_name_en' => 'Dior',
                    'confidence' => 0.95,
                    'type' => 'fragrance',
                ],
                [
                    'text' => 'ソヴァージュ パルファム',
                    'text_en' => 'Sauvage Parfum',
                    'brand_name' => 'ディオール',
                    'brand_name_en' => 'Dior',
                    'confidence' => 0.93,
                    'type' => 'fragrance',
                ],
                [
                    'text' => 'ソヴァージュ エリクサー',
                    'text_en' => 'Sauvage Elixir',
                    'brand_name' => 'ディオール',
                    'brand_name_en' => 'Dior',
                    'confidence' => 0.91,
                    'type' => 'fragrance',
                ],
            ],
            'response_time_ms' => 1500.2,
            'provider' => 'anthropic',
            'cost_estimate' => 0.008,
        ];

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('anthropic')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('openai')
            ->andReturn($this->aiProviderMock);

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('gemini')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->with('ソヴァージュ', [
                'type' => 'fragrance',
                'limit' => 8,
                'language' => 'ja',
                'contextBrand' => null,
            ])
            ->andReturn($mockVariationResponse);

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->complete('ソヴァージュ', ['type' => 'fragrance', 'limit' => 8]);

        // バリエーション提案の検証
        $this->assertCount(3, $result['suggestions']);

        foreach ($result['suggestions'] as $suggestion) {
            // すべて同じブランドであることを確認
            $this->assertEquals('ディオール', $suggestion['brand_name']);
            $this->assertEquals('Dior', $suggestion['brand_name_en']);

            // 香水名がソヴァージュのバリエーションであることを確認
            $this->assertStringContainsString('ソヴァージュ', $suggestion['text']);
            $this->assertStringContainsString('Sauvage', $suggestion['text_en']);

            // 異なるコンセントレーション/バリエーションを持つことを確認
            $this->assertTrue(
                str_contains($suggestion['text'], 'EDT') ||
                str_contains($suggestion['text'], 'パルファム') ||
                str_contains($suggestion['text'], 'エリクサー')
            );
        }
    }

    public function test_legacy_format_compatibility(): void
    {
        // レガシー形式（ブランド名+香水名の結合）のレスポンスも処理できることを確認
        $mockLegacyResponse = [
            'suggestions' => [
                [
                    'text' => 'CHANEL No.5 Eau de Parfum',  // 旧形式（結合）
                    'confidence' => 0.95,
                    'type' => 'fragrance',
                    // 新しいフィールドがない場合
                ],
            ],
            'response_time_ms' => 1000,
            'provider' => 'openai',
            'cost_estimate' => 0.005,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->andReturn($mockLegacyResponse);

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->complete('No.5', ['type' => 'fragrance']);

        // レガシー形式でも正常に処理されることを確認
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertCount(1, $result['suggestions']);

        $suggestion = $result['suggestions'][0];
        $this->assertEquals('CHANEL No.5 Eau de Parfum', $suggestion['text']);
        $this->assertEquals('fragrance', $suggestion['type']);

        // 新しいフィールドが存在しない場合でもエラーにならないことを確認
        // （実装側でデフォルト値が設定される）
    }

    public function test_empty_brand_name_handling(): void
    {
        // ブランド名が空の場合の処理をテスト
        $mockEmptyBrandResponse = [
            'suggestions' => [
                [
                    'text' => '匿名の香水',
                    'text_en' => 'Anonymous Fragrance',
                    'brand_name' => '',  // 空のブランド名
                    'brand_name_en' => '',
                    'confidence' => 0.50,
                    'type' => 'fragrance',
                ],
            ],
            'response_time_ms' => 800,
            'provider' => 'gemini',
            'cost_estimate' => 0.002,
        ];

        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('gemini');

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('complete')
            ->andReturn($mockEmptyBrandResponse);

        $service = new CompletionService($this->providerFactoryMock, $this->costTrackerMock);
        $result = $service->complete('未知の香水', ['type' => 'fragrance']);

        // 空のブランド名でも正常に処理されることを確認
        $suggestion = $result['suggestions'][0];
        $this->assertEquals('', $suggestion['brand_name']);
        $this->assertEquals('', $suggestion['brand_name_en']);
        $this->assertIsFloat($suggestion['confidence']);
    }
}
