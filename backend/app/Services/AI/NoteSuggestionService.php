<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NoteSuggestionService
{
    private AIProviderFactory $providerFactory;

    private CostTrackingService $costTracker;

    private const CACHE_TTL = 3600; // 1時間

    private const MIN_BRAND_LENGTH = 2;

    private const MIN_FRAGRANCE_LENGTH = 2;

    private const NOTE_CATEGORIES = [
        'citrus' => ['bergamot', 'lemon', 'lime', 'orange', 'grapefruit', 'mandarin'],
        'floral' => ['rose', 'jasmine', 'lily', 'violet', 'peony', 'freesia'],
        'woody' => ['sandalwood', 'cedar', 'pine', 'oak', 'birch', 'bamboo'],
        'oriental' => ['vanilla', 'amber', 'musk', 'oud', 'benzoin', 'labdanum'],
        'fresh' => ['aquatic', 'marine', 'ozone', 'cucumber', 'mint', 'eucalyptus'],
        'spicy' => ['cinnamon', 'clove', 'pepper', 'cardamom', 'ginger', 'nutmeg'],
        'fruity' => ['apple', 'peach', 'berry', 'cherry', 'pear', 'plum'],
        'green' => ['grass', 'leaves', 'stems', 'moss', 'fern', 'basil'],
        'gourmand' => ['chocolate', 'caramel', 'honey', 'coffee', 'cake', 'sugar'],
    ];

    private const SEASONS = ['spring', 'summer', 'autumn', 'winter'];

    private const OCCASIONS = ['casual', 'business', 'formal', 'date', 'party', 'daily'];

    private const TIME_OF_DAY = ['morning', 'afternoon', 'evening', 'night'];

    private const INTENSITY_LEVELS = ['light', 'moderate', 'strong', 'very_strong'];

    public function __construct(
        AIProviderFactory $providerFactory,
        CostTrackingService $costTracker
    ) {
        $this->providerFactory = $providerFactory;
        $this->costTracker = $costTracker;
    }

    public function suggestNotes(string $brandName, string $fragranceName, array $options = []): array
    {
        if (strlen(trim($brandName)) < self::MIN_BRAND_LENGTH || strlen(trim($fragranceName)) < self::MIN_FRAGRANCE_LENGTH) {
            throw new \InvalidArgumentException('Brand name and fragrance name must be at least 2 characters long');
        }

        $language = $options['language'] ?? 'ja';
        $provider = $options['provider'] ?? null;
        $userId = $options['user_id'] ?? null;

        $providerName = $provider ?? $this->providerFactory->getDefaultProvider();
        $cacheKey = $this->generateCacheKey('note-suggestion', $brandName, $fragranceName, $providerName, $language);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($brandName, $fragranceName, $providerName, $language, $userId, $options) {
            try {
                $aiProvider = $this->providerFactory->create($providerName);
                $result = $aiProvider->suggestNotes($brandName, $fragranceName, array_merge($options, ['language' => $language]));

                $processedResult = $this->processNoteSuggestionResult($result, $brandName, $fragranceName, $language);

                if ($userId) {
                    $this->costTracker->trackUsage($userId, [
                        'operation_type' => 'note_suggestion',
                        'provider' => $providerName,
                        'cost' => $result['cost_estimate'] ?? 0.0,
                        'response_time_ms' => $result['response_time_ms'] ?? 0,
                        'metadata' => [
                            'brand_name' => $brandName,
                            'fragrance_name' => $fragranceName,
                            'language' => $language,
                            'note_count' => count($processedResult['notes']['top'] ?? []) +
                                          count($processedResult['notes']['middle'] ?? []) +
                                          count($processedResult['notes']['base'] ?? []),
                        ],
                    ]);
                }

                return $processedResult;

            } catch (\Exception $e) {
                Log::warning('NoteSuggestionService: AI provider failed, using fallback', [
                    'error' => $e->getMessage(),
                    'brand' => $brandName,
                    'fragrance' => $fragranceName,
                    'provider' => $providerName,
                ]);

                return $this->generateFallbackNoteSuggestion($brandName, $fragranceName, $language);
            }
        });
    }

    public function batchSuggestNotes(array $fragrances, array $options = []): array
    {
        $results = [];
        $totalCost = 0.0;
        $successCount = 0;

        foreach ($fragrances as $index => $fragrance) {
            if (! isset($fragrance['brand_name']) || ! isset($fragrance['fragrance_name'])) {
                $results[] = [
                    'error' => 'Missing required fields: brand_name and fragrance_name',
                    'index' => $index,
                ];

                continue;
            }

            try {
                $result = $this->suggestNotes(
                    $fragrance['brand_name'],
                    $fragrance['fragrance_name'],
                    $options
                );

                $results[] = array_merge($result, ['index' => $index]);
                $totalCost += $result['cost_estimate'] ?? 0.0;
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'error' => $e->getMessage(),
                    'index' => $index,
                ];
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total_processed' => count($fragrances),
                'successful_count' => $successCount,
                'failed_count' => count($fragrances) - $successCount,
                'total_cost_estimate' => $totalCost,
            ],
        ];
    }

    private function processNoteSuggestionResult(array $result, string $brandName, string $fragranceName, string $language): array
    {
        $notes = $result['notes'] ?? [];

        $processedNotes = [
            'top' => $this->processNoteCategory($notes['top'] ?? [], 'top'),
            'middle' => $this->processNoteCategory($notes['middle'] ?? [], 'middle'),
            'base' => $this->processNoteCategory($notes['base'] ?? [], 'base'),
        ];

        $attributes = $this->processAttributes($result['attributes'] ?? []);
        $confidence = $this->calculateOverallConfidence($processedNotes);

        return [
            'notes' => $processedNotes,
            'attributes' => $attributes,
            'confidence_score' => $confidence,
            'provider' => $result['provider'] ?? 'unknown',
            'response_time_ms' => $result['response_time_ms'] ?? 0,
            'cost_estimate' => $result['cost_estimate'] ?? 0.0,
            'metadata' => [
                'brand_name' => $brandName,
                'fragrance_name' => $fragranceName,
                'language' => $language,
                'processed_at' => now()->toISOString(),
            ],
        ];
    }

    private function processNoteCategory(array $notes, string $category): array
    {
        $processed = [];

        foreach ($notes as $note) {
            if (is_string($note)) {
                $processed[] = [
                    'name' => $this->normalizeNoteName($note),
                    'intensity' => 'moderate',
                    'confidence' => 0.7,
                    'category' => $this->detectNoteCategory($note),
                    'original_name' => $note,
                ];
            } elseif (is_array($note)) {
                $processed[] = [
                    'name' => $this->normalizeNoteName($note['name'] ?? ''),
                    'intensity' => $this->normalizeIntensity($note['intensity'] ?? 'moderate'),
                    'confidence' => $this->normalizeConfidence($note['confidence'] ?? 0.7),
                    'category' => $this->detectNoteCategory($note['name'] ?? ''),
                    'original_name' => $note['name'] ?? '',
                ];
            }
        }

        // カテゴリー別にソート（信頼度順）
        usort($processed, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($processed, 0, 8); // 最大8個まで
    }

    private function processAttributes(array $attributes): array
    {
        return [
            'seasons' => $this->validateAndFilterArray($attributes['seasons'] ?? [], self::SEASONS),
            'occasions' => $this->validateAndFilterArray($attributes['occasions'] ?? [], self::OCCASIONS),
            'time_of_day' => $this->validateAndFilterArray($attributes['time_of_day'] ?? [], self::TIME_OF_DAY),
            'intensity_rating' => $this->normalizeIntensityRating($attributes['intensity_rating'] ?? 'moderate'),
            'longevity_hours' => $this->normalizeLongevity($attributes['longevity_hours'] ?? 6),
            'sillage' => $this->normalizeSillage($attributes['sillage'] ?? 'moderate'),
        ];
    }

    private function normalizeNoteName(string $name): string
    {
        $name = trim(strtolower($name));

        // 一般的な正規化ルール
        $rules = [
            'bergamot' => ['bergamotte', 'bergamot'],
            'rose' => ['rosa', 'rose'],
            'sandalwood' => ['sandal', 'sandalwood'],
            'vanilla' => ['vanille', 'vanilla'],
            'jasmine' => ['jasmin', 'jasmine'],
            'cedar' => ['cedarwood', 'cedar'],
            'musk' => ['white musk', 'musk'],
        ];

        foreach ($rules as $normalized => $variations) {
            if (in_array($name, $variations)) {
                return $normalized;
            }
        }

        return $name;
    }

    private function detectNoteCategory(string $noteName): string
    {
        $name = strtolower(trim($noteName));

        foreach (self::NOTE_CATEGORIES as $category => $notes) {
            if (in_array($name, $notes) || str_contains($name, $category)) {
                return $category;
            }
        }

        return 'other';
    }

    private function normalizeIntensity(string $intensity): string
    {
        $intensity = strtolower(trim($intensity));

        if (in_array($intensity, self::INTENSITY_LEVELS)) {
            return $intensity;
        }

        // マッピングルール
        $mappings = [
            'weak' => 'light',
            'mild' => 'light',
            'medium' => 'moderate',
            'heavy' => 'strong',
            'intense' => 'very_strong',
            'powerful' => 'very_strong',
        ];

        return $mappings[$intensity] ?? 'moderate';
    }

    private function normalizeConfidence(float $confidence): float
    {
        return max(0.0, min(1.0, $confidence));
    }

    private function normalizeIntensityRating(string $rating): string
    {
        $mappings = [
            'light' => 'light',
            'moderate' => 'moderate',
            'strong' => 'strong',
            'very strong' => 'very_strong',
            'beast mode' => 'very_strong',
        ];

        return $mappings[strtolower(trim($rating))] ?? 'moderate';
    }

    private function normalizeLongevity(int $hours): int
    {
        return max(1, min(24, $hours));
    }

    private function normalizeSillage(string $sillage): string
    {
        $mappings = [
            'intimate' => 'intimate',
            'moderate' => 'moderate',
            'heavy' => 'heavy',
            'nuclear' => 'heavy',
        ];

        return $mappings[strtolower(trim($sillage))] ?? 'moderate';
    }

    private function validateAndFilterArray(array $items, array $validOptions): array
    {
        return array_values(array_intersect($items, $validOptions));
    }

    private function calculateOverallConfidence(array $notes): float
    {
        $allNotes = array_merge(
            $notes['top'] ?? [],
            $notes['middle'] ?? [],
            $notes['base'] ?? []
        );

        if (empty($allNotes)) {
            return 0.0;
        }

        $totalConfidence = array_sum(array_column($allNotes, 'confidence'));

        return round($totalConfidence / count($allNotes), 2);
    }

    private function generateFallbackNoteSuggestion(string $brandName, string $fragranceName, string $language): array
    {
        // ブランドベースの基本的なノート推定
        $fallbackNotes = $this->getFallbackNotesByBrand($brandName);

        return [
            'notes' => $fallbackNotes,
            'attributes' => [
                'seasons' => ['spring', 'summer'],
                'occasions' => ['casual', 'daily'],
                'time_of_day' => ['morning', 'afternoon'],
                'intensity_rating' => 'moderate',
                'longevity_hours' => 6,
                'sillage' => 'moderate',
            ],
            'confidence_score' => 0.5,
            'provider' => 'fallback',
            'response_time_ms' => 10,
            'cost_estimate' => 0.0,
            'metadata' => [
                'brand_name' => $brandName,
                'fragrance_name' => $fragranceName,
                'language' => $language,
                'fallback_reason' => 'AI provider unavailable',
                'processed_at' => now()->toISOString(),
            ],
        ];
    }

    private function getFallbackNotesByBrand(string $brandName): array
    {
        $brand = strtolower(trim($brandName));

        // ブランド特徴的なノートパターン
        $brandPatterns = [
            'chanel' => [
                'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.8, 'category' => 'citrus', 'original_name' => 'bergamot']],
                'middle' => [['name' => 'rose', 'intensity' => 'strong', 'confidence' => 0.9, 'category' => 'floral', 'original_name' => 'rose']],
                'base' => [['name' => 'sandalwood', 'intensity' => 'moderate', 'confidence' => 0.7, 'category' => 'woody', 'original_name' => 'sandalwood']],
            ],
            'dior' => [
                'top' => [['name' => 'lemon', 'intensity' => 'moderate', 'confidence' => 0.7, 'category' => 'citrus', 'original_name' => 'lemon']],
                'middle' => [['name' => 'jasmine', 'intensity' => 'strong', 'confidence' => 0.8, 'category' => 'floral', 'original_name' => 'jasmine']],
                'base' => [['name' => 'musk', 'intensity' => 'moderate', 'confidence' => 0.6, 'category' => 'oriental', 'original_name' => 'musk']],
            ],
        ];

        if (isset($brandPatterns[$brand])) {
            return $brandPatterns[$brand];
        }

        // デフォルトの汎用的なノート構成
        return [
            'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.6, 'category' => 'citrus', 'original_name' => 'bergamot']],
            'middle' => [['name' => 'rose', 'intensity' => 'moderate', 'confidence' => 0.6, 'category' => 'floral', 'original_name' => 'rose']],
            'base' => [['name' => 'cedar', 'intensity' => 'moderate', 'confidence' => 0.6, 'category' => 'woody', 'original_name' => 'cedar']],
        ];
    }

    private function generateCacheKey(string $operation, string $brandName, string $fragranceName, string $provider, string $language): string
    {
        $data = $operation.':'.$brandName.':'.$fragranceName.':'.$provider.':'.$language;

        return 'ai:'.$operation.':'.md5($data);
    }
}
