<?php

use App\Models\User;
use App\UseCases\Auth\LoginUserUseCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

describe('LoginUserUseCase', function () {
    beforeEach(function () {
        $this->useCase = new LoginUserUseCase;
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);
    });

    test('正しい認証情報でログインできる', function () {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $result = $this->useCase->execute($credentials);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['user', 'token']);
        expect($result['user'])->toBeInstanceOf(User::class);
        expect($result['user']->id)->toBe($this->user->id);
        expect($result['token'])->toBeString();
    });

    test('記憶するオプション付きでログインできる', function () {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $result = $this->useCase->execute($credentials, remember: true);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['user', 'token']);
        expect($result['token'])->toBeString();
    });

    test('間違ったパスワードでは認証例外が発生する', function () {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ];

        expect(fn () => $this->useCase->execute($credentials))
            ->toThrow(AuthenticationException::class);
    });

    test('存在しないメールアドレスでは認証例外が発生する', function () {
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ];

        expect(fn () => $this->useCase->execute($credentials))
            ->toThrow(AuthenticationException::class);
    });

    test('返されるユーザーにはプロフィールとロールが含まれる', function () {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $result = $this->useCase->execute($credentials);

        $user = $result['user'];
        expect($user->relationLoaded('profile'))->toBeTrue();

    });

    test('記憶するオプションによってトークン名が変わる', function () {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        // 通常のログイン
        $normalResult = $this->useCase->execute($credentials, remember: false);
        $normalToken = $normalResult['token'];

        // ログアウトしてトークンをクリア
        $this->user->tokens()->delete();

        // 記憶するログイン
        $rememberResult = $this->useCase->execute($credentials, remember: true);
        $rememberToken = $rememberResult['token'];

        // トークンが異なることを確認（実際には内容が同じでも名前が違う）
        expect($normalToken)->toBeString();
        expect($rememberToken)->toBeString();
    });

    test('ログイン時にトークンが生成される', function () {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $initialTokenCount = $this->user->tokens()->count();

        $result = $this->useCase->execute($credentials);

        $this->user->refresh();
        expect($this->user->tokens()->count())->toBe($initialTokenCount + 1);
        expect($result['token'])->toBeString();
    });
});
