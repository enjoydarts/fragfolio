<?php

use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

describe('AuthController', function () {
    beforeEach(function () {
        // TurnstileServiceをモック
        $this->mock(TurnstileService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('verify')->andReturn(true);
        });
    });

    test('現在のユーザー情報を取得できる', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        Auth::login($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'two_factor_enabled',
                    'profile',
                    'roles',
                ],
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('user.id'))->toBe($user->id);
        expect($response->json('user.email'))->toBe('test@example.com');
    });

    test('未認証の場合は401エラー', function () {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    });

    test('ログアウトができる', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(204);

        // ユーザーがログアウトされている
        expect(Auth::check())->toBe(false);
    });

    test('未認証でもログアウトリクエストは成功する', function () {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    });

    test('プロフィール更新ができる', function () {
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        Auth::login($user);

        $updateData = [
            'name' => 'New Name',
            'language' => 'en',
            'timezone' => 'UTC',
            'bio' => 'Updated bio',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'country' => 'US',
        ];

        $response = $this->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'profile',
                ],
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('user.name'))->toBe('New Name');

        // データベースが更新されている
        $user->refresh();
        expect($user->name)->toBe('New Name');
        expect($user->profile->language)->toBe('en');
        expect($user->profile->bio)->toBe('Updated bio');
    });

    test('プロフィール更新で不正なデータはバリデーションエラー', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $response = $this->putJson('/api/auth/profile', [
            'name' => '', // 空の名前
            'language' => 'invalid-language',
            'timezone' => 'invalid-timezone',
            'date_of_birth' => 'invalid-date',
            'gender' => 'invalid-gender',
            'country' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'language',
            ]);

        // データベースは更新されていない
        $user->refresh();
        expect($user->name)->not()->toBe('');
    });

    test('未認証ユーザーはプロフィール更新できない', function () {
        $response = $this->putJson('/api/auth/profile', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(401);
    });

    test('トークン更新ができる', function () {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user',
                'token',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('token'))->toBeString();
        expect($response->json('token'))->not()->toBe($token);
    });

    test('無効なトークンでトークン更新はエラー', function () {
        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    });

    test('ユーザー登録ができる', function () {
        $userData = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'language' => 'ja',
            'timezone' => 'Asia/Tokyo',
            'cf-turnstile-response' => 'test-token',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'profile',
                    'roles',
                ],
                'token',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('user.name'))->toBe('New User');
        expect($response->json('user.email'))->toBe('new@example.com');

        // データベースにユーザーが作成されている
        expect(User::where('email', 'new@example.com')->exists())->toBe(true);
    });

    test('既存のメールアドレスでの登録はエラー', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'cf-turnstile-response' => 'test-token',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('パスワード確認が一致しない場合はエラー', function () {
        $userData = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'cf-turnstile-response' => 'test-token',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('ログイン（2FA無効ユーザー）ができる', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'cf-turnstile-response' => 'test-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user',
                'token',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('user.id'))->toBe($user->id);
    });

    test('ログイン（2FA有効ユーザー）で2FA要求される', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'cf-turnstile-response' => 'test-token',
        ]);

        // 2FAが有効なユーザーの場合、2FA要求レスポンスが返される
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'requires_two_factor',
                'temp_token',
                'available_methods',
                'message',
            ]);

        expect($response->json('success'))->toBe(false);
        expect($response->json('requires_two_factor'))->toBe(true);
        expect($response->json('temp_token'))->toBeString();
    });

    test('間違ったパスワードでログインエラー', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'cf-turnstile-response' => 'test-token',
        ]);

        $response->assertStatus(422);
    });
});