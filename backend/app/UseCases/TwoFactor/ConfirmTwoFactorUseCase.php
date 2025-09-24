<?php

namespace App\UseCases\TwoFactor;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

class ConfirmTwoFactorUseCase
{
    public function __construct(
        private ConfirmTwoFactorAuthentication $confirmTwoFactorAction
    ) {}

    public function execute(User $user, string $code): array
    {
        if (!$user->two_factor_secret) {
            throw new \InvalidArgumentException(__('auth.two_factor_not_enabled'));
        }

        if ($user->two_factor_confirmed_at) {
            throw new \InvalidArgumentException(__('auth.two_factor_already_confirmed'));
        }

        try {
            ($this->confirmTwoFactorAction)($user, $code);

            // Fortifyによってリカバリーコードが生成されているので、それを取得して返す
            $user->refresh();
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            return [
                'recovery_codes' => $recoveryCodes
            ];
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'code' => [__('auth.two_factor_code_invalid')],
            ]);
        }
    }
}