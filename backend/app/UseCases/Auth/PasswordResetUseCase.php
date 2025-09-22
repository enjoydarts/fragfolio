<?php

namespace App\UseCases\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetUseCase
{
    public function sendResetLinkEmail(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => __('auth.user_not_found'),
            ];
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            return [
                'success' => true,
                'message' => __('auth.reset_link_sent'),
            ];
        } else {
            return [
                'success' => false,
                'message' => __('auth.reset_link_failed'),
            ];
        }
    }

    public function resetPassword(array $data): array
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return [
                'success' => true,
                'message' => __('auth.password_reset_success'),
            ];
        } else {
            return [
                'success' => false,
                'message' => __('auth.password_reset_failed'),
            ];
        }
    }
}
