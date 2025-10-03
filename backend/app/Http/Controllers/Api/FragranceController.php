<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFragranceRequest;
use App\UseCases\Fragrance\RegisterFragranceUseCase;
use App\UseCases\Fragrance\UpdateFragranceUseCase;
use App\UseCases\Fragrance\DeleteFragranceUseCase;
use Illuminate\Http\JsonResponse;

class FragranceController extends Controller
{
    private RegisterFragranceUseCase $registerUseCase;

    private UpdateFragranceUseCase $updateUseCase;

    private DeleteFragranceUseCase $deleteUseCase;

    public function __construct(
        RegisterFragranceUseCase $registerUseCase,
        UpdateFragranceUseCase $updateUseCase,
        DeleteFragranceUseCase $deleteUseCase
    ) {
        $this->registerUseCase = $registerUseCase;
        $this->updateUseCase = $updateUseCase;
        $this->deleteUseCase = $deleteUseCase;
    }

    /**
     * ユーザーの香水コレクション一覧を取得
     */
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'type' => 'unauthorized',
                        'message' => __('auth.unauthorized'),
                    ],
                ], 401);
            }

            $fragrances = $user->fragrances()
                ->with(['fragrance.brand', 'tags'])
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $fragrances,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('fragrance.fetch_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 香水の詳細を取得
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'type' => 'unauthorized',
                        'message' => __('auth.unauthorized'),
                    ],
                ], 401);
            }

            $userFragrance = $user->fragrances()
                ->with(['fragrance.brand', 'tags'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $userFragrance,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'not_found',
                    'message' => __('fragrance.not_found'),
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('fragrance.fetch_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 新しい香水を登録
     */
    public function store(StoreFragranceRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'type' => 'unauthorized',
                        'message' => __('auth.unauthorized'),
                    ],
                ], 401);
            }

            $userFragrance = $this->registerUseCase->execute($user, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $userFragrance,
                'message' => __('fragrance.registration_success'),
            ], 201);

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
                    'message' => __('fragrance.registration_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 香水情報を更新
     */
    public function update(StoreFragranceRequest $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'type' => 'unauthorized',
                        'message' => __('auth.unauthorized'),
                    ],
                ], 401);
            }

            /** @var \App\Models\UserFragrance $userFragrance */
            $userFragrance = $user->fragrances()->findOrFail($id);

            $updatedFragrance = $this->updateUseCase->execute($userFragrance, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $updatedFragrance,
                'message' => __('fragrance.update_success'),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'not_found',
                    'message' => __('fragrance.not_found'),
                ],
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('fragrance.update_failed'),
                ],
            ], 500);
        }
    }

    /**
     * 香水を削除（論理削除）
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'type' => 'unauthorized',
                        'message' => __('auth.unauthorized'),
                    ],
                ], 401);
            }

            $userFragrance = $user->fragrances()->findOrFail($id);
            $this->deleteUseCase->execute($userFragrance);

            return response()->json([
                'success' => true,
                'message' => __('fragrance.delete_success'),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'not_found',
                    'message' => __('fragrance.not_found'),
                ],
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'internal_error',
                    'message' => __('fragrance.delete_failed'),
                ],
            ], 500);
        }
    }
}
