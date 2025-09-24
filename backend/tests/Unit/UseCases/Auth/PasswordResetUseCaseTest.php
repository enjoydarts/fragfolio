<?php

use App\Models\User;
use App\UseCases\Auth\PasswordResetUseCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

describe('PasswordResetUseCase', function () {
    beforeEach(function () {
        $this->useCase = new PasswordResetUseCase;
        createDefaultRoles();
    });

    describe('sendResetLinkEmail', function () {
        test('存在するメールアドレスでリセットリンクを送信できる', function () {
            $user = User::factory()->create([
                'email' => 'test@example.com',
            ]);

            Password::shouldReceive('sendResetLink')
                ->once()
                ->with(['email' => 'test@example.com'])
                ->andReturn(Password::RESET_LINK_SENT);

            $result = $this->useCase->sendResetLinkEmail('test@example.com');

            expect($result['success'])->toBeTrue();
            expect($result['message'])->toBe(__('auth.reset_link_sent'));
        });

        test('存在しないメールアドレスではリセットリンク送信が失敗する', function () {
            $result = $this->useCase->sendResetLinkEmail('nonexistent@example.com');

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe(__('auth.user_not_found'));
        });

        test('Password::sendResetLinkが失敗した場合はエラーを返す', function () {
            $user = User::factory()->create([
                'email' => 'test@example.com',
            ]);

            Password::shouldReceive('sendResetLink')
                ->once()
                ->with(['email' => 'test@example.com'])
                ->andReturn('passwords.throttled');

            $result = $this->useCase->sendResetLinkEmail('test@example.com');

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe(__('auth.reset_link_failed'));
        });
    });

    describe('resetPassword', function () {
        test('正しいトークンでパスワードリセットができる', function () {
            $user = User::factory()->create();
            $newPassword = 'newpassword123';

            Password::shouldReceive('reset')
                ->once()
                ->andReturn(Password::PASSWORD_RESET);

            $data = [
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'token' => 'valid-token',
            ];

            $result = $this->useCase->resetPassword($data);

            expect($result['success'])->toBeTrue();
            expect($result['message'])->toBe(__('auth.password_reset_success'));
        });

        test('無効なトークンではパスワードリセットが失敗する', function () {
            Password::shouldReceive('reset')
                ->once()
                ->andReturn('passwords.token');

            $data = [
                'email' => 'test@example.com',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'token' => 'invalid-token',
            ];

            $result = $this->useCase->resetPassword($data);

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toBe(__('auth.password_reset_failed'));
        });

        test('パスワードリセット時にハッシュ化とトークン更新が実行される', function () {
            $user = User::factory()->create();
            $newPassword = 'newpassword123';

            // Password::resetのコールバック関数をテスト
            Password::shouldReceive('reset')
                ->once()
                ->andReturnUsing(function ($credentials, $callback) use ($user, $newPassword) {
                    $callback($user, $newPassword);

                    return Password::PASSWORD_RESET;
                });

            $data = [
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'token' => 'valid-token',
            ];

            $result = $this->useCase->resetPassword($data);

            // ユーザーを再読み込みして変更を確認
            $user->refresh();

            expect($result['success'])->toBeTrue();
            expect(Hash::check($newPassword, $user->password))->toBeTrue();
            expect($user->remember_token)->not->toBeNull();
        });
    });
});
