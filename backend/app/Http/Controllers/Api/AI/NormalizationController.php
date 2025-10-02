<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AI\BatchNormalizeFragranceRequest;
use App\Http\Requests\Api\AI\NormalizeFragranceRequest;
use App\Http\Requests\Api\AI\SmartFragranceInputRequest;
use App\UseCases\AI\NormalizeFragranceUseCase;
use Illuminate\Http\JsonResponse;

class NormalizationController extends Controller
{
    private NormalizeFragranceUseCase $useCase;

    public function __construct(NormalizeFragranceUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    /**
     * 香水情報の正規化
     */
    public function normalize(NormalizeFragranceRequest $request): JsonResponse
    {
        try {
            $result = $this->useCase->normalize(
                brandName: $request->validated('brand_name'),
                fragranceName: $request->validated('fragrance_name'),
                options: [
                    'provider' => $request->validated('provider'),
                    'language' => $request->validated('language', 'ja'),
                    'user_id' => $request->user()?->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'validation_error',
                    'message' => $e->getMessage(),
                ],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('ai.normalization_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 一括正規化
     */
    public function batchNormalize(BatchNormalizeFragranceRequest $request): JsonResponse
    {
        try {
            $result = $this->useCase->batchNormalize(
                fragrances: $request->validated('fragrances'),
                options: [
                    'provider' => $request->validated('provider'),
                    'language' => $request->validated('language', 'ja'),
                    'user_id' => $request->user()?->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'validation_error',
                    'message' => $e->getMessage(),
                ],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('ai.batch_normalization_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 統一入力からの香水情報正規化
     */
    public function normalizeFromInput(SmartFragranceInputRequest $request): JsonResponse
    {
        try {
            $result = $this->useCase->normalizeFromInput(
                input: $request->validated('input'),
                options: [
                    'provider' => $request->validated('provider'),
                    'language' => $request->validated('language', 'mixed'),
                    'user_id' => $request->user()?->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'validation_error',
                    'message' => $e->getMessage(),
                ],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('ai.smart_normalization_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 正規化プロバイダー一覧
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'providers' => [
                    [
                        'name' => 'openai',
                        'display_name' => 'OpenAI GPT',
                        'available' => ! empty(config('services.openai.api_key')),
                        'models' => [config('services.ai.models.gpt')],
                    ],
                    [
                        'name' => 'anthropic',
                        'display_name' => 'Anthropic Claude',
                        'available' => ! empty(config('services.anthropic.api_key')),
                        'models' => [config('services.ai.models.claude')],
                    ],
                    [
                        'name' => 'gemini',
                        'display_name' => 'Google Gemini',
                        'available' => ! empty(config('services.google.project_id')),
                        'models' => [config('services.ai.models.gemini')],
                    ],
                ],
                'default' => config('services.ai.default_provider'),
                'total' => 3,
            ],
        ]);
    }

    /**
     * 正規化システムのヘルスチェック
     */
    public function health(): JsonResponse
    {
        try {
            $providers = [];
            $overallHealthy = true;

            // OpenAI プロバイダーチェック
            $openaiHealthy = $this->checkProviderHealth('openai');
            $providers[] = [
                'name' => 'openai',
                'healthy' => $openaiHealthy,
                'last_check' => now()->toISOString(),
            ];
            if (! $openaiHealthy) {
                $overallHealthy = false;
            }

            // Anthropic プロバイダーチェック
            $anthropicHealthy = $this->checkProviderHealth('anthropic');
            $providers[] = [
                'name' => 'anthropic',
                'healthy' => $anthropicHealthy,
                'last_check' => now()->toISOString(),
            ];
            if (! $anthropicHealthy) {
                $overallHealthy = false;
            }

            // Gemini プロバイダーチェック
            $geminiHealthy = $this->checkProviderHealth('gemini');
            $providers[] = [
                'name' => 'gemini',
                'healthy' => $geminiHealthy,
                'last_check' => now()->toISOString(),
            ];
            if (! $geminiHealthy) {
                $overallHealthy = false;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'providers' => $providers,
                    'overall_status' => $overallHealthy ? 'healthy' : 'degraded',
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'health_check_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * プロバイダーのヘルス状態チェック
     */
    private function checkProviderHealth(string $providerName): bool
    {
        try {
            switch ($providerName) {
                case 'openai':
                    return ! empty(config('services.openai.api_key'));
                case 'anthropic':
                    return ! empty(config('services.anthropic.api_key'));
                case 'gemini':
                    return ! empty(config('services.google.project_id'));
                default:
                    return false;
            }
        } catch (\Exception) {
            return false;
        }
    }
}
