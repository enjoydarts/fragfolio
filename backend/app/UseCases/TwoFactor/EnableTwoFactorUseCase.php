<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class EnableTwoFactorUseCase
{
    public function __construct(
        private EnableTwoFactorAuthentication $enableTwoFactorAction
    ) {}

    public function execute(User $user): void
    {
        if ($user->two_factor_secret) {
            throw new \InvalidArgumentException(__('auth.two_factor_already_enabled'));
        }

        ($this->enableTwoFactorAction)($user);
    }
}