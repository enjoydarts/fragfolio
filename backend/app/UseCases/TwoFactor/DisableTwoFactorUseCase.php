<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class DisableTwoFactorUseCase
{
    public function __construct(
        private DisableTwoFactorAuthentication $disableTwoFactorAction
    ) {}

    public function execute(User $user): void
    {
        if (! $user->two_factor_secret) {
            throw new \InvalidArgumentException(__('auth.two_factor_already_disabled'));
        }

        ($this->disableTwoFactorAction)($user);
    }
}
