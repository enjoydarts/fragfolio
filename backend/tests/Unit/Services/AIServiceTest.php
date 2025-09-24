<?php

use App\Services\AIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

describe('AIService', function () {
    beforeEach(function () {
        // 設定をモック
        Config::set([
            'services.ai.default_provider' => 'openai',
            'services.openai.api_key' => 'test-openai-key',
            'services.anthropic.api_key' => 'test-anthropic-key',
            'services.ai.gpt_model' => 'gpt-4',
            'services.ai.claude_model' => 'claude-3-sonnet-20240229',
        ]);

        $this->service = new AIService();
    });

    test('OpenAIで香水データを正規化できる', function () {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'normalized_brand' => 'Chanel',
                            'normalized_fragrance_name' => 'No. 5',
                            'concentration_type' => 'EDP',
                            'launch_year' => '1921',
                            'fragrance_family' => 'フローラル',
                            'top_notes' => ['ベルガモット', 'レモン'],
                            'middle_notes' => ['ジャスミン', 'ローズ'],
                            'base_notes' => ['サンダルウッド', 'バニラ'],
                            'suitable_seasons' => ['春', '秋'],
                            'suitable_scenes' => ['フォーマル', 'デート'],
                            'description_ja' => '伝説的な香水',
                            'description_en' => 'Legendary fragrance'
                        ])
                    ]
                ]
            ]
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response($mockResponse, 200)
        ]);

        $result = $this->service->normalizeFragranceData('シャネル', 'No.5', 'openai');

        expect($result)->toBeArray();
        expect($result['normalized_brand'])->toBe('Chanel');
        expect($result['normalized_fragrance_name'])->toBe('No. 5');
        expect($result['concentration_type'])->toBe('EDP');
    });

    test('Anthropicで香水データを正規化できる', function () {
        $mockResponse = [
            'content' => [
                [
                    'text' => json_encode([
                        'normalized_brand' => 'Dior',
                        'normalized_fragrance_name' => 'Sauvage',
                        'concentration_type' => 'EDT',
                        'launch_year' => '2015',
                        'fragrance_family' => 'ウッディ',
                        'top_notes' => ['ベルガモット', 'ピンクペッパー'],
                        'middle_notes' => ['ラベンダー', 'ゼラニウム'],
                        'base_notes' => ['アンブロクサン', 'シダーウッド'],
                        'suitable_seasons' => ['春', '夏'],
                        'suitable_scenes' => ['カジュアル', 'ビジネス'],
                        'description_ja' => 'モダンなフレッシュフレグランス',
                        'description_en' => 'Modern fresh fragrance'
                    ])
                ]
            ]
        ];

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response($mockResponse, 200)
        ]);

        $result = $this->service->normalizeFragranceData('ディオール', 'ソヴァージュ', 'anthropic');

        expect($result)->toBeArray();
        expect($result['normalized_brand'])->toBe('Dior');
        expect($result['normalized_fragrance_name'])->toBe('Sauvage');
        expect($result['concentration_type'])->toBe('EDT');
    });

    test('デフォルトプロバイダーが使用される', function () {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"test": "data"}']]]
            ], 200)
        ]);

        $this->service->normalizeFragranceData('Test', 'Fragrance');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions';
        });
    });

    test('JSONマークダウンブロックが正しく解析される', function () {
        $jsonResponse = '```json
{
    "normalized_brand": "Test Brand",
    "normalized_fragrance_name": "Test Fragrance"
}
```';

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $jsonResponse]]]
            ], 200)
        ]);

        $result = $this->service->normalizeFragranceData('Test', 'Fragrance');

        expect($result['normalized_brand'])->toBe('Test Brand');
        expect($result['normalized_fragrance_name'])->toBe('Test Fragrance');
    });

    test('サポートされていないプロバイダーでエラー', function () {
        expect(fn() => $this->service->normalizeFragranceData('Test', 'Fragrance', 'invalid'))
            ->toThrow(\InvalidArgumentException::class, 'Unsupported AI provider: invalid');
    });

    test('OpenAI APIキーが設定されていない場合はエラー', function () {
        Config::set('services.openai.api_key', null);
        $service = new AIService();

        expect(fn() => $service->normalizeFragranceData('Test', 'Fragrance', 'openai'))
            ->toThrow(\Exception::class, 'OpenAI API key is not configured');
    });

    test('Anthropic APIキーが設定されていない場合はエラー', function () {
        Config::set('services.anthropic.api_key', null);
        $service = new AIService();

        expect(fn() => $service->normalizeFragranceData('Test', 'Fragrance', 'anthropic'))
            ->toThrow(\Exception::class, 'Anthropic API key is not configured');
    });

    test('OpenAI APIリクエストが失敗した場合はエラー', function () {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response('API Error', 500)
        ]);

        expect(fn() => $this->service->normalizeFragranceData('Test', 'Fragrance', 'openai'))
            ->toThrow(\Exception::class);
    });

    test('Anthropic APIリクエストが失敗した場合はエラー', function () {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response('API Error', 500)
        ]);

        expect(fn() => $this->service->normalizeFragranceData('Test', 'Fragrance', 'anthropic'))
            ->toThrow(\Exception::class);
    });

    test('無効なJSONレスポンスでエラー', function () {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'invalid json']]]
            ], 200)
        ]);

        expect(fn() => $this->service->normalizeFragranceData('Test', 'Fragrance'))
            ->toThrow(\Exception::class, 'Failed to parse AI response as JSON');
    });

    test('利用可能なプロバイダーを取得できる', function () {
        Config::set([
            'services.openai.api_key' => 'test-key',
            'services.anthropic.api_key' => 'test-key',
        ]);
        $service = new AIService();

        $providers = $service->getAvailableProviders();

        expect($providers)->toContain('openai');
        expect($providers)->toContain('anthropic');
    });

    test('APIキーが設定されていないプロバイダーは除外される', function () {
        Config::set([
            'services.openai.api_key' => 'test-key',
            'services.anthropic.api_key' => null,
        ]);
        $service = new AIService();

        $providers = $service->getAvailableProviders();

        expect($providers)->toContain('openai');
        expect($providers)->not()->toContain('anthropic');
    });

    test('プロンプトが正しく構築される', function () {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"test": "data"}']]]
            ], 200)
        ]);

        $this->service->normalizeFragranceData('Test Brand', 'Test Fragrance');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $prompt = $body['messages'][0]['content'];

            return str_contains($prompt, 'Test Brand') &&
                   str_contains($prompt, 'Test Fragrance') &&
                   str_contains($prompt, 'JSON形式で回答してください');
        });
    });
});