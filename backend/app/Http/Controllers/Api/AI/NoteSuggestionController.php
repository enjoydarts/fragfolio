<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\UseCases\AI\SuggestNotesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteSuggestionController extends Controller
{
    private SuggestNotesUseCase $suggestNotesUseCase;

    public function __construct(SuggestNotesUseCase $suggestNotesUseCase)
    {
        $this->suggestNotesUseCase = $suggestNotesUseCase;
    }

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand_name' => 'required|string|min:2|max:100',
            'fragrance_name' => 'required|string|min:2|max:100',
            'provider' => ['sometimes', 'string', Rule::in(['openai', 'anthropic'])],
            'language' => ['sometimes', 'string', Rule::in(['ja', 'en'])],
            'include_attributes' => 'sometimes|boolean',
            'note_limit' => 'sometimes|integer|min:1|max:10',
        ]);

        try {
            $userId = $request->user()?->id;
            $options = array_merge($validated, compact('userId'));

            $result = $this->suggestNotesUseCase->execute(
                $validated['brand_name'],
                $validated['fragrance_name'],
                $options
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.validation.invalid_input'),
                'error' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.suggestion_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    public function batchSuggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fragrances' => 'required|array|min:1|max:20',
            'fragrances.*.brand_name' => 'required|string|min:2|max:100',
            'fragrances.*.fragrance_name' => 'required|string|min:2|max:100',
            'provider' => ['sometimes', 'string', Rule::in(['openai', 'anthropic'])],
            'language' => ['sometimes', 'string', Rule::in(['ja', 'en'])],
            'include_attributes' => 'sometimes|boolean',
            'note_limit' => 'sometimes|integer|min:1|max:10',
        ]);

        try {
            $userId = $request->user()?->id;
            $options = array_merge(
                collect($validated)->except('fragrances')->toArray(),
                compact('userId')
            );

            $result = $this->suggestNotesUseCase->executeBatch(
                $validated['fragrances'],
                $options
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.batch_suggestion_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    public function providers(): JsonResponse
    {
        try {
            $providers = $this->suggestNotesUseCase->getAvailableProviders();

            return response()->json([
                'success' => true,
                'data' => [
                    'providers' => $providers,
                    'default_provider' => $this->suggestNotesUseCase->getDefaultProvider(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.provider_list_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    public function health(): JsonResponse
    {
        try {
            $healthStatus = $this->suggestNotesUseCase->checkHealth();

            return response()->json([
                'success' => true,
                'data' => $healthStatus,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.health_check_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    public function feedback(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'suggestion_id' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'feedback_type' => ['required', 'string', Rule::in(['accuracy', 'completeness', 'relevance'])],
            'comments' => 'sometimes|string|max:1000',
            'corrected_notes' => 'sometimes|array',
            'corrected_notes.top' => 'sometimes|array|max:10',
            'corrected_notes.middle' => 'sometimes|array|max:10',
            'corrected_notes.base' => 'sometimes|array|max:10',
            'corrected_attributes' => 'sometimes|array',
        ]);

        try {
            $result = $this->suggestNotesUseCase->processFeedback($userId, $validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.feedback_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    public function noteCategories(): JsonResponse
    {
        try {
            $categories = $this->suggestNotesUseCase->getNoteCategories();

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'total_notes' => array_sum(array_map('count', $categories)),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.categories_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }

    public function similarFragrances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|array|min:1',
            'notes.top' => 'sometimes|array',
            'notes.middle' => 'sometimes|array',
            'notes.base' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:20',
            'include_discontinued' => 'sometimes|boolean',
        ]);

        try {
            $options = array_merge($validated, [
                'user_id' => $request->user()?->id,
            ]);

            $result = $this->suggestNotesUseCase->findSimilarFragrances($validated['notes'], $options);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ai.errors.similar_search_failed'),
                'error' => config('app.debug') ? $e->getMessage() : __('ai.errors.internal_error'),
            ], 500);
        }
    }
}
