<?php

use App\Models\User;
use App\Models\EmailChangeRequest;
use App\UseCases\Auth\RequestEmailChangeUseCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyNewEmailChange;

describe('RequestEmailChangeUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'old@example.com',
        ]);
        $this->useCase = new RequestEmailChangeUseCase();

        // 既存のリクエストをクリア
        EmailChangeRequest::where('user_id', $this->user->id)->delete();

        Mail::fake();
    });

    test('メールアドレス変更リクエストを作成できる', function () {
        $result = $this->useCase->execute($this->user, 'new@example.com');

        expect($result)->toBeInstanceOf(EmailChangeRequest::class);
        expect($result->user_id)->toBe($this->user->id);
        expect($result->new_email)->toBe('new@example.com');
        expect($result->token)->not()->toBeNull();

        // データベースにリクエストが保存されている
        $request = EmailChangeRequest::where('user_id', $this->user->id)->first();
        expect($request)->not()->toBeNull();
        expect($request->new_email)->toBe('new@example.com');
        expect($request->token)->not()->toBeNull();

        // メールがキューに入れられている
        Mail::assertQueued(VerifyNewEmailChange::class, function ($mail) {
            return $mail->hasTo('new@example.com');
        });
    });

    test('同じメールアドレスへの変更はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user, 'old@example.com'))
            ->toThrow(\InvalidArgumentException::class);

        // リクエストは作成されない
        expect(EmailChangeRequest::where('user_id', $this->user->id)->exists())->toBe(false);
        Mail::assertNotQueued(VerifyNewEmailChange::class);
    });

    test('既に使用されているメールアドレスはエラー', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        expect(fn () => $this->useCase->execute($this->user, 'existing@example.com'))
            ->toThrow(\InvalidArgumentException::class);

        expect(EmailChangeRequest::where('user_id', $this->user->id)->exists())->toBe(false);
        Mail::assertNotQueued(VerifyNewEmailChange::class);
    });

    test('既存のリクエストがある場合は更新される', function () {
        // 既存のリクエストを作成
        $existingRequest = EmailChangeRequest::create([
            'user_id' => $this->user->id,
            'new_email' => 'old-request@example.com',
            'token' => 'old-token',
            'expires_at' => now()->addHours(1),
        ]);

        $result = $this->useCase->execute($this->user, 'new@example.com');

        expect($result)->toBeInstanceOf(EmailChangeRequest::class);
        expect($result->new_email)->toBe('new@example.com');

        // 新しいリクエストが作成されている（古いものは削除される）
        expect(EmailChangeRequest::where('user_id', $this->user->id)->count())->toBe(1);
        expect(EmailChangeRequest::where('user_id', $this->user->id)->first()->new_email)->toBe('new@example.com');
    });

    test('無効なメールアドレス形式はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user, 'invalid-email'))
            ->toThrow(\InvalidArgumentException::class);

        expect(EmailChangeRequest::where('user_id', $this->user->id)->exists())->toBe(false);
        Mail::assertNotQueued(VerifyNewEmailChange::class);
    });

    test('トークンが正しく生成される', function () {
        $result = $this->useCase->execute($this->user, 'new@example.com');

        expect($result)->toBeInstanceOf(EmailChangeRequest::class);
        expect($result->token)->toBeString();
        expect(strlen($result->token))->toBe(60); // Laravel標準のトークン長
    });

    test('有効期限が正しく設定される', function () {
        $before = now()->addHours(24)->subMinute();

        $result = $this->useCase->execute($this->user, 'new@example.com');

        $after = now()->addHours(24)->addMinute();

        expect($result)->toBeInstanceOf(EmailChangeRequest::class);
        expect($result->expires_at->between($before, $after))->toBe(true);
    });
});