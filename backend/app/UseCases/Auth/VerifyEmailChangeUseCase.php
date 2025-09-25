<?php

namespace App\UseCases\Auth;

use App\Models\EmailChangeRequest;
use Illuminate\Support\Facades\DB;

class VerifyEmailChangeUseCase
{
    public function verifyEmailChange(string $token): void
    {
        $request = EmailChangeRequest::where('token', $token)->firstOrFail();

        if ($request->isExpired()) {
            throw new \InvalidArgumentException(__('auth.invalid_verification_link'));
        }

        $request->update(['verified' => true]);

        // 認証完了後、メールアドレス変更を実行
        $this->completeEmailChange($request);
    }

    private function completeEmailChange(EmailChangeRequest $request): void
    {
        DB::transaction(function () use ($request) {
            // ユーザーのメールアドレスを変更
            $request->user->update([
                'email' => $request->new_email,
                'email_verified_at' => now(), // 新しいメールアドレスは認証済み
            ]);

            // リクエストを削除
            $request->delete();

            // TODO: 変更完了通知メールを両方のアドレスに送信
        });
    }
}
