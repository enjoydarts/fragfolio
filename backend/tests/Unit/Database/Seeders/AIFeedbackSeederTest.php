<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\AIFeedback;
use Database\Seeders\AIFeedbackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AIFeedbackSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_ai_feedback_records(): void
    {
        // Seederを実行
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // データが作成されたことを確認
        $this->assertTrue(AIFeedback::count() > 0);

        // 最低限のレコード数が存在することを確認（概算）
        $totalRecords = AIFeedback::count();
        $this->assertGreaterThan(50, $totalRecords, 'Should create substantial amount of feedback data');
    }

    public function test_brand_search_patterns_use_separated_format(): void
    {
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // ブランド検索パターンのテスト
        $brandFeedbacks = AIFeedback::where('operation_type', 'completion')
            ->where('query_type', 'brand_search')
            ->get();

        $this->assertNotEmpty($brandFeedbacks, 'Brand search patterns should exist');

        foreach ($brandFeedbacks as $feedback) {
            $aiSuggestions = $feedback->ai_suggestions;
            $selectedSuggestion = $feedback->selected_suggestion;

            // AI提案の構造を検証
            if (is_array($aiSuggestions)) {
                foreach ($aiSuggestions as $suggestion) {
                    if (is_array($suggestion)) {
                        $this->assertArrayHasKey('text', $suggestion);
                        $this->assertArrayHasKey('confidence', $suggestion);

                        // 新しい分離形式のフィールドが存在することを確認
                        if (isset($suggestion['text_en'])) {
                            $this->assertIsString($suggestion['text_en']);
                        }
                        if (isset($suggestion['brand_name'])) {
                            $this->assertIsString($suggestion['brand_name']);
                        }
                        if (isset($suggestion['brand_name_en'])) {
                            $this->assertIsString($suggestion['brand_name_en']);
                        }
                    }
                }
            }

            // 選択された提案の構造を検証
            if (is_array($selectedSuggestion)) {
                $this->assertArrayHasKey('text', $selectedSuggestion);
                $this->assertArrayHasKey('confidence', $selectedSuggestion);
            }
        }
    }

    public function test_fragrance_search_patterns_use_separated_format(): void
    {
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // 香水検索パターンのテスト
        $fragranceFeedbacks = AIFeedback::where('operation_type', 'completion')
            ->where('query_type', 'fragrance_search')
            ->get();

        $this->assertNotEmpty($fragranceFeedbacks, 'Fragrance search patterns should exist');

        foreach ($fragranceFeedbacks as $feedback) {
            $aiSuggestions = $feedback->ai_suggestions;
            $selectedSuggestion = $feedback->selected_suggestion;

            // AI提案の構造を検証
            if (is_array($aiSuggestions)) {
                foreach ($aiSuggestions as $suggestion) {
                    if (is_array($suggestion) && isset($suggestion['type']) && $suggestion['type'] === 'fragrance') {
                        $this->assertArrayHasKey('text', $suggestion);

                        // 分離形式の検証：香水提案にはbrand_nameフィールドが必要
                        if (isset($suggestion['brand_name'])) {
                            $this->assertIsString($suggestion['brand_name']);
                            $this->assertNotEmpty($suggestion['brand_name'], 'Fragrance suggestions should have brand name');
                        }

                        if (isset($suggestion['brand_name_en'])) {
                            $this->assertIsString($suggestion['brand_name_en']);
                            $this->assertNotEmpty($suggestion['brand_name_en'], 'Fragrance suggestions should have English brand name');
                        }

                        if (isset($suggestion['text_en'])) {
                            $this->assertIsString($suggestion['text_en']);
                        }

                        // 香水名にブランド名が含まれていないことを確認
                        if (isset($suggestion['text']) && isset($suggestion['brand_name'])) {
                            // 日本語ブランド名が香水名に含まれていないことを確認
                            $this->assertStringNotContainsString(
                                $suggestion['brand_name'],
                                $suggestion['text'],
                                "Fragrance name '{$suggestion['text']}' should not contain brand name '{$suggestion['brand_name']}'"
                            );
                        }

                        if (isset($suggestion['text_en']) && isset($suggestion['brand_name_en'])) {
                            // 英語ブランド名が英語香水名に含まれていないことを確認
                            $this->assertStringNotContainsString(
                                $suggestion['brand_name_en'],
                                $suggestion['text_en'],
                                "English fragrance name '{$suggestion['text_en']}' should not contain English brand name '{$suggestion['brand_name_en']}'"
                            );
                        }
                    }
                }
            }
        }
    }

    public function test_scent_search_patterns_use_separated_format(): void
    {
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // 香り検索パターンのテスト
        $scentFeedbacks = AIFeedback::where('operation_type', 'completion')
            ->where('query_type', 'scent_characteristic')
            ->get();

        $this->assertNotEmpty($scentFeedbacks, 'Scent search patterns should exist');

        foreach ($scentFeedbacks as $feedback) {
            $aiSuggestions = $feedback->ai_suggestions;

            if (is_array($aiSuggestions)) {
                foreach ($aiSuggestions as $suggestion) {
                    if (is_array($suggestion)) {
                        // scent_match タイプの提案でも分離形式を使用
                        if (isset($suggestion['type']) && $suggestion['type'] === 'scent_match') {
                            $this->assertArrayHasKey('text', $suggestion);

                            // 分離形式のフィールドがある場合の検証
                            if (isset($suggestion['brand_name']) && isset($suggestion['text'])) {
                                $this->assertStringNotContainsString(
                                    $suggestion['brand_name'],
                                    $suggestion['text'],
                                    'Scent match fragrance name should not contain brand name'
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    public function test_helper_methods_work_correctly(): void
    {
        $seeder = new AIFeedbackSeeder;

        // getEnglishName メソッドのテスト（リフレクションを使用）
        $reflectionClass = new \ReflectionClass($seeder);
        $getEnglishNameMethod = $reflectionClass->getMethod('getEnglishName');
        $getEnglishNameMethod->setAccessible(true);

        $englishName = $getEnglishNameMethod->invoke($seeder, 'アバントゥス');
        $this->assertEquals('Aventus', $englishName);

        $unknownName = $getEnglishNameMethod->invoke($seeder, '未知の香水');
        $this->assertEquals('未知の香水', $unknownName); // マッピングにない場合はそのまま返す

        // getEnglishBrand メソッドのテスト
        $getEnglishBrandMethod = $reflectionClass->getMethod('getEnglishBrand');
        $getEnglishBrandMethod->setAccessible(true);

        $englishBrand = $getEnglishBrandMethod->invoke($seeder, 'シャネル');
        $this->assertEquals('CHANEL', $englishBrand);

        $unknownBrand = $getEnglishBrandMethod->invoke($seeder, '未知のブランド');
        $this->assertEquals('未知のブランド', $unknownBrand);

        // getJapaneseBrand メソッドのテスト
        $getJapaneseBrandMethod = $reflectionClass->getMethod('getJapaneseBrand');
        $getJapaneseBrandMethod->setAccessible(true);

        $japaneseBrand = $getJapaneseBrandMethod->invoke($seeder, 'CHANEL');
        $this->assertEquals('シャネル', $japaneseBrand);

        $unknownEnBrand = $getJapaneseBrandMethod->invoke($seeder, 'UnknownBrand');
        $this->assertEquals('UnknownBrand', $unknownEnBrand);
    }

    public function test_feedback_data_has_required_structure(): void
    {
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        $feedbacks = AIFeedback::limit(10)->get();

        foreach ($feedbacks as $feedback) {
            // 必須フィールドの存在確認
            $this->assertNotNull($feedback->query);
            $this->assertNotNull($feedback->operation_type);
            $this->assertNotNull($feedback->query_type);
            $this->assertNotNull($feedback->ai_provider);
            $this->assertNotNull($feedback->ai_model);

            // JSON フィールドの構造確認
            if ($feedback->ai_suggestions) {
                $this->assertIsArray($feedback->ai_suggestions);
            }

            if ($feedback->selected_suggestion) {
                $this->assertIsArray($feedback->selected_suggestion);
            }

            if ($feedback->context_data) {
                $this->assertIsArray($feedback->context_data);
            }

            // 数値フィールドの範囲確認
            if ($feedback->relevance_score !== null) {
                $this->assertGreaterThanOrEqual(0, $feedback->relevance_score);
                $this->assertLessThanOrEqual(1, $feedback->relevance_score);
            }
        }
    }

    public function test_seeder_creates_diverse_query_types(): void
    {
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // 異なるクエリタイプが作成されることを確認
        $queryTypes = AIFeedback::distinct('query_type')->pluck('query_type')->toArray();

        $expectedTypes = [
            'brand_search',
            'fragrance_search',
            'english_fragrance_search',
            'scent_characteristic',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $queryTypes, "Query type '{$type}' should be present in seeded data");
        }
    }

    public function test_seeder_creates_diverse_providers(): void
    {
        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // 異なるAIプロバイダーが作成されることを確認
        $providers = AIFeedback::distinct('ai_provider')->pluck('ai_provider')->toArray();

        $expectedProviders = ['openai', 'anthropic', 'gemini'];

        foreach ($expectedProviders as $provider) {
            $this->assertContains($provider, $providers, "AI provider '{$provider}' should be present in seeded data");
        }
    }

    public function test_seeder_handles_empty_database(): void
    {
        // データベースが空の状態でSeederを実行
        DB::table('ai_feedback')->truncate();

        $seeder = new AIFeedbackSeeder;
        $seeder->run();

        // データが正常に作成されることを確認
        $count = AIFeedback::count();
        $this->assertGreaterThan(0, $count, 'Seeder should create data even when database is empty');
    }
}
