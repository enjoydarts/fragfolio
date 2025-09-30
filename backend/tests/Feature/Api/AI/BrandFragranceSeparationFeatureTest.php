<?php

namespace Tests\Feature\Api\AI;

use App\UseCases\AI\CompleteFragranceUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BrandFragranceSeparationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_completion_endpoint_returns_separated_brand_fragrance_format(): void
    {
        // CompleteFragranceUseCaseをモック
        $mockUseCase = Mockery::mock(CompleteFragranceUseCase::class);
        $mockUseCase->shouldReceive('execute')
            ->with('ソヴァージュ', [
                'type' => 'fragrance',
                'limit' => 5,
                'language' => 'ja',
                'provider' => null,
                'contextBrand' => null,
                'user_id' => null,
            ])
            ->andReturn([
                'suggestions' => [
                    [
                        'text' => 'ソヴァージュ EDT',
                        'text_en' => 'Sauvage EDT',
                        'brand_name' => 'ディオール',
                        'brand_name_en' => 'Dior',
                        'confidence' => 0.95,
                        'type' => 'fragrance',
                        'similarity_score' => 0.95,
                        'adjusted_confidence' => 0.95,
                    ],
                    [
                        'text' => 'ソヴァージュ パルファム',
                        'text_en' => 'Sauvage Parfum',
                        'brand_name' => 'ディオール',
                        'brand_name_en' => 'Dior',
                        'confidence' => 0.93,
                        'type' => 'fragrance',
                        'similarity_score' => 0.93,
                        'adjusted_confidence' => 0.93,
                    ],
                ],
                'provider' => 'gemini',
                'response_time_ms' => 100,
                'cost_estimate' => 0.001,
            ]);

        $this->app->instance(CompleteFragranceUseCase::class, $mockUseCase);

        $response = $this->postJson('/api/ai/complete', [
            'query' => 'ソヴァージュ',
            'type' => 'fragrance',
            'limit' => 5,
            'language' => 'ja',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'suggestions' => [
                    '*' => [
                        'text',
                        'text_en',
                        'brand_name',
                        'brand_name_en',
                        'confidence',
                        'type',
                    ],
                ],
                'provider',
                'response_time_ms',
                'cost_estimate',
            ],
        ]);

        $data = $response->json('data');

        // 提案が存在することを確認
        $this->assertNotEmpty($data['suggestions']);

        // 各提案が分離形式を持つことを検証
        foreach ($data['suggestions'] as $suggestion) {
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('text_en', $suggestion);
            $this->assertArrayHasKey('brand_name', $suggestion);
            $this->assertArrayHasKey('brand_name_en', $suggestion);
            $this->assertArrayHasKey('confidence', $suggestion);

            // 香水名にブランド名が含まれていないことを確認
            if (! empty($suggestion['brand_name']) && ! empty($suggestion['text'])) {
                $this->assertStringNotContainsString(
                    $suggestion['brand_name'],
                    $suggestion['text'],
                    "Fragrance name '{$suggestion['text']}' should not contain brand name '{$suggestion['brand_name']}'"
                );
            }

            // 英語版でも同様の確認
            if (! empty($suggestion['brand_name_en']) && ! empty($suggestion['text_en'])) {
                $this->assertStringNotContainsString(
                    $suggestion['brand_name_en'],
                    $suggestion['text_en'],
                    "English fragrance name '{$suggestion['text_en']}' should not contain English brand name '{$suggestion['brand_name_en']}'"
                );
            }

            // 信頼度スコアの検証
            $this->assertIsFloat($suggestion['confidence']);
            $this->assertGreaterThanOrEqual(0.0, $suggestion['confidence']);
            $this->assertLessThanOrEqual(1.0, $suggestion['confidence']);
        }
    }

    public function test_brand_completion_has_appropriate_structure(): void
    {
        // CompleteFragranceUseCaseをモック
        $mockUseCase = Mockery::mock(CompleteFragranceUseCase::class);
        $mockUseCase->shouldReceive('execute')
            ->with('シャン', [
                'type' => 'brand',
                'limit' => 5,
                'language' => 'ja',
                'provider' => null,
                'contextBrand' => null,
                'user_id' => null,
            ])
            ->andReturn([
                'suggestions' => [
                    [
                        'text' => 'シャネル',
                        'text_en' => 'Chanel',
                        'confidence' => 0.98,
                        'type' => 'brand',
                        'brand_name' => null,
                        'brand_name_en' => null,
                        'similarity_score' => 0.98,
                        'adjusted_confidence' => 0.98,
                    ],
                    [
                        'text' => 'ディオール',
                        'text_en' => 'Dior',
                        'confidence' => 0.95,
                        'type' => 'brand',
                        'brand_name' => null,
                        'brand_name_en' => null,
                        'similarity_score' => 0.95,
                        'adjusted_confidence' => 0.95,
                    ],
                ],
                'provider' => 'gemini',
                'response_time_ms' => 80,
                'cost_estimate' => 0.001,
            ]);

        $this->app->instance(CompleteFragranceUseCase::class, $mockUseCase);

        $response = $this->postJson('/api/ai/complete', [
            'query' => 'シャン',
            'type' => 'brand',
            'limit' => 5,
            'language' => 'ja',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data['suggestions']);

        foreach ($data['suggestions'] as $suggestion) {
            $this->assertEquals('brand', $suggestion['type']);
            $this->assertArrayHasKey('text', $suggestion);
            $this->assertArrayHasKey('text_en', $suggestion);

            // ブランドの場合、brand_nameは通常null
            if (isset($suggestion['brand_name'])) {
                $this->assertTrue(
                    $suggestion['brand_name'] === null ||
                    $suggestion['brand_name'] === $suggestion['text']
                );
            }
        }
    }

    public function test_fragrance_variations_are_properly_separated(): void
    {
        // CompleteFragranceUseCaseをモック
        $mockUseCase = Mockery::mock(CompleteFragranceUseCase::class);
        $mockUseCase->shouldReceive('execute')
            ->with('No.5', [
                'type' => 'fragrance',
                'limit' => 8,
                'language' => 'ja',
                'provider' => null,
                'contextBrand' => null,
                'user_id' => null,
            ])
            ->andReturn([
                'suggestions' => [
                    [
                        'text' => 'No.5 オードゥパルファム',
                        'text_en' => 'No.5 Eau de Parfum',
                        'brand_name' => 'シャネル',
                        'brand_name_en' => 'Chanel',
                        'confidence' => 0.96,
                        'type' => 'fragrance',
                        'similarity_score' => 0.96,
                        'adjusted_confidence' => 0.96,
                    ],
                    [
                        'text' => 'No.5 オードゥトワレ',
                        'text_en' => 'No.5 Eau de Toilette',
                        'brand_name' => 'シャネル',
                        'brand_name_en' => 'Chanel',
                        'confidence' => 0.94,
                        'type' => 'fragrance',
                        'similarity_score' => 0.94,
                        'adjusted_confidence' => 0.94,
                    ],
                    [
                        'text' => 'No.5 ロー',
                        'text_en' => 'No.5 L\'Eau',
                        'brand_name' => 'シャネル',
                        'brand_name_en' => 'Chanel',
                        'confidence' => 0.92,
                        'type' => 'fragrance',
                        'similarity_score' => 0.92,
                        'adjusted_confidence' => 0.92,
                    ],
                ],
                'provider' => 'gemini',
                'response_time_ms' => 120,
                'cost_estimate' => 0.001,
            ]);

        $this->app->instance(CompleteFragranceUseCase::class, $mockUseCase);

        $response = $this->postJson('/api/ai/complete', [
            'query' => 'No.5',
            'type' => 'fragrance',
            'limit' => 8,
            'language' => 'ja',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data['suggestions']);

        foreach ($data['suggestions'] as $suggestion) {
            // すべて同じブランドであることを確認
            $this->assertEquals('シャネル', $suggestion['brand_name']);
            $this->assertEquals('Chanel', $suggestion['brand_name_en']);

            // 香水名がNo.5のバリエーションであることを確認
            $this->assertStringContainsString('No.5', $suggestion['text']);
            $this->assertStringContainsString('No.5', $suggestion['text_en']);

            // 分離が正しく行われていることを確認
            $this->assertStringNotContainsString('シャネル', $suggestion['text']);
            $this->assertStringNotContainsString('Chanel', $suggestion['text_en']);
        }
    }

    public function test_completion_endpoint_handles_empty_results_gracefully(): void
    {
        // CompleteFragranceUseCaseをモック
        $mockUseCase = Mockery::mock(CompleteFragranceUseCase::class);
        $mockUseCase->shouldReceive('execute')
            ->with('存在しない香水', [
                'type' => 'fragrance',
                'limit' => 5,
                'language' => 'ja',
                'provider' => null,
                'contextBrand' => null,
                'user_id' => null,
            ])
            ->andReturn([
                'suggestions' => [],
                'provider' => 'fallback',
                'response_time_ms' => 50,
                'cost_estimate' => 0.0,
            ]);

        $this->app->instance(CompleteFragranceUseCase::class, $mockUseCase);

        $response = $this->postJson('/api/ai/complete', [
            'query' => '存在しない香水',
            'type' => 'fragrance',
            'limit' => 5,
            'language' => 'ja',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('suggestions', $data);
        $this->assertIsArray($data['suggestions']);
        // 空の結果でも配列構造は保持される
    }

    public function test_completion_endpoint_validates_request_parameters(): void
    {
        // 無効なパラメータでのリクエスト
        $response = $this->postJson('/api/ai/complete', [
            'query' => '',  // 空のクエリ
            'type' => 'fragrance',
        ]);

        $response->assertStatus(422); // バリデーションエラー

        // 無効なtypeパラメータ
        $response = $this->postJson('/api/ai/complete', [
            'query' => 'test',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422); // バリデーションエラー
    }
}
