<?php

namespace Database\Seeders;

use App\Models\AIFeedback;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AIFeedbackSeeder extends Seeder
{
    /**
     * 香水名サンプル.jsonのデータを基に初期学習データを作成
     */
    public function run(): void
    {
        $fragranceData = [
            ['brand' => 'Creed', 'name' => 'アバントゥス', 'type' => 'Eau de Parfum'],
            ['brand' => 'Givenchy', 'name' => 'パイ', 'type' => 'Eau de Toilette'],
            ['brand' => 'Bdk Parfums', 'name' => 'ルージュ スモーキング', 'type' => 'Eau de Parfum'],
            ['brand' => 'Dusita', 'name' => 'ラ ラプソディ ノワール', 'type' => 'Eau de Parfum'],
            ['brand' => 'CHANEL', 'name' => 'ブルー ドゥ シャネル', 'type' => 'Parfum'],
            ['brand' => 'DIOR', 'name' => 'ソヴァージュ', 'type' => 'Parfum'],
            ['brand' => 'CLEAN', 'name' => 'リザーブ スパークリングシュガー', 'type' => 'Eau de Parfum'],
            ['brand' => "PENHALIGON'S", 'name' => 'ザ トラジェディ オブ ロード ジョージ', 'type' => 'Eau de Parfum'],
            ['brand' => 'PARLE MOI DE PARFUM', 'name' => 'ギモーヴ ドゥ ノエル', 'type' => 'Eau de Parfum'],
            ['brand' => 'PARLE MOI DE PARFUM', 'name' => 'ウェイクアップ ワールド', 'type' => 'Eau de Parfum'],
            ['brand' => 'KILIAN PARIS', 'name' => 'エンジェルズ シェア', 'type' => 'Eau de Parfum'],
            ['brand' => 'Dolce & Gabbana', 'name' => 'ディヴォーション プールオム', 'type' => 'Eau de Parfum'],
            ['brand' => 'Guerlain', 'name' => 'ロム イデアル', 'type' => 'Parfum'],
            ['brand' => 'Diptyque', 'name' => 'フルールドゥポー', 'type' => 'Eau de Parfum'],
            ['brand' => 'Maison Margiela', 'name' => 'レプリカ ネバーエンディング サマー', 'type' => 'Eau de Toilette'],
            ['brand' => 'Maison Margiela', 'name' => 'レプリカ レイジーサンデー モーニング', 'type' => 'Eau de Toilette'],
            ['brand' => 'imp.', 'name' => 'ウィステリアブロッサム', 'type' => 'Eau de Parfum'],
            ['brand' => 'TOM FORD', 'name' => 'タバコ バニラ', 'type' => 'Eau de Parfum'],
            ['brand' => 'THÉOBROMA', 'name' => 'テオブロマ ポムカネル', 'type' => 'Eau de Parfum'],
            ['brand' => 'Hermès', 'name' => '李氏の庭', 'type' => 'Eau de Toilette'],
            ['brand' => 'Hermès', 'name' => 'テール ドゥ エルメス', 'type' => 'Eau de Toilette'],
            ['brand' => "John's Blend", 'name' => 'ホワイトムスク', 'type' => 'Eau de Parfum'],
            ['brand' => 'KLOWER PANDOR', 'name' => 'エンカウンター 1310', 'type' => 'Eau de Parfum'],
            ['brand' => 'Fatalite', 'name' => '聡慧なる手綱', 'type' => 'Eau de Parfum'],
            ['brand' => 'J-scent', 'name' => '花見酒', 'type' => 'Eau de Parfum'],
            ['brand' => 'J-scent', 'name' => '珈琲', 'type' => 'Eau de Parfum'],
            ['brand' => 'Carthusia', 'name' => 'メディテラネオ', 'type' => 'Eau de Parfum'],
            ['brand' => 'EDIT(h)', 'name' => 'カクテルレーン', 'type' => 'Eau de Parfum'],
        ];

        // ブランド名での検索成功パターンを作成
        $this->createBrandSearchPatterns($fragranceData);

        // 香水名での検索成功パターンを作成
        $this->createFragranceSearchPatterns($fragranceData);

        // 英語での香水名検索成功パターンを作成
        $this->createEnglishFragranceSearchPatterns($fragranceData);

        // 香りの特徴での検索成功パターンを作成
        $this->createScentSearchPatterns($fragranceData);
    }

    private function createBrandSearchPatterns(array $fragranceData): void
    {
        $brandPatterns = [
            'CHANEL' => ['シャネル', 'chanel', 'CHANEL'],
            'TOM FORD' => ['トムフォード', 'Tom Ford', 'tom ford'],
            'Hermès' => ['エルメス', 'hermes', 'Hermès'],
            'DIOR' => ['ディオール', 'dior', 'DIOR'],
            'Creed' => ['クリード', 'creed', 'Creed'],
            'Maison Margiela' => ['メゾンマルジェラ', 'Maison Margiela'],
            'Diptyque' => ['ディプティック', 'diptyque'],
            'Guerlain' => ['ゲラン', 'guerlain'],
        ];

        foreach ($brandPatterns as $brand => $queries) {
            $brandFragrances = array_filter($fragranceData, fn ($f) => $f['brand'] === $brand);

            foreach ($queries as $query) {
                $selectedFragrance = collect($brandFragrances)->random();

                AIFeedback::create([
                    'user_id' => null,
                    'session_id' => Str::uuid(),
                    'operation_type' => 'completion',
                    'query_type' => 'brand_search',
                    'query' => $query,
                    'request_params' => [
                        'type' => 'fragrance',
                        'limit' => 12,
                        'language' => 'ja',
                    ],
                    'ai_provider' => collect(['openai', 'anthropic', 'gemini'])->random(),
                    'ai_model' => 'claude-3-haiku-20240307',
                    'ai_suggestions' => [
                        [
                            'text' => $selectedFragrance['name'],
                            'text_en' => $this->getEnglishName($selectedFragrance['name']),
                            'brand_name' => $this->getJapaneseBrand($selectedFragrance['brand']),
                            'brand_name_en' => $selectedFragrance['brand'],
                            'confidence' => rand(85, 98) / 100,
                            'type' => 'exact_match',
                        ],
                    ],
                    'user_action' => 'selected',
                    'selected_suggestion' => [
                        'text' => $selectedFragrance['name'],
                        'text_en' => $this->getEnglishName($selectedFragrance['name']),
                        'brand_name' => $this->getJapaneseBrand($selectedFragrance['brand']),
                        'brand_name_en' => $selectedFragrance['brand'],
                        'confidence' => rand(85, 98) / 100,
                        'type' => 'exact_match',
                    ],
                    'final_input' => $selectedFragrance['name'],
                    'relevance_score' => rand(90, 100) / 100,
                    'was_helpful' => true,
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'ip_address' => '127.0.0.1',
                    'context_data' => [
                        'brand' => $selectedFragrance['brand'],
                        'type' => $selectedFragrance['type'],
                    ],
                ]);
            }
        }
    }

    private function createFragranceSearchPatterns(array $fragranceData): void
    {
        $fragranceQueries = [
            'アバントゥス' => ['fragrance' => 'アバントゥス', 'brand' => 'Creed'],
            'ブルー ドゥ シャネル' => ['fragrance' => 'ブルー ドゥ シャネル', 'brand' => 'CHANEL'],
            'タバコ バニラ' => ['fragrance' => 'タバコ バニラ', 'brand' => 'TOM FORD'],
            'ソヴァージュ' => ['fragrance' => 'ソヴァージュ', 'brand' => 'DIOR'],
            'テール ドゥ エルメス' => ['fragrance' => 'テール ドゥ エルメス', 'brand' => 'Hermès'],
            'エンジェルズ シェア' => ['fragrance' => 'エンジェルズ シェア', 'brand' => 'KILIAN PARIS'],
            'フルールドゥポー' => ['fragrance' => 'フルールドゥポー', 'brand' => 'Diptyque'],
            '花見酒' => ['fragrance' => '花見酒', 'brand' => 'J-scent'],
            '珈琲' => ['fragrance' => '珈琲', 'brand' => 'J-scent'],
            'レイジーサンデー' => ['fragrance' => 'レプリカ レイジーサンデー モーニング', 'brand' => 'Maison Margiela'],
        ];

        foreach ($fragranceQueries as $query => $expected) {
            AIFeedback::create([
                'user_id' => null,
                'session_id' => Str::uuid(),
                'operation_type' => 'completion',
                'query_type' => 'fragrance_search',
                'query' => $query,
                'request_params' => [
                    'type' => 'fragrance',
                    'limit' => 12,
                    'language' => 'ja',
                ],
                'ai_provider' => 'anthropic',
                'ai_model' => 'claude-3-haiku-20240307',
                'ai_suggestions' => [
                    [
                        'text' => $expected['fragrance'],
                        'text_en' => $this->getEnglishName($expected['fragrance']),
                        'brand_name' => $this->getJapaneseBrand($expected['brand']),
                        'brand_name_en' => $expected['brand'],
                        'confidence' => rand(92, 99) / 100,
                        'type' => 'exact_match',
                    ],
                ],
                'user_action' => 'selected',
                'selected_suggestion' => [
                    'text' => $expected['fragrance'],
                    'text_en' => $this->getEnglishName($expected['fragrance']),
                    'brand_name' => $this->getJapaneseBrand($expected['brand']),
                    'brand_name_en' => $expected['brand'],
                    'confidence' => rand(92, 99) / 100,
                    'type' => 'exact_match',
                ],
                'final_input' => $expected['fragrance'],
                'relevance_score' => rand(95, 100) / 100,
                'was_helpful' => true,
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'ip_address' => '127.0.0.1',
                'context_data' => [
                    'query_type' => 'fragrance_name',
                ],
            ]);
        }
    }

    private function createEnglishFragranceSearchPatterns(array $fragranceData): void
    {
        $englishFragranceQueries = [
            'Aventus' => ['fragrance' => 'アバントゥス', 'brand' => 'Creed'],
            'Blue de Chanel' => ['fragrance' => 'ブルー ドゥ シャネル', 'brand' => 'CHANEL'],
            'Bleu de Chanel' => ['fragrance' => 'ブルー ドゥ シャネル', 'brand' => 'CHANEL'],
            'Tobacco Vanille' => ['fragrance' => 'タバコ バニラ', 'brand' => 'TOM FORD'],
            'Sauvage' => ['fragrance' => 'ソヴァージュ', 'brand' => 'DIOR'],
            'Terre d\'Hermes' => ['fragrance' => 'テール ドゥ エルメス', 'brand' => 'Hermès'],
            'Angels Share' => ['fragrance' => 'エンジェルズ シェア', 'brand' => 'KILIAN PARIS'],
            'Angel\'s Share' => ['fragrance' => 'エンジェルズ シェア', 'brand' => 'KILIAN PARIS'],
            'Fleur de Peau' => ['fragrance' => 'フルールドゥポー', 'brand' => 'Diptyque'],
            'Rouge Smoking' => ['fragrance' => 'ルージュ スモーキング', 'brand' => 'Bdk Parfums'],
            'White Musk' => ['fragrance' => 'ホワイトムスク', 'brand' => 'John\'s Blend'],
            'Lazy Sunday Morning' => ['fragrance' => 'レプリカ レイジーサンデー モーニング', 'brand' => 'Maison Margiela'],
            'Never Ending Summer' => ['fragrance' => 'レプリカ ネバーエンディング サマー', 'brand' => 'Maison Margiela'],
            'L\'Homme Ideal' => ['fragrance' => 'ロム イデアル', 'brand' => 'Guerlain'],
            'Homme Ideal' => ['fragrance' => 'ロム イデアル', 'brand' => 'Guerlain'],
            'Pi' => ['fragrance' => 'パイ', 'brand' => 'Givenchy'],
            'Un Jardin sur le Toit' => ['fragrance' => '李氏の庭', 'brand' => 'Hermès'],
            'Hanami Sake' => ['fragrance' => '花見酒', 'brand' => 'J-scent'],
            'Coffee' => ['fragrance' => '珈琲', 'brand' => 'J-scent'],
            'Mediterraneo' => ['fragrance' => 'メディテラネオ', 'brand' => 'Carthusia'],
        ];

        foreach ($englishFragranceQueries as $query => $expected) {
            AIFeedback::create([
                'user_id' => null,
                'session_id' => Str::uuid(),
                'operation_type' => 'completion',
                'query_type' => 'english_fragrance_search',
                'query' => $query,
                'request_params' => [
                    'type' => 'fragrance',
                    'limit' => 12,
                    'language' => 'en',
                ],
                'ai_provider' => 'anthropic',
                'ai_model' => 'claude-3-haiku-20240307',
                'ai_suggestions' => [
                    [
                        'text' => $expected['fragrance'],
                        'text_en' => $this->getEnglishName($expected['fragrance']),
                        'brand_name' => $this->getJapaneseBrand($expected['brand']),
                        'brand_name_en' => $expected['brand'],
                        'confidence' => rand(88, 96) / 100,
                        'type' => 'exact_match',
                    ],
                ],
                'user_action' => 'selected',
                'selected_suggestion' => [
                    'text' => $expected['fragrance'],
                    'text_en' => $this->getEnglishName($expected['fragrance']),
                    'brand_name' => $this->getJapaneseBrand($expected['brand']),
                    'brand_name_en' => $expected['brand'],
                    'confidence' => rand(88, 96) / 100,
                    'type' => 'exact_match',
                ],
                'final_input' => $expected['fragrance'],
                'relevance_score' => rand(92, 100) / 100,
                'was_helpful' => true,
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'ip_address' => '127.0.0.1',
                'context_data' => [
                    'query_type' => 'english_fragrance_name',
                    'language' => 'en',
                ],
            ]);
        }
    }

    private function createScentSearchPatterns(array $fragranceData): void
    {
        $scentPatterns = [
            'バニラ' => [
                ['fragrance' => 'タバコ バニラ', 'brand' => 'TOM FORD'],
                ['fragrance' => 'テオブロマ ポムカネル', 'brand' => 'THÉOBROMA'],
            ],
            'フローラル' => [
                ['fragrance' => 'フルールドゥポー', 'brand' => 'Diptyque'],
                ['fragrance' => 'ウィステリアブロッサム', 'brand' => 'imp.'],
            ],
            'ムスク' => [
                ['fragrance' => 'ホワイトムスク', 'brand' => 'John\'s Blend'],
                ['fragrance' => 'リザーブ スパークリングシュガー', 'brand' => 'CLEAN'],
            ],
            'シトラス' => [
                ['fragrance' => 'ブルー ドゥ シャネル', 'brand' => 'CHANEL'],
                ['fragrance' => 'テール ドゥ エルメス', 'brand' => 'Hermès'],
            ],
            'ウッディ' => [
                ['fragrance' => 'ソヴァージュ', 'brand' => 'DIOR'],
                ['fragrance' => 'ロム イデアル', 'brand' => 'Guerlain'],
            ],
        ];

        foreach ($scentPatterns as $scent => $fragrances) {
            foreach ($fragrances as $fragrance) {
                AIFeedback::create([
                    'user_id' => null,
                    'session_id' => Str::uuid(),
                    'operation_type' => 'completion',
                    'query_type' => 'scent_characteristic',
                    'query' => $scent,
                    'request_params' => [
                        'type' => 'fragrance',
                        'limit' => 12,
                        'language' => 'ja',
                    ],
                    'ai_provider' => collect(['openai', 'anthropic', 'gemini'])->random(),
                    'ai_model' => 'claude-3-haiku-20240307',
                    'ai_suggestions' => [
                        [
                            'text' => $this->removeFragranceBrandName($fragrance['fragrance'], $fragrance['brand']),
                            'text_en' => $this->getEnglishName($fragrance['fragrance']),
                            'brand_name' => $this->getJapaneseBrand($fragrance['brand']),
                            'brand_name_en' => $this->getEnglishBrand($fragrance['brand']),
                            'confidence' => rand(75, 90) / 100,
                            'type' => 'scent_match',
                        ],
                    ],
                    'user_action' => 'selected',
                    'selected_suggestion' => [
                        'text' => $this->removeFragranceBrandName($fragrance['fragrance'], $fragrance['brand']),
                        'text_en' => $this->getEnglishName($fragrance['fragrance']),
                        'brand_name' => $this->getJapaneseBrand($fragrance['brand']),
                        'brand_name_en' => $this->getEnglishBrand($fragrance['brand']),
                        'confidence' => rand(75, 90) / 100,
                        'type' => 'scent_match',
                    ],
                    'final_input' => $this->removeFragranceBrandName($fragrance['fragrance'], $fragrance['brand']),
                    'relevance_score' => rand(80, 95) / 100,
                    'was_helpful' => true,
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'ip_address' => '127.0.0.1',
                    'context_data' => [
                        'scent_type' => $scent,
                        'query_type' => 'scent_characteristic',
                    ],
                ]);
            }
        }
    }

    /**
     * 英語名を取得（簡易版）
     */
    private function getEnglishName(string $japaneseName): string
    {
        $nameMapping = [
            'アバントゥス' => 'Aventus',
            'ブルー ドゥ シャネル' => 'Bleu de Chanel',
            'タバコ バニラ' => 'Tobacco Vanille',
            'ソヴァージュ' => 'Sauvage',
            'テール ドゥ エルメス' => 'Terre d\'Hermès',
            'エンジェルズ シェア' => 'Angels Share',
            'フルールドゥポー' => 'Fleur de Peau',
            '花見酒' => 'Hanami Sake',
            '珈琲' => 'Coffee',
            'レプリカ レイジーサンデー モーニング' => 'REPLICA Lazy Sunday Morning',
            'レプリカ ネバーエンディング サマー' => 'REPLICA Never Ending Summer',
            'パイ' => 'Pi',
            '李氏の庭' => 'Un Jardin sur le Toit',
            'ホワイトムスク' => 'White Musk',
            'ロム イデアル' => 'L\'Homme Idéal',
            'メディテラネオ' => 'Mediterraneo',
        ];

        return $nameMapping[$japaneseName] ?? $japaneseName;
    }

    /**
     * 英語ブランド名を取得
     */
    private function getEnglishBrand(string $japaneseBrand): string
    {
        $brandMapping = [
            'シャネル' => 'CHANEL',
            'ディオール' => 'DIOR',
            'トム フォード' => 'TOM FORD',
            'TOM FORD' => 'TOM FORD',
            'エルメス' => 'Hermès',
            'Hermès' => 'Hermès',
            'クリード' => 'Creed',
            'Creed' => 'Creed',
            'メゾン マルジェラ' => 'Maison Margiela',
            'ディプティック' => 'Diptyque',
            'Diptyque' => 'Diptyque',
            'ゲラン' => 'Guerlain',
            'ジバンシー' => 'Givenchy',
            'キリアン パリ' => 'KILIAN PARIS',
            'J-scent' => 'J-scent',
            'カルトゥージア' => 'Carthusia',
            'ジョンズブレンド' => 'John\'s Blend',
            'John\'s Blend' => 'John\'s Blend',
            'THÉOBROMA' => 'THÉOBROMA',
            'imp.' => 'imp.',
            'CLEAN' => 'CLEAN',
        ];

        return $brandMapping[$japaneseBrand] ?? $japaneseBrand;
    }

    /**
     * 日本語ブランド名を取得
     */
    private function getJapaneseBrand(string $englishBrand): string
    {
        $brandMapping = [
            'CHANEL' => 'シャネル',
            'DIOR' => 'ディオール',
            'TOM FORD' => 'トム フォード',
            'Hermès' => 'エルメス',
            'Creed' => 'クリード',
            'Maison Margiela' => 'メゾン マルジェラ',
            'Diptyque' => 'ディプティック',
            'Guerlain' => 'ゲラン',
            'Givenchy' => 'ジバンシー',
            'KILIAN PARIS' => 'キリアン パリ',
            'J-scent' => 'J-scent',
            'Carthusia' => 'カルトゥージア',
            'John\'s Blend' => 'ジョンズブレンド',
        ];

        return $brandMapping[$englishBrand] ?? $englishBrand;
    }

    /**
     * 香水名からブランド名部分を削除して分離された香水名を取得
     */
    private function removeFragranceBrandName(string $fragranceName, string $brandName): string
    {
        $japaneseBrand = $this->getJapaneseBrand($brandName);

        // 日本語ブランド名が香水名に含まれている場合は削除
        $cleanName = str_replace($japaneseBrand, '', $fragranceName);
        $cleanName = str_replace($brandName, '', $cleanName);

        // 特定の香水名の手動マッピング（分離形式）
        $manualMapping = [
            'ブルー ドゥ シャネル' => 'ブルー', // ブランド名部分を削除
            'テール ドゥ エルメス' => 'テール', // ブランド名部分を削除
            'ロム イデアル' => 'イデアル', // ブランド名部分を削除
            'ソヴァージュ' => 'ソヴァージュ', // 既に分離済み
            'タバコ バニラ' => 'タバコ バニラ', // 既に分離済み
            'テオブロマ ポムカネル' => 'ポムカネル', // ブランド名部分を削除
            'フルールドゥポー' => 'フルールドゥポー', // 既に分離済み
            'ウィステリアブロッサム' => 'ウィステリアブロッサム', // 既に分離済み
            'ホワイトムスク' => 'ホワイトムスク', // 既に分離済み
            'リザーブ スパークリングシュガー' => 'リザーブ スパークリングシュガー', // 既に分離済み
        ];

        if (isset($manualMapping[$fragranceName])) {
            return $manualMapping[$fragranceName];
        }

        return trim($cleanName) ?: $fragranceName;
    }
}
