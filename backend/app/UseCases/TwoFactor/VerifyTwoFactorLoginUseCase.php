<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class VerifyTwoFactorLoginUseCase
{
    public function __construct() {}

    public function execute(string $code, string $tempToken): array
    {
        // Cacheから一時認証情報を取得
        $tempData = Cache::get("two_factor_pending:{$tempToken}");

        if (! $tempData) {
            throw new \InvalidArgumentException(__('auth.two_factor_token_expired'));
        }

        $user = User::find($tempData['user_id']);

        if (! $user || ! $user->two_factor_confirmed_at) {
            Cache::forget("two_factor_pending:{$tempToken}");
            throw new \InvalidArgumentException(__('auth.unauthorized'));
        }

        // TOTPコードを検証
        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $code
        );

        if (! $valid) {
            // リカバリコードもチェック
            $valid = $this->validateRecoveryCode($user, $code);
        }

        if (! $valid) {
            throw new \InvalidArgumentException(__('auth.two_factor_failed'));
        }

        // ログイン完了処理
        $remember = $tempData['remember'];
        Cache::forget("two_factor_pending:{$tempToken}");

        // Sanctumトークンを生成
        $token = $user->createToken('API Token')->plainTextToken;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => ! is_null($user->two_factor_secret),
                'profile' => [
                    'language' => $user->profile->language ?? 'ja',
                    'timezone' => $user->profile->timezone ?? 'Asia/Tokyo',
                    'bio' => $user->profile->bio ?? null,
                    'date_of_birth' => $user->profile->date_of_birth ?? null,
                    'gender' => $user->profile->gender ?? null,
                    'country' => $user->profile->country ?? null,
                ],
                'role' => $user->role,
            ],
            'token' => $token,
        ];
    }

    private function validateRecoveryCode(User $user, string $code): bool
    {
        if (empty($user->two_factor_recovery_codes)) {
            return false;
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];

        foreach ($recoveryCodes as $index => $recoveryCode) {
            if ($recoveryCode === $code) {
                // 使用済みリカバリコードを削除
                unset($recoveryCodes[$index]);
                $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                $user->save();

                return true;
            }
        }

        return false;
    }
}
