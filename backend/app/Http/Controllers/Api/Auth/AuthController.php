<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\TurnstileService;
use App\UseCases\Auth\LoginUserUseCase;
use App\UseCases\Auth\RefreshTokenUseCase;
use App\UseCases\Auth\RegisterUserUseCase;
use App\UseCases\Auth\UpdateUserProfileUseCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        private TurnstileService $turnstileService,
        private RegisterUserUseCase $registerUserUseCase,
        private LoginUserUseCase $loginUserUseCase,
        private UpdateUserProfileUseCase $updateUserProfileUseCase,
        private RefreshTokenUseCase $refreshTokenUseCase
    ) {}

    public function register(Request $request): JsonResponse
    {
        // Turnstile検証
        if ($this->turnstileService->isConfigured()) {
            $turnstileToken = $request->input('turnstile_token');
            if (! $turnstileToken || ! $this->turnstileService->verify($turnstileToken, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turnstile検証に失敗しました',
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->only(['name', 'email', 'password', 'language', 'timezone']);
            $user = $this->registerUserUseCase->execute($data);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'ユーザー登録が完了しました',
                'user' => $user,
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー登録に失敗しました',
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        // Turnstile検証
        if ($this->turnstileService->isConfigured()) {
            $turnstileToken = $request->input('turnstile_token');
            if (! $turnstileToken || ! $this->turnstileService->verify($turnstileToken, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turnstile検証に失敗しました',
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            $remember = $request->boolean('remember');

            $result = $this->loginUserUseCase->execute($credentials, $remember);

            return response()->json([
                'success' => true,
                'message' => 'ログインしました',
                'user' => $result['user'],
                'token' => $result['token'],
            ]);

        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが正しくありません',
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'ログアウトしました',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('profile', 'roles'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'display_name' => 'sometimes|string|max:255',
            'bio' => 'sometimes|string|max:500',
            'language' => 'sometimes|string|in:ja,en',
            'timezone' => 'sometimes|string|max:50',
            'notification_preferences' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->only([
                'name', 'bio', 'language', 'timezone', 'date_of_birth', 'gender', 'country',
            ]);

            $user = $this->updateUserProfileUseCase->execute($request->user(), $data);

            return response()->json([
                'success' => true,
                'message' => 'プロフィールを更新しました',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'プロフィール更新に失敗しました',
            ], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        // パスワードリセット機能は後で実装
        return response()->json([
            'success' => true,
            'message' => 'パスワードリセットメールを送信しました（実装予定）',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        // パスワードリセット機能は後で実装
        return response()->json([
            'success' => true,
            'message' => 'パスワードをリセットしました（実装予定）',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->refreshTokenUseCase->execute($request->user());

        return response()->json([
            'success' => true,
            'message' => 'トークンを更新しました',
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }
}
