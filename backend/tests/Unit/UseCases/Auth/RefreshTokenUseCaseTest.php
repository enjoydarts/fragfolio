<?php

use App\Models\User;
use App\UseCases\Auth\RefreshTokenUseCase;
use Laravel\Sanctum\Sanctum;

describe('RefreshTokenUseCase', function () {
    beforeEach(function () {
        $this->useCase = new RefreshTokenUseCase;
        $this->user = User::factory()->create();

        // 既存のトークンをクリア
        $this->user->tokens()->delete();
    });

    test('トークンを正常にリフレッシュできる', function () {
        // 古いトークンを作成
        $oldToken = $this->user->createToken('old_token');
        Sanctum::actingAs($this->user, [], 'web');

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['user', 'token']);
        expect($result['user'])->toBeInstanceOf(User::class);
        expect($result['user']->id)->toBe($this->user->id);
        expect($result['token'])->toBeString();
    });

    test('古いトークンが削除される', function () {
        // 古いトークンを作成
        $oldToken = $this->user->createToken('old_token');
        $oldTokenId = $oldToken->accessToken->id;

        // 現在のトークンとして設定
        $this->user->withAccessToken($oldToken->accessToken);

        expect($this->user->tokens()->count())->toBe(1);

        $result = $this->useCase->execute($this->user);

        // 新しいトークンが作成されているが、古いトークンは削除されている
        $this->user->refresh();
        expect($this->user->tokens()->count())->toBe(1);
        expect($this->user->tokens()->where('id', $oldTokenId)->exists())->toBeFalse();
    });

    test('新しいトークンが作成される', function () {
        $oldTokenResult = $this->user->createToken('old_token');
        $oldToken = $oldTokenResult->plainTextToken;
        $this->user->withAccessToken($oldTokenResult->accessToken);

        $result = $this->useCase->execute($this->user);

        $newToken = $result['token'];
        expect($newToken)->toBeString();
        expect($newToken)->not->toBe($oldToken);
    });

    test('返されるユーザーにはプロフィールとロールが含まれる', function () {
        $tokenResult = $this->user->createToken('test_token');
        $this->user->withAccessToken($tokenResult->accessToken);

        $result = $this->useCase->execute($this->user);

        $user = $result['user'];
        expect($user->relationLoaded('profile'))->toBeTrue();

    });

    test('複数のトークンがある場合、現在のトークンのみが削除される', function () {
        // 複数のトークンを作成
        $token1 = $this->user->createToken('token_1');
        $token2 = $this->user->createToken('token_2');
        $token3 = $this->user->createToken('token_3');

        // token2を現在のトークンとして設定
        $this->user->withAccessToken($token2->accessToken);

        expect($this->user->tokens()->count())->toBe(3);

        $result = $this->useCase->execute($this->user);

        // 新しいトークンが作成され、現在のトークンが削除される
        $this->user->refresh();
        expect($this->user->tokens()->count())->toBe(3); // 2つの古いトークン + 1つの新しいトークン

        // 新しいトークンが生成されている
        expect($result['token'])->toBeString();
    });

    test('トークンがない状態でもリフレッシュできる', function () {
        // トークンなしでユーザーを設定
        expect($this->user->tokens()->count())->toBe(0);

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['user', 'token']);
        expect($result['token'])->toBeString();

        $this->user->refresh();
        expect($this->user->tokens()->count())->toBe(1);
    });

    test('新しいトークンの名前は auth_token になる', function () {
        $oldToken = $this->user->createToken('old_token');
        $this->user->withAccessToken($oldToken->accessToken);

        $result = $this->useCase->execute($this->user);

        $this->user->refresh();
        $newToken = $this->user->tokens()->latest()->first();
        expect($newToken->name)->toBe('auth_token');
    });
});
