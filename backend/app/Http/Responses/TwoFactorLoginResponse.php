<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;

class TwoFactorLoginResponse implements Responsable
{
    protected $user;
    protected $remember;

    public function __construct($user, $remember = false)
    {
        $this->user = $user;
        $this->remember = $remember;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        // 一時トークンを生成（2FA確認用）
        $tempToken = \Illuminate\Support\Str::random(60);

        // Cacheに一時認証情報を保存（10分間有効）
        cache()->put("two_factor_pending:{$tempToken}", [
            'user_id' => $this->user->getKey(),
            'remember' => $this->remember,
        ], now()->addMinutes(10));

        // 利用可能な2FA認証方法を確認
        $hasTotp = !is_null($this->user->two_factor_secret) && !is_null($this->user->two_factor_confirmed_at);
        $hasWebAuthn = $this->user->webAuthnCredentials()->whereNull('disabled_at')->exists();

        $availableMethods = [];
        if ($hasTotp) {
            $availableMethods[] = 'totp';
        }
        if ($hasWebAuthn) {
            $availableMethods[] = 'webauthn';
        }

        return response()->json([
            'success' => false,
            'requires_two_factor' => true,
            'message' => '2要素認証コードを入力してください',
            'temp_token' => $tempToken,
            'available_methods' => $availableMethods,
        ], 200);
    }
}