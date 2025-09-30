<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    private AIFeedbackService $aiFeedbackService;

    public function __construct(AIFeedbackService $aiFeedbackService)
    {
        $this->aiFeedbackService = $aiFeedbackService;
    }

    /**
     * AI提案の選択を記録
     */
    public function recordSelection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'operation_type' => 'required|string|in:completion,normalization',
            'ai_provider' => 'required|string|max:50',
            'ai_model' => 'required|string|max:50',
            'ai_suggestions' => 'required|array',
            'selected_suggestion' => 'required|array',
            'relevance_score' => 'nullable|numeric|min:0|max:1',
            'session_id' => 'nullable|string|max:255',
            'final_input' => 'nullable|string|max:255',
            'context_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->aiFeedbackService->recordSelection([
                'user_id' => $request->user()?->id,
                'session_id' => $request->input('session_id'),
                'operation_type' => $request->input('operation_type'),
                'query' => $request->input('query'),
                'request_params' => $request->input('request_params', []),
                'ai_provider' => $request->input('ai_provider'),
                'ai_model' => $request->input('ai_model'),
                'ai_suggestions' => $request->input('ai_suggestions'),
                'selected_suggestion' => $request->input('selected_suggestion'),
                'final_input' => $request->input('final_input'),
                'relevance_score' => $request->input('relevance_score'),
                'context_data' => $request->input('context_data', []),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feedback recorded successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record feedback',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * AI提案の拒否を記録
     */
    public function recordRejection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'operation_type' => 'required|string|in:completion,normalization',
            'ai_provider' => 'required|string|max:50',
            'ai_model' => 'required|string|max:50',
            'ai_suggestions' => 'required|array',
            'final_input' => 'nullable|string|max:255',
            'user_notes' => 'nullable|string|max:1000',
            'session_id' => 'nullable|string|max:255',
            'context_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->aiFeedbackService->recordRejection([
                'user_id' => $request->user()?->id,
                'session_id' => $request->input('session_id'),
                'operation_type' => $request->input('operation_type'),
                'query' => $request->input('query'),
                'request_params' => $request->input('request_params', []),
                'ai_provider' => $request->input('ai_provider'),
                'ai_model' => $request->input('ai_model'),
                'ai_suggestions' => $request->input('ai_suggestions'),
                'final_input' => $request->input('final_input'),
                'user_notes' => $request->input('user_notes'),
                'context_data' => $request->input('context_data', []),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rejection feedback recorded successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record rejection feedback',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ユーザー修正を記録
     */
    public function recordModification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'operation_type' => 'required|string|in:completion,normalization',
            'ai_provider' => 'required|string|max:50',
            'ai_model' => 'required|string|max:50',
            'ai_suggestions' => 'required|array',
            'original_suggestion' => 'nullable|array',
            'final_input' => 'required|string|max:255',
            'relevance_score' => 'nullable|numeric|min:0|max:1',
            'was_helpful' => 'nullable|boolean',
            'user_notes' => 'nullable|string|max:1000',
            'session_id' => 'nullable|string|max:255',
            'context_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->aiFeedbackService->recordModification([
                'user_id' => $request->user()?->id,
                'session_id' => $request->input('session_id'),
                'operation_type' => $request->input('operation_type'),
                'query' => $request->input('query'),
                'request_params' => $request->input('request_params', []),
                'ai_provider' => $request->input('ai_provider'),
                'ai_model' => $request->input('ai_model'),
                'ai_suggestions' => $request->input('ai_suggestions'),
                'original_suggestion' => $request->input('original_suggestion'),
                'final_input' => $request->input('final_input'),
                'relevance_score' => $request->input('relevance_score'),
                'was_helpful' => $request->input('was_helpful'),
                'user_notes' => $request->input('user_notes'),
                'context_data' => $request->input('context_data', []),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Modification feedback recorded successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record modification feedback',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * セッションIDを生成
     */
    public function generateSessionId(): JsonResponse
    {
        try {
            $sessionId = $this->aiFeedbackService->generateSessionId();

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate session ID',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
