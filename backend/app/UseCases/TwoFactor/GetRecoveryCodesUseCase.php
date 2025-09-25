<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;

class GetRecoveryCodesUseCase
{
    public function execute(User $user): array
    {
        if (! $user->two_factor_confirmed_at) {
            throw new \InvalidArgumentException(__('auth.two_factor_not_confirmed'));
        }

        if (! $user->two_factor_recovery_codes) {
            throw new \InvalidArgumentException(__('auth.recovery_codes_not_generated'));
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true);
    }
}
