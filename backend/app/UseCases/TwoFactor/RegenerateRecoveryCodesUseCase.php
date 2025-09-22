<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class RegenerateRecoveryCodesUseCase
{
    public function __construct(
        private GenerateNewRecoveryCodes $generateNewRecoveryCodes
    ) {}

    public function execute(User $user): array
    {
        if (!$user->two_factor_confirmed_at) {
            throw new \InvalidArgumentException(__('auth.two_factor_not_confirmed'));
        }

        ($this->generateNewRecoveryCodes)($user);

        // データベースから最新のユーザー情報を取得
        $user->refresh();

        return json_decode(decrypt($user->two_factor_recovery_codes), true);
    }
}