<?php

namespace App\UseCases\Auth;

use App\Mail\VerifyNewEmailChange;
use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RequestEmailChangeUseCase
{
    public function execute(User $user, string $newEmail): EmailChangeRequest
    {
        // 新しいメールアドレスが既に他のユーザーで使用されていないかチェック
        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            throw new \InvalidArgumentException(__('auth.email_exists'));
        }

        return DB::transaction(function () use ($user, $newEmail) {
            // 既存のメールアドレス変更リクエストを削除
            EmailChangeRequest::where('user_id', $user->id)->delete();

            // 新しいリクエストを作成
            $request = EmailChangeRequest::create([
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'token' => Str::random(60),
                'expires_at' => now()->addHours(24), // 24時間で期限切れ
            ]);

            // 新しいメールアドレスにのみ確認メールを送信
            $this->sendNewEmailVerification($newEmail, $request);

            return $request;
        });
    }

    private function sendNewEmailVerification(string $newEmail, EmailChangeRequest $request): void
    {
        Mail::to($newEmail)->send(new VerifyNewEmailChange($request));
    }
}