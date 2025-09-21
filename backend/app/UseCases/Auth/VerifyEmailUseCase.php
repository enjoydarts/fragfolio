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
                'message' => 'メールアドレスは既に認証済みです',
            ];
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return [
            'success' => true,
            'message' => 'メールアドレスが認証されました',
        ];
    }

    public function resendVerificationEmail(User $user): array
    {
        if ($user->hasVerifiedEmail()) {
            return [
                'success' => false,
                'message' => 'メールアドレスは既に認証済みです',
            ];
        }

        $user->sendEmailVerificationNotification();

        return [
            'success' => true,
            'message' => '認証メールを再送信しました',
        ];
    }

    public function verifyFromLink(int $id, string $hash): array
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return [
                'success' => false,
                'message' => '無効な認証リンクです',
                'status_code' => 403,
            ];
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => 'メールアドレスは既に認証済みです',
            ];
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return [
            'success' => true,
            'message' => 'メールアドレスが認証されました',
        ];
    }
}
