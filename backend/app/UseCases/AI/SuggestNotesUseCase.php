<?php

namespace App\UseCases\AI;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\CostTrackingService;
use App\Services\AI\NoteSuggestionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuggestNotesUseCase
{
    private NoteSuggestionService $noteSuggestionService;

    private AIProviderFactory $providerFactory;

    private CostTrackingService $costTracker;

    private const DAILY_LIMIT = 100;

    private const MONTHLY_LIMIT = 1000;

    private const RATE_LIMIT_WINDOW = 3600; // 1時間

    public function __construct(
        NoteSuggestionService $noteSuggestionService,
        AIProviderFactory $providerFactory,
        CostTrackingService $costTracker
    ) {
        $this->noteSuggestionService = $noteSuggestionService;
        $this->providerFactory = $providerFactory;
        $this->costTracker = $costTracker;
    }

    public function execute(string $brandName, string $fragranceName, array $options = []): array
    {
        $userId = $options['user_id'] ?? null;

        // 入力値のサニタイゼーション
        $brandName = $this->sanitizeInput($brandName);
        $fragranceName = $this->sanitizeInput($fragranceName);

        // ユーザー制限チェック
        if ($userId && ! $this->checkUserLimits($userId)) {
            throw new \Exception(__('ai.errors.limit_exceeded'));
        }

        // レート制限チェック
        if ($userId && ! $this->checkRateLimit($userId)) {
            throw new \Exception(__('ai.errors.rate_limit_exceeded'));
        }

        try {
            $result = $this->noteSuggestionService->suggestNotes($brandName, $fragranceName, $options);

            // 結果を後処理
            $result = $this->postProcessResult($result, $options);

            // フィードバック情報を追加
            if ($userId) {
                $result['feedback_info'] = [
                    'can_provide_feedback' => true,
                    'feedback_url' => url("/api/ai/note-suggestion/feedback/{$userId}"),
                    'suggestion_id' => $this->generateSuggestionId($result),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('SuggestNotesUseCase: Note suggestion failed', [
                'brand' => $brandName,
                'fragrance' => $fragranceName,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function executeBatch(array $fragrances, array $options = []): array
    {
        $userId = $options['user_id'] ?? null;

        // バッチサイズ制限
        if (count($fragrances) > 20) {
            throw new \Exception(__('ai.errors.batch_size_exceeded'));
        }

        // ユーザー制限チェック（バッチ処理分）
        if ($userId && ! $this->checkUserLimits($userId, count($fragrances))) {
            throw new \Exception(__('ai.errors.batch_limit_exceeded'));
        }

        try {
            $result = $this->noteSuggestionService->batchSuggestNotes($fragrances, $options);

            // バッチ結果の後処理
            foreach ($result['results'] as &$itemResult) {
                if (isset($itemResult['notes'])) {
                    $itemResult = $this->postProcessResult($itemResult, $options);
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('SuggestNotesUseCase: Batch note suggestion failed', [
                'count' => count($fragrances),
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function processFeedback(int $userId, array $feedbackData): array
    {
        try {
            DB::beginTransaction();

            // フィードバックをDBに保存
            $feedbackId = DB::table('ai_note_suggestion_feedback')->insertGetId([
                'user_id' => $userId,
                'suggestion_id' => $feedbackData['suggestion_id'],
                'rating' => $feedbackData['rating'],
                'feedback_type' => $feedbackData['feedback_type'],
                'comments' => $feedbackData['comments'] ?? null,
                'corrected_notes' => isset($feedbackData['corrected_notes']) ? json_encode($feedbackData['corrected_notes']) : null,
                'corrected_attributes' => isset($feedbackData['corrected_attributes']) ? json_encode($feedbackData['corrected_attributes']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 学習データとしてキューに投入
            if ($feedbackData['rating'] >= 4 && isset($feedbackData['corrected_notes'])) {
                // 高評価の修正データは学習に使用
                // dispatch(new ProcessNoteLearningData($feedbackId));
            }

            DB::commit();

            return [
                'feedback_id' => $feedbackId,
                'status' => 'processed',
                'message' => __('ai.feedback.thank_you'),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('SuggestNotesUseCase: Feedback processing failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getAvailableProviders(): array
    {
        return $this->providerFactory->getAvailableProviders();
    }

    public function getDefaultProvider(): string
    {
        return $this->providerFactory->getDefaultProvider();
    }

    public function checkHealth(): array
    {
        $providers = $this->getAvailableProviders();
        $health = [];

        foreach ($providers as $provider) {
            try {
                // 簡単なヘルスチェック用リクエスト
                $testResult = $this->noteSuggestionService->suggestNotes(
                    'Test',
                    'Test',
                    ['provider' => $provider, 'language' => 'en']
                );

                $health[$provider] = [
                    'status' => 'healthy',
                    'response_time' => $testResult['response_time_ms'] ?? 0,
                    'last_check' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                $health[$provider] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'last_check' => now()->toISOString(),
                ];
            }
        }

        return [
            'providers' => $health,
            'overall_status' => $this->calculateOverallHealth($health),
            'checked_at' => now()->toISOString(),
        ];
    }

    public function getNoteCategories(): array
    {
        return [
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
    }

    public function findSimilarFragrances(array $notes, array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        $includeDiscontinued = $options['include_discontinued'] ?? false;

        // ノートベースの類似香水検索ロジック
        // 実装では実際のデータベースから検索
        $query = DB::table('fragrances as f')
            ->select(['f.id', 'f.name', 'b.name as brand_name', 'f.notes', 'f.discontinued_at'])
            ->join('brands as b', 'f.brand_id', '=', 'b.id')
            ->whereNotNull('f.notes');

        if (! $includeDiscontinued) {
            $query->whereNull('f.discontinued_at');
        }

        $fragrances = $query->limit($limit * 3)->get(); // 余裕をもって取得

        $similarities = [];
        foreach ($fragrances as $fragrance) {
            $fragranceNotes = json_decode($fragrance->notes, true) ?? [];
            $similarity = $this->calculateNoteSimilarity($notes, $fragranceNotes);

            if ($similarity > 0.3) { // 30%以上の類似度
                $similarities[] = [
                    'id' => $fragrance->id,
                    'name' => $fragrance->name,
                    'brand_name' => $fragrance->brand_name,
                    'similarity_score' => $similarity,
                    'notes' => $fragranceNotes,
                    'discontinued' => $fragrance->discontinued_at !== null,
                ];
            }
        }

        // 類似度でソート
        usort($similarities, function ($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });

        return [
            'similar_fragrances' => array_slice($similarities, 0, $limit),
            'total_found' => count($similarities),
            'search_notes' => $notes,
        ];
    }

    private function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }

    private function checkUserLimits(int $userId, int $requestCount = 1): bool
    {
        $today = now()->format('Y-m-d');
        $month = now()->format('Y-m');

        $dailyCount = Cache::get("note_suggestions:daily:{$userId}:{$today}", 0);
        $monthlyCount = Cache::get("note_suggestions:monthly:{$userId}:{$month}", 0);

        return ($dailyCount + $requestCount) <= self::DAILY_LIMIT &&
               ($monthlyCount + $requestCount) <= self::MONTHLY_LIMIT;
    }

    private function checkRateLimit(int $userId): bool
    {
        $key = "note_suggestions:rate_limit:{$userId}";
        $current = Cache::get($key, 0);

        if ($current >= 60) { // 1時間に60回まで
            return false;
        }

        Cache::put($key, $current + 1, self::RATE_LIMIT_WINDOW);

        return true;
    }

    private function postProcessResult(array $result, array $options): array
    {
        // 特定のオプションに基づく後処理
        if (! ($options['include_attributes'] ?? true)) {
            unset($result['attributes']);
        }

        if (isset($options['note_limit'])) {
            foreach (['top', 'middle', 'base'] as $category) {
                if (isset($result['notes'][$category])) {
                    $result['notes'][$category] = array_slice(
                        $result['notes'][$category],
                        0,
                        $options['note_limit']
                    );
                }
            }
        }

        return $result;
    }

    private function generateSuggestionId(array $result): string
    {
        $data = json_encode($result['notes'] ?? []).$result['metadata']['processed_at'];

        return 'note_'.substr(md5($data), 0, 16);
    }

    private function calculateOverallHealth(array $health): string
    {
        $total = count($health);
        $healthy = count(array_filter($health, fn ($h) => $h['status'] === 'healthy'));

        if ($healthy === $total) {
            return 'healthy';
        } elseif ($healthy > 0) {
            return 'degraded';
        } else {
            return 'unhealthy';
        }
    }

    private function calculateNoteSimilarity(array $notes1, array $notes2): float
    {
        $flat1 = $this->flattenNotes($notes1);
        $flat2 = $this->flattenNotes($notes2);

        if (empty($flat1) || empty($flat2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($flat1, $flat2));
        $union = count(array_unique(array_merge($flat1, $flat2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function flattenNotes(array $notes): array
    {
        $flat = [];

        foreach (['top', 'middle', 'base'] as $category) {
            if (isset($notes[$category]) && is_array($notes[$category])) {
                foreach ($notes[$category] as $note) {
                    if (is_string($note)) {
                        $flat[] = strtolower(trim($note));
                    } elseif (is_array($note) && isset($note['name'])) {
                        $flat[] = strtolower(trim($note['name']));
                    }
                }
            }
        }

        return array_unique($flat);
    }
}
