<?php

namespace App\UseCases\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmailUseCase
{
    public function execute(User $user): array
    {
        if ($user->hasVerifiedEmail()) {
            return [
                'success' => false,
                'message' => __('auth.email_already_verified'),
            ];
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return [
            'success' => true,
            'message' => __('auth.email_verified'),
        ];
    }

    public function resendVerificationEmail(User $user): array
    {
        if ($user->hasVerifiedEmail()) {
            return [
                'success' => false,
                'message' => __('auth.email_already_verified'),
            ];
        }

        $user->sendEmailVerificationNotification();

        return [
            'success' => true,
            'message' => __('auth.verification_email_resent'),
        ];
    }

    public function verifyFromLink(int $id, string $hash): array
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return [
                'success' => false,
                'message' => __('auth.invalid_verification_link'),
                'status_code' => 403,
            ];
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => __('auth.email_already_verified'),
            ];
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return [
            'success' => true,
            'message' => __('auth.email_verified'),
        ];
    }
}
