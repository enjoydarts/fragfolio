<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FragranceNormalizationController extends Controller
{
    public function __construct(
        private AIService $aiService
    ) {}

    public function normalize(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'brand_name' => 'required|string|max:255',
            'fragrance_name' => 'required|string|max:255',
            'ai_provider' => 'nullable|in:openai,anthropic',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $brandName = $request->input('brand_name');
            $fragranceName = $request->input('fragrance_name');
            $provider = $request->input('ai_provider');

            // AI正規化を実行
            $normalizedData = $this->aiService->normalizeFragranceData(
                $brandName,
                $fragranceName,
                $provider
            );

            // 正規化ログを保存
            $logId = $this->saveNormalizationLog(
                $brandName,
                $fragranceName,
                $normalizedData,
                $provider ?: config('services.ai.default_provider'),
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'data' => $normalizedData,
                'log_id' => $logId,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI正規化に失敗しました: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getAvailableProviders(): JsonResponse
    {
        $providers = $this->aiService->getAvailableProviders();

        return response()->json([
            'success' => true,
            'providers' => $providers,
            'default_provider' => config('services.ai.default_provider'),
        ]);
    }

    public function getNormalizationHistory(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $query = DB::table('ai_normalization_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($request->has('limit')) {
            $query->limit($request->integer('limit', 50));
        }

        $logs = $query->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    private function saveNormalizationLog(
        string $originalBrand,
        string $originalFragrance,
        array $normalizedData,
        string $provider,
        ?int $userId
    ): int {
        return DB::table('ai_normalization_logs')->insertGetId([
            'user_id' => $userId,
            'original_brand_name' => $originalBrand,
            'original_fragrance_name' => $originalFragrance,
            'normalized_brand_name' => $normalizedData['normalized_brand'] ?? null,
            'normalized_fragrance_name' => $normalizedData['normalized_fragrance_name'] ?? null,
            'concentration_type' => $normalizedData['concentration_type'] ?? null,
            'launch_year' => $normalizedData['launch_year'] ?? null,
            'fragrance_family' => $normalizedData['fragrance_family'] ?? null,
            'top_notes' => json_encode($normalizedData['top_notes'] ?? []),
            'middle_notes' => json_encode($normalizedData['middle_notes'] ?? []),
            'base_notes' => json_encode($normalizedData['base_notes'] ?? []),
            'suitable_seasons' => json_encode($normalizedData['suitable_seasons'] ?? []),
            'suitable_scenes' => json_encode($normalizedData['suitable_scenes'] ?? []),
            'description_ja' => $normalizedData['description_ja'] ?? null,
            'description_en' => $normalizedData['description_en'] ?? null,
            'ai_provider' => $provider,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
