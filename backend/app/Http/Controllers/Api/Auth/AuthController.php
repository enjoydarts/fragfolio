<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\TurnstileService;
use App\UseCases\Auth\LoginUserUseCase;
use App\UseCases\Auth\PasswordResetUseCase;
use App\UseCases\Auth\RefreshTokenUseCase;
use App\UseCases\Auth\RegisterUserUseCase;
use App\UseCases\Auth\UpdateUserProfileUseCase;
use App\UseCases\Auth\VerifyEmailUseCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        private TurnstileService $turnstileService,
        private RegisterUserUseCase $registerUserUseCase,
        private LoginUserUseCase $loginUserUseCase,
        private UpdateUserProfileUseCase $updateUserProfileUseCase,
        private RefreshTokenUseCase $refreshTokenUseCase,
        private VerifyEmailUseCase $verifyEmailUseCase,
        private PasswordResetUseCase $passwordResetUseCase
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
            Log::info('User registration attempt', ['email' => $request->email]);
            $data = $request->only(['name', 'email', 'password', 'language', 'timezone']);
            Log::debug('Registration data', ['data' => Arr::except($data, ['password'])]);

            $user = $this->registerUserUseCase->execute($data);
            Log::info('User registered successfully', ['user_id' => $user->id]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'ユーザー登録が完了しました',
                'user' => $user,
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ユーザー登録に失敗しました',
                'error' => $e->getMessage(),
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
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->passwordResetUseCase->sendResetLinkEmail($request->email);

            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'パスワードリセットメールの送信に失敗しました',
            ], 500);
        }
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

        try {
            $result = $this->passwordResetUseCase->resetPassword($request->only([
                'email', 'password', 'password_confirmation', 'token',
            ]));

            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'パスワードリセットに失敗しました',
            ], 500);
        }
    }

    public function showResetForm(Request $request, $token): JsonResponse
    {
        return response()->json([
            'success' => true,
            'token' => $token,
            'email' => $request->email,
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

    public function verifyEmail(Request $request): JsonResponse
    {
        $result = $this->verifyEmailUseCase->execute($request->user());

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $result = $this->verifyEmailUseCase->resendVerificationEmail($request->user());

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    public function verifyEmailFromLink(Request $request, $id, $hash)
    {
        $result = $this->verifyEmailUseCase->verifyFromLink($id, $hash);

        $frontendUrl = config('app.frontend_url');

        if ($result['success']) {
            return redirect($frontendUrl.'/email-verification-success?message='.urlencode($result['message']));
        } else {
            return redirect($frontendUrl.'/email-verification-error?message='.urlencode($result['message']));
        }
    }
}
