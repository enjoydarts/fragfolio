<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\UseCases\TwoFactor\VerifyTwoFactorLoginUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Laragear\WebAuthn\Exceptions\AssertionException;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\JsonTransport;

class TwoFactorLoginController extends Controller
{
    public function __construct(
        private VerifyTwoFactorLoginUseCase $verifyTwoFactorLoginUseCase,
        private AssertionValidator $assertionValidator
    ) {}

    /**
     * 2FAコードでログインを完了
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|min:6|max:50', // リカバリコードにも対応
            'temp_token' => 'required|string',
        ]);

        try {
            $result = $this->verifyTwoFactorLoginUseCase->execute(
                $request->code,
                $request->temp_token
            );

            return response()->json([
                'success' => true,
                'message' => __('auth.login_success'),
                'user' => $result['user'],
                'token' => $result['token'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * WebAuthnで2FAを完了
     */
    public function verifyWithWebAuthn(AssertionRequest $request): JsonResponse
    {
        $request->validate([
            'temp_token' => 'required|string',
        ]);

        try {
            // temp_tokenからユーザー情報を取得
            $tempData = Cache::get("two_factor_pending:{$request->temp_token}");

            if (! $tempData) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.invalid_reset_link'),
                ], 401);
            }

            $user = User::find($tempData['user_id']);
            if (! $user instanceof User) {
                Cache::forget("two_factor_pending:{$request->temp_token}");

                return response()->json([
                    'success' => false,
                    'message' => __('auth.invalid_reset_link'),
                ], 401);
            }

            // WebAuthnログインオプションを返す（ユーザー限定）
            $options = $request->toVerify(['email' => $user->email]);

            return response()->json([
                'success' => true,
                'webauthn_options' => $options,
            ]);
        } catch (\Exception $e) {
            Log::error('WebAuthn 2FA options failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('auth.login_failed'),
            ], 500);
        }
    }

    /**
     * WebAuthn認証レスポンスで2FAを完了
     */
    public function completeWebAuthnTwoFactor(AssertedRequest $request): JsonResponse
    {
        $request->validate([
            'temp_token' => 'required|string',
        ]);

        try {
            // temp_tokenからユーザー情報を取得
            $tempData = Cache::get("two_factor_pending:{$request->temp_token}");

            if (! $tempData) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.invalid_reset_link'),
                ], 401);
            }

            $user = User::find($tempData['user_id']);
            if (! $user instanceof User) {
                Cache::forget("two_factor_pending:{$request->temp_token}");

                return response()->json([
                    'success' => false,
                    'message' => __('auth.invalid_reset_link'),
                ], 401);
            }

            // WebAuthn認証を検証
            try {
                // WebAuthn認証データの準備
                $credentials = [
                    'id' => $request->input('id'),
                    'rawId' => $request->input('rawId'),
                    'response' => $request->input('response'),
                    'type' => $request->input('type'),
                ];

                // Laragear/WebAuthnパッケージの完全な暗号学的検証を実行
                $this->assertionValidator
                    ->send(new AssertionValidation(new JsonTransport($credentials), $user))
                    ->thenReturn();

                Log::info('WebAuthn 2FA verified successfully', ['user_id' => $user->id, 'credential_id' => $credentials['id']]);

            } catch (AssertionException $e) {
                Log::error('WebAuthn 2FA assertion failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);

                return response()->json([
                    'success' => false,
                    'message' => __('auth.login_failed'),
                ], 422);
            } catch (\Exception $e) {
                Log::error('WebAuthn 2FA verification failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);

                return response()->json([
                    'success' => false,
                    'message' => __('auth.login_failed'),
                ], 422);
            }

            // 2FA完了処理
            Cache::forget("two_factor_pending:{$request->temp_token}");

            // Sanctumトークンを生成
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => ! is_null($user->two_factor_secret),
                ],
                'token' => $token,
                'message' => __('auth.login_success'),
            ]);
        } catch (\Exception $e) {
            Log::error('WebAuthn 2FA complete failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('auth.login_failed'),
            ], 500);
        }
    }
}
