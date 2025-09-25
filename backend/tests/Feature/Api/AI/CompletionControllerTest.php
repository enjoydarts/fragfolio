<?php

use App\Services\AI\AIProviderFactory;
use App\Services\AI\CompletionService;
use App\Services\AI\CostTrackingService;
use App\UseCases\AI\CompleteFragranceUseCase;
use Illuminate\Support\Facades\Config;

describe('AI補完API', function () {
    beforeEach(function () {
        // AI API キーの設定（テスト用）
        Config::set('services.openai.api_key', 'test-openai-key');
        Config::set('services.anthropic.api_key', 'test-anthropic-key');
        Config::set('services.ai.default_provider', 'openai');
    });

    test('有効なリクエストで補完ができる', function () {
        $requestData = [
            'query' => 'シャネル',
            'type' => 'brand',
            'limit' => 5,
            'language' => 'ja',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'suggestions',
                    'response_time_ms',
                    'provider',
                    'cost_estimate',
                    'metadata',
                ],
            ]);
    });

    test('短すぎるクエリではエラーになる', function () {
        $requestData = [
            'query' => 'a', // Too short
            'type' => 'brand',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['query']);
    });

    test('必須フィールドが不足している場合はエラーになる', function () {
        $requestData = [
            'query' => 'テスト',
            // type が欠如
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['type']);
    });

    test('無効なタイプではエラーになる', function () {
        $requestData = [
            'query' => 'テスト',
            'type' => 'invalid_type',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    test('無効なプロバイダーではエラーになる', function () {
        $requestData = [
            'query' => 'テスト',
            'type' => 'brand',
            'provider' => 'invalid_provider',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    });

    test('制限値が高すぎる場合はエラーになる', function () {
        $requestData = [
            'query' => 'テスト',
            'type' => 'brand',
            'limit' => 25, // Over limit
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    });

    test('制限値が低すぎる場合はエラーになる', function () {
        $requestData = [
            'query' => 'テスト',
            'type' => 'brand',
            'limit' => 0, // Too low
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    });

    test('一括補完が正常に動作する', function () {
        $requestData = [
            'queries' => ['シャネル', 'ディオール', 'トムフォード'],
            'type' => 'brand',
            'language' => 'ja',
        ];

        $response = $this->postJson('/api/ai/batch-complete', $requestData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'results',
                    'total_queries',
                    'total_cost_estimate',
                ],
            ]);
    });

    test('クエリ数が多すぎる場合は一括補完がエラーになる', function () {
        $requestData = [
            'queries' => array_fill(0, 15, 'テスト'), // 15 queries (over limit of 10)
            'type' => 'brand',
        ];

        $response = $this->postJson('/api/ai/batch-complete', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['queries']);
    });

    test('プロバイダー一覧が取得できる', function () {
        $response = $this->getJson('/api/ai/providers');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'providers',
                    'default',
                    'total',
                ],
            ]);
    });

    test('ヘルスチェックが正常に動作する', function () {
        $response = $this->getJson('/api/ai/health');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'providers',
                    'overall_status',
                ],
            ]);
    });

    test('日本語での補完が正常に動作する', function () {
        $requestData = [
            'query' => 'シャネル',
            'type' => 'brand',
            'language' => 'ja',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data['metadata']['language'])->toBe('ja');
    });

    test('英語での補完が正常に動作する', function () {
        $requestData = [
            'query' => 'Chanel',
            'type' => 'brand',
            'language' => 'en',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data['metadata']['language'])->toBe('en');
    });

    test('香水タイプでの補完が正常に動作する', function () {
        $requestData = [
            'query' => 'No.5',
            'type' => 'fragrance',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data['metadata']['type'])->toBe('fragrance');
    });

    test('レスポンス構造が正しい', function () {
        $requestData = [
            'query' => 'テスト',
            'type' => 'brand',
        ];

        $response = $this->postJson('/api/ai/complete', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveKeys(['suggestions', 'response_time_ms', 'provider', 'cost_estimate', 'metadata']);

        // メタデータの構造確認
        $metadata = $data['metadata'];
        expect($metadata['query'])->toBe('テスト');
        expect($metadata['type'])->toBe('brand');
        expect($metadata)->toHaveKey('timestamp');
    });
});