<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;

class EnableTwoFactorUseCase
{
    public function __construct(
        private EnableTwoFactorAuthentication $enableTwoFactorAction,
        private Google2FA $google2fa
    ) {}

    public function execute(User $user): array
    {
        if ($user->two_factor_secret) {
            throw new \InvalidArgumentException(__('auth.two_factor_already_enabled'));
        }

        ($this->enableTwoFactorAction)($user);

        $secret = decrypt($user->two_factor_secret);
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }
}
