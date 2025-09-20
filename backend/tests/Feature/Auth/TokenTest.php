<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('トークン管理', function () {
    test('認証されたユーザーはトークンをリフレッシュできる', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'profile',
                    'roles',
                ],
                'token',
            ]);

        expect($response->json('user.id'))->toBe($user->id);
        expect($response->json('token'))->toBeString();
        expect($response->json('token'))->not->toBe($token);
    });

    test('認証されたユーザーはログアウトできる', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'ログアウトしました',
            ]);

        // トークンが削除されているか確認
        expect($user->tokens()->count())->toBe(0);
    });

    test('未認証のユーザーはトークンをリフレッシュできない', function () {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(401);
    });

    test('未認証のユーザーはログアウトできない', function () {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    });

    test('リフレッシュ後、古いトークンは無効になる', function () {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');
        $oldTokenPlain = $token->plainTextToken;

        // 古いトークンが有効であることを確認
        expect($user->tokens()->count())->toBe(1);

        $response = $this->withToken($oldTokenPlain)->postJson('/api/auth/refresh');

        $response->assertStatus(200);

        // 新しいトークンが作成され、古いトークンが削除されることを確認
        $user->refresh();
        expect($user->tokens()->count())->toBe(1);

        // 新しいトークンが古いトークンと異なることを確認
        $newToken = $response->json('token');
        expect($newToken)->not->toBe($oldTokenPlain);
    });

    test('複数のデバイスでログアウトした場合、現在のトークンのみが削除される', function () {
        $user = User::factory()->create();

        // 複数のトークンを作成
        $user->createToken('device_1');
        $user->createToken('device_2');
        $currentToken = $user->createToken('device_3');

        expect($user->tokens()->count())->toBe(3);

        $response = $this->withToken($currentToken->plainTextToken)->postJson('/api/auth/logout');

        $response->assertStatus(200);

        // 現在のトークンのみが削除されることを確認
        $user->refresh();
        expect($user->tokens()->count())->toBe(2);
    });
});