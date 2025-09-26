<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract, Responsable
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        $user = $request->user()->load('profile');

        // 利用可能な2FA認証方法を確認
        $hasTotp = ! is_null($user->two_factor_secret) && ! is_null($user->two_factor_confirmed_at);
        $hasWebAuthn = $user->webAuthnCredentials()->whereNull('disabled_at')->exists();

        // いずれかの2FA方法が有効な場合は、2FAチャレンジを要求
        if ($hasTotp || $hasWebAuthn) {
            // 一時トークンを生成してキャッシュに保存
            $tempToken = \Illuminate\Support\Str::random(60);
            cache()->put("two_factor_pending:{$tempToken}", [
                'user_id' => $user->getKey(),
                'remember' => $request->boolean('remember'),
            ], now()->addMinutes(10));

            \Log::info('LoginResponse 2FA check', [
                'user_id' => $user->id,
                'has_totp_secret' => ! is_null($user->two_factor_secret),
                'has_totp_confirmed' => ! is_null($user->two_factor_confirmed_at),
                'has_totp' => $hasTotp,
                'webauthn_count' => $user->webAuthnCredentials()->whereNull('disabled_at')->count(),
                'has_webauthn' => $hasWebAuthn,
            ]);

            $availableMethods = [];
            if ($hasTotp) {
                $availableMethods[] = 'totp';
            }
            if ($hasWebAuthn) {
                $availableMethods[] = 'webauthn';
            }

            \Log::info('LoginResponse available methods', ['available_methods' => $availableMethods]);

            return response()->json([
                'success' => false,
                'requires_two_factor' => true,
                'temp_token' => $tempToken,
                'message' => '2要素認証が必要です。認証コードを入力してください。',
                'available_methods' => $availableMethods,
            ], 200);
        }

        // 通常ログイン（2FA無効）
        $abilities = $user->is_admin ? ['admin'] : ['user'];
        $token = $user->createToken('auth_token', $abilities, $request->boolean('remember') ? now()->addDays(30) : null);

        return response()->json([
            'success' => true,
            'message' => 'ログインに成功しました',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                'profile' => [
                    'language' => $user->profile?->language ?? 'ja',
                    'timezone' => $user->profile?->timezone ?? 'Asia/Tokyo',
                    'bio' => $user->profile?->bio ?? null,
                    'date_of_birth' => $user->profile?->date_of_birth ?? null,
                    'gender' => $user->profile?->gender ?? null,
                    'country' => $user->profile?->country ?? null,
                ],
                'roles' => $user->role,
            ],
            'token' => $token->plainTextToken,
        ]);
    }
}
