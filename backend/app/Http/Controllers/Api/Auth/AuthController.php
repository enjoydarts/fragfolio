<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\TurnstileService;
use App\UseCases\Auth\LoginUserUseCase;
use App\UseCases\Auth\PasswordResetUseCase;
use App\UseCases\Auth\RefreshTokenUseCase;
use App\UseCases\Auth\RegisterUserUseCase;
use App\UseCases\Auth\RequestEmailChangeUseCase;
use App\UseCases\Auth\UpdateUserProfileUseCase;
use App\UseCases\Auth\VerifyEmailChangeUseCase;
use App\UseCases\Auth\VerifyEmailUseCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
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
        private PasswordResetUseCase $passwordResetUseCase,
        private RequestEmailChangeUseCase $requestEmailChangeUseCase,
        private VerifyEmailChangeUseCase $verifyEmailChangeUseCase
    ) {}

    public function register(Request $request): JsonResponse
    {
        // Turnstile検証
        if ($this->turnstileService->isConfigured()) {
            $turnstileToken = $request->input('turnstile_token');
            if (! $turnstileToken || ! $this->turnstileService->verify($turnstileToken, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.turnstile_verification_failed'),
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.validation_error'),
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
                'message' => __('auth.registration_success'),
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
                'message' => __('auth.registration_failed'),
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
                    'message' => __('auth.turnstile_verification_failed'),
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
                'message' => __('auth.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            $remember = $request->boolean('remember');

            Log::info('Login attempt', [
                'email' => $credentials['email'],
                'has_password' => ! empty($credentials['password']),
                'remember' => $remember,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            $result = $this->loginUserUseCase->execute($credentials, $remember);

            return response()->json([
                'success' => true,
                'message' => __('auth.login_success'),
                'user' => $result['user'],
                'token' => $result['token'],
            ]);

        } catch (AuthenticationException $e) {
            Log::warning('Login failed - invalid credentials', [
                'email' => $credentials['email'] ?? 'unknown',
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auth.invalid_credentials'),
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('auth.logout_success'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => ! is_null($user->two_factor_secret),
                'profile' => $user->profile,
                'role' => $user->role,
            ],
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
                'message' => __('auth.validation_error'),
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
                'message' => __('auth.profile_update_success'),
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.profile_update_failed'),
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
                'message' => __('auth.validation_error'),
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
                'message' => __('auth.reset_link_failed'),
            ], 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.validation_error'),
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
                'message' => __('auth.password_reset_failed'),
            ], 500);
        }
    }

    public function showResetForm(Request $request, $token)
    {
        $frontendUrl = config('app.frontend_url');
        $email = $request->email;

        if (! $email || ! $token) {
            return redirect($frontendUrl.'/password-reset-error?message='.urlencode(__('auth.invalid_reset_link')));
        }

        return redirect($frontendUrl.'/reset-password?token='.urlencode($token).'&email='.urlencode($email));
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->refreshTokenUseCase->execute($request->user());

        return response()->json([
            'success' => true,
            'message' => __('auth.token_refresh_success'),
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

    public function requestEmailChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $emailChangeRequest = $this->requestEmailChangeUseCase->execute(
                $request->user(),
                $request->input('new_email')
            );

            return response()->json([
                'success' => true,
                'message' => __('auth.email_change_request_sent'),
                'data' => [
                    'current_email' => $request->user()->email,
                    'new_email' => $emailChangeRequest->new_email,
                    'expires_at' => $emailChangeRequest->expires_at,
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Email change request failed', [
                'user_id' => $request->user()->id,
                'new_email' => $request->input('new_email'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auth.email_change_request_failed'),
            ], 500);
        }
    }

    public function verifyEmailChange(Request $request, $token)
    {
        try {
            $this->verifyEmailChangeUseCase->verifyEmailChange($token);

            $frontendUrl = config('app.frontend_url');

            return redirect($frontendUrl.'/settings?message='.urlencode(__('auth.email_change_completed')));

        } catch (\Exception $e) {
            $frontendUrl = config('app.frontend_url');

            return redirect($frontendUrl.'/settings?error='.urlencode(__('auth.invalid_verification_link')));
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])/',
        ]);

        $user = $request->user();

        // 現在のパスワードを確認
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.current_password_incorrect'),
            ], 422);
        }

        // 新しいパスワードをハッシュ化して保存
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('auth.password_change_success'),
        ]);
    }
}
