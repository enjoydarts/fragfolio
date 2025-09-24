<?php

namespace App\Http\Controllers\WebAuthn;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

use function response;

class WebAuthnLoginController
{
    /**
     * Returns the challenge to assertion.
     */
    public function options(AssertionRequest $request): Responsable
    {
        return $request->toVerify($request->validate(['email' => 'sometimes|email|string']));
    }

    /**
     * Log the user in.
     */
    public function login(AssertedRequest $request): JsonResponse
    {
        // WebAuthn認証を実行
        $user = $request->login();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.login_failed'),
            ], 422);
        }

        // 2FAが有効な場合、完全ログインではなく2FA待ち状態にする
        if ($user->two_factor_secret) {
            // Cacheに一時的にユーザー情報を保存（temp_tokenを生成）
            $tempToken = \Illuminate\Support\Str::random(60);
            \Illuminate\Support\Facades\Cache::put("two_factor_pending:{$tempToken}", [
                'user_id' => $user->id,
                'remember' => false, // WebAuthnの場合はremember機能なし
                'login_method' => 'webauthn',
            ], now()->addMinutes(10));

            // 利用可能な2FA認証方法を確認
            $hasTotp = ! is_null($user->two_factor_secret) && ! is_null($user->two_factor_confirmed_at);
            $hasWebAuthn = $user->webAuthnCredentials()->whereNull('disabled_at')->exists();

            $availableMethods = [];
            if ($hasTotp) {
                $availableMethods[] = 'totp';
            }
            if ($hasWebAuthn) {
                $availableMethods[] = 'webauthn';
            }

            return response()->json([
                'success' => true,
                'requires_two_factor' => true,
                'temp_token' => $tempToken,
                'message' => __('auth.login_success'),
                'available_methods' => $availableMethods,
            ]);
        }

        // 2FAが無効な場合、通常のログイン処理
        Auth::login($user);

        // APIトークンを生成
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
    }
}
