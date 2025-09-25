<?php

use App\Models\User;
use App\UseCases\Auth\VerifyEmailUseCase;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;

describe('VerifyEmailUseCase', function () {
    beforeEach(function () {
        $this->useCase = new VerifyEmailUseCase;
        createDefaultRoles();
        Event::fake();
    });

    describe('execute', function () {
        test('未認証のユーザーのメールアドレスを認証できる', function () {
            $user = User::factory()->create([
                'email_verified_at' => null,
            ]);

            $result = $this->useCase->execute($user);

            expect($result['success'])->toBeTrue();
            expect($result['message'])->toBe(__('auth.email_verified'));

            $user->refresh();
            expect($user->email_verified_at)->not->toBeNull();
            expect($user->hasVerifiedEmail())->toBeTrue();

            Event::assertDispatched(Verified::class);
        });

        test('既に認証済みのユーザーでは認証済みメッセージを返す', function () {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            $result = $this->useCase->execute($user);

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe(__('auth.email_already_verified'));

            Event::assertNotDispatched(Verified::class);
        });
    });

    describe('verifyFromLink', function () {
        test('有効なリンクでメールアドレスを認証できる', function () {
            $user = User::factory()->create([
                'email_verified_at' => null,
            ]);

            $result = $this->useCase->verifyFromLink($user->id, sha1($user->email));

            expect($result['success'])->toBeTrue();
            expect($result['message'])->toBe(__('auth.email_verified'));

            $user->refresh();
            expect($user->hasVerifiedEmail())->toBeTrue();
        });

        test('無効なハッシュでは認証が失敗する', function () {
            $user = User::factory()->create([
                'email_verified_at' => null,
            ]);

            $result = $this->useCase->verifyFromLink($user->id, 'invalid-hash');

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe(__('auth.invalid_verification_link'));
        });

        test('既に認証済みのユーザーでは認証済みメッセージを返す', function () {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            $result = $this->useCase->verifyFromLink($user->id, sha1($user->email));

            expect($result['success'])->toBeTrue();
            expect($result['message'])->toBe(__('auth.email_already_verified'));
        });
    });

    describe('resendVerificationEmail', function () {
        test('未認証ユーザーに認証メールを再送信できる', function () {
            $user = User::factory()->create([
                'email_verified_at' => null,
            ]);

            $result = $this->useCase->resendVerificationEmail($user);

            expect($result['success'])->toBeTrue();
            expect($result['message'])->toBe(__('auth.verification_email_resent'));
        });

        test('既に認証済みのユーザーでは再送信しない', function () {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            $result = $this->useCase->resendVerificationEmail($user);

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe(__('auth.email_already_verified'));
        });
    });
});
