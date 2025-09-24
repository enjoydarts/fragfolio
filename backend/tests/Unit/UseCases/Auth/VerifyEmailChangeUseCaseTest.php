<?php

use App\Models\EmailChangeRequest;
use App\Models\User;
use App\UseCases\Auth\VerifyEmailChangeUseCase;

describe('VerifyEmailChangeUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'old@example.com',
        ]);
        $this->useCase = new VerifyEmailChangeUseCase;

        // 既存のリクエストをクリア
        EmailChangeRequest::where('user_id', $this->user->id)->delete();

        // 有効なリクエストを作成
        $this->token = 'valid-token-'.time().'-'.rand(1000, 9999);
        $this->emailRequest = EmailChangeRequest::create([
            'user_id' => $this->user->id,
            'new_email' => 'new@example.com',
            'token' => $this->token,
            'expires_at' => now()->addHour(),
        ]);
    });

    test('有効なトークンでメールアドレスを変更できる', function () {
        $this->useCase->verifyEmailChange($this->token);

        // ユーザーのメールアドレスが変更されている
        $this->user->refresh();
        expect($this->user->email)->toBe('new@example.com');
        expect($this->user->email_verified_at)->not()->toBeNull();

        // リクエストが削除されている
        expect(EmailChangeRequest::where('token', $this->token)->exists())->toBe(false);
    });

    test('存在しないトークンでエラー', function () {
        expect(fn () => $this->useCase->verifyEmailChange('non-existent-token'))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // ユーザーのメールアドレスは変更されていない
        $this->user->refresh();
        expect($this->user->email)->toBe('old@example.com');
    });

    test('期限切れのトークンでエラー', function () {
        // トークンを期限切れにする
        $this->emailRequest->update([
            'expires_at' => now()->subHour(),
        ]);

        expect(fn () => $this->useCase->verifyEmailChange($this->token))
            ->toThrow(\InvalidArgumentException::class);

        // ユーザーのメールアドレスは変更されていない
        $this->user->refresh();
        expect($this->user->email)->toBe('old@example.com');
    });

    test('新しいメールアドレスが既に使用されている場合の動作', function () {
        // 別のユーザーが新しいメールアドレスを使用
        User::factory()->create(['email' => 'new@example.com']);

        // 制約違反が発生するが、それをどう処理するかは実装次第
        // ここではそのまま実行し、どうなるかを確認
        expect(fn () => $this->useCase->verifyEmailChange($this->token))
            ->toThrow(\Exception::class);

        // ユーザーのメールアドレスは変更されていない
        $this->user->refresh();
        expect($this->user->email)->toBe('old@example.com');
    });
});
