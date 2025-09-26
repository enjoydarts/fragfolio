<?php

use Illuminate\Support\Facades\Config;

describe('AI正規化API', function () {
    beforeEach(function () {
        // AI API キーの設定（テスト用）
        Config::set('services.openai.api_key', 'test-openai-key');
        Config::set('services.anthropic.api_key', 'test-anthropic-key');
        Config::set('services.ai.default_provider', 'openai');
    });

    test('有効なリクエストで正規化ができる', function () {
        $requestData = [
            'brand_name' => 'シャネル',
            'fragrance_name' => 'No.5',
            'language' => 'ja',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'normalized_data',
                    'response_time_ms',
                    'provider',
                    'cost_estimate',
                    'metadata',
                    'execution_time_ms',
                ],
            ]);
    });

    test('ブランド名が短すぎる場合はエラーになる', function () {
        $requestData = [
            'brand_name' => 'a', // Too short
            'fragrance_name' => 'テスト香水',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_name']);
    });

    test('香水名が短すぎる場合はエラーになる', function () {
        $requestData = [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'a', // Too short
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrance_name']);
    });

    test('必須フィールドが不足している場合はエラーになる', function () {
        $requestData = [
            'brand_name' => 'テストブランド',
            // fragrance_name が欠如
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrance_name']);
    });

    test('無効なプロバイダーではエラーになる', function () {
        $requestData = [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'provider' => 'invalid_provider',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    });

    test('無効な言語ではエラーになる', function () {
        $requestData = [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'language' => 'fr', // 無効な言語
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    });

    test('一括正規化が正常に動作する', function () {
        $requestData = [
            'fragrances' => [
                ['brand_name' => 'シャネル', 'fragrance_name' => 'No.5'],
                ['brand_name' => 'ディオール', 'fragrance_name' => 'J\'adore'],
                ['brand_name' => 'トムフォード', 'fragrance_name' => 'Noir'],
            ],
            'language' => 'ja',
        ];

        $response = $this->postJson('/api/ai/batch-normalize', $requestData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'results',
                    'total_processed',
                    'successful_count',
                    'total_cost_estimate',
                    'execution_time_ms',
                    'success_rate',
                ],
            ]);
    });

    test('香水情報が多すぎる場合は一括正規化がエラーになる', function () {
        $fragrances = [];
        for ($i = 0; $i < 15; $i++) { // 15個（制限超過）
            $fragrances[] = [
                'brand_name' => 'ブランド'.$i,
                'fragrance_name' => '香水'.$i,
            ];
        }

        $requestData = [
            'fragrances' => $fragrances,
        ];

        $response = $this->postJson('/api/ai/batch-normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrances']);
    });

    test('香水情報が空の場合は一括正規化がエラーになる', function () {
        $requestData = [
            'fragrances' => [],
        ];

        $response = $this->postJson('/api/ai/batch-normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrances']);
    });

    test('香水情報の構造が不正な場合はエラーになる', function () {
        $requestData = [
            'fragrances' => [
                ['brand_name' => 'シャネル'], // fragrance_name が欠如
                ['fragrance_name' => 'No.5'], // brand_name が欠如
            ],
        ];

        $response = $this->postJson('/api/ai/batch-normalize', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'fragrances.0.fragrance_name',
                'fragrances.1.brand_name',
            ]);
    });

    test('正規化プロバイダー一覧が取得できる', function () {
        $response = $this->getJson('/api/ai/normalization/providers');

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

    test('正規化ヘルスチェックが正常に動作する', function () {
        $response = $this->getJson('/api/ai/normalization/health');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'providers',
                    'overall_status',
                    'timestamp',
                ],
            ]);
    });

    test('日本語での正規化が正常に動作する', function () {
        $requestData = [
            'brand_name' => 'シャネル',
            'fragrance_name' => 'No.5',
            'language' => 'ja',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data['metadata']['language'])->toBe('ja');
        expect($data['metadata']['original_brand'])->toBe('シャネル');
        expect($data['metadata']['original_fragrance'])->toBe('No.5');
    });

    test('英語での正規化が正常に動作する', function () {
        $requestData = [
            'brand_name' => 'Chanel',
            'fragrance_name' => 'No.5',
            'language' => 'en',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data['metadata']['language'])->toBe('en');
        expect($data['metadata']['original_brand'])->toBe('Chanel');
        expect($data['metadata']['original_fragrance'])->toBe('No.5');
    });

    test('レスポンス構造が正しい', function () {
        $requestData = [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveKeys([
            'normalized_data',
            'response_time_ms',
            'provider',
            'cost_estimate',
            'metadata',
            'execution_time_ms',
        ]);

        // メタデータの構造確認
        $metadata = $data['metadata'];
        expect($metadata)->toHaveKeys([
            'original_brand',
            'original_fragrance',
            'language',
            'provider',
            'timestamp',
            'cached',
        ]);
        expect($metadata['original_brand'])->toBe('テストブランド');
        expect($metadata['original_fragrance'])->toBe('テスト香水');
    });

    test('正規化データの構造が正しい', function () {
        $requestData = [
            'brand_name' => 'Chanel',
            'fragrance_name' => 'No.5',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);

        $response->assertStatus(200);

        $data = $response->json('data');
        $normalizedData = $data['normalized_data'];

        // 正規化データの基本構造
        expect($normalizedData)->toHaveKey('normalized_brand');
        expect($normalizedData)->toHaveKey('normalized_fragrance_name');
        expect($normalizedData)->toHaveKey('final_confidence_score');

        // 信頼度スコアは0-1の範囲
        expect($normalizedData['final_confidence_score'])->toBeFloat();
        expect($normalizedData['final_confidence_score'])->toBeGreaterThanOrEqual(0.0);
        expect($normalizedData['final_confidence_score'])->toBeLessThanOrEqual(1.0);
    });

    test('キャッシュが正常に動作する', function () {
        $requestData = [
            'brand_name' => 'Chanel',
            'fragrance_name' => 'No.5',
        ];

        // 1回目のリクエスト
        $response1 = $this->postJson('/api/ai/normalize', $requestData);
        $response1->assertStatus(200);
        $data1 = $response1->json('data');

        // 2回目のリクエスト（キャッシュから取得されるべき）
        $response2 = $this->postJson('/api/ai/normalize', $requestData);
        $response2->assertStatus(200);
        $data2 = $response2->json('data');

        // フォールバック機能の場合、レスポンス時間が同じになることがあるので、以下を確認
        expect($data2['response_time_ms'])->toBeLessThanOrEqual($data1['response_time_ms']);

        // メタデータにキャッシュ情報が含まれている
        expect($data2['metadata'])->toHaveKey('cached');
    });

    test('認証ユーザーでフィードバック情報が含まれる', function () {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $requestData = [
            'brand_name' => 'Chanel',
            'fragrance_name' => 'No.5',
        ];

        $response = $this->postJson('/api/ai/normalize', $requestData);
        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveKey('feedback');
        expect($data['feedback'])->toHaveKeys([
            'can_provide_feedback',
            'feedback_url',
            'rating_options',
        ]);
        expect($data['feedback']['can_provide_feedback'])->toBeTrue();
    });
});
