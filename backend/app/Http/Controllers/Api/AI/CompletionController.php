<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\UseCases\AI\CompleteFragranceUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompletionController extends Controller
{
    private CompleteFragranceUseCase $completeFragranceUseCase;

    public function __construct(CompleteFragranceUseCase $completeFragranceUseCase)
    {
        $this->completeFragranceUseCase = $completeFragranceUseCase;
    }

    /**
     * リアルタイム補完API
     */
    public function complete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
            'type' => 'required|string|in:brand,fragrance',
            'limit' => 'nullable|integer|min:1|max:20',
            'language' => 'nullable|string|in:ja,en',
            'provider' => 'nullable|string|in:openai,anthropic',
        ], [
            'query.required' => __('ai.validation.query_required'),
            'query.min' => __('ai.validation.query_min_length'),
            'query.max' => __('ai.validation.query_max_length'),
            'type.required' => __('ai.validation.type_required'),
            'type.in' => __('ai.validation.type_invalid'),
            'limit.integer' => __('ai.validation.limit_integer'),
            'limit.min' => __('ai.validation.limit_min'),
            'limit.max' => __('ai.validation.limit_max'),
            'language.in' => __('ai.validation.language_invalid'),
            'provider.in' => __('ai.validation.provider_invalid'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('ai.validation.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->completeFragranceUseCase->execute(
                $request->input('query'),
                [
                    'type' => $request->input('type'),
                    'limit' => $request->input('limit', 10),
                    'language' => $request->input('language', app()->getLocale()),
                    'provider' => $request->input('provider'),
                    'user_id' => $request->user()?->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.completion_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 一括補完API
     */
    public function batchComplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'queries' => 'required|array|min:1|max:10',
            'queries.*' => 'required|string|min:2|max:100',
            'type' => 'required|string|in:brand,fragrance',
            'language' => 'nullable|string|in:ja,en',
            'provider' => 'nullable|string|in:openai,anthropic',
        ], [
            'queries.required' => __('ai.validation.queries_required'),
            'queries.array' => __('ai.validation.queries_array'),
            'queries.min' => __('ai.validation.queries_min'),
            'queries.max' => __('ai.validation.queries_max'),
            'queries.*.required' => __('ai.validation.query_required'),
            'queries.*.min' => __('ai.validation.query_min_length'),
            'queries.*.max' => __('ai.validation.query_max_length'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('ai.validation.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->completeFragranceUseCase->executeBatch(
                $request->input('queries'),
                [
                    'type' => $request->input('type'),
                    'language' => $request->input('language', app()->getLocale()),
                    'provider' => $request->input('provider'),
                    'user_id' => $request->user()?->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.batch_completion_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 利用可能なプロバイダー一覧取得
     */
    public function providers(): JsonResponse
    {
        try {
            $result = $this->completeFragranceUseCase->getAvailableProviders();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.providers_fetch_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ヘルスチェック
     */
    public function health(Request $request): JsonResponse
    {
        $provider = $request->input('provider');

        try {
            $result = $this->completeFragranceUseCase->healthCheck($provider);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.health_check_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
