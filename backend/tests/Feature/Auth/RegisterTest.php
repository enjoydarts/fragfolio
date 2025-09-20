<?php

use App\Models\User;
use App\Services\TurnstileService;

describe('ユーザー登録', function () {
    beforeEach(function () {
        createDefaultRoles();

        // TurnstileServiceをモック
        $this->mock(TurnstileService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('verify')->andReturn(true);
        });
    });
    test('正常なユーザー登録ができる', function () {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'language' => 'ja',
            'timezone' => 'Asia/Tokyo',
            'turnstile_token' => 'test_token',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'profile' => [
                        'language',
                        'timezone',
                    ],
                    'roles',
                ],
                'token',
            ]);

        expect(User::where('email', 'test@example.com')->exists())->toBeTrue();

        $user = User::where('email', 'test@example.com')->first();
        expect($user->profile->language)->toBe('ja');
        expect($user->profile->timezone)->toBe('Asia/Tokyo');
        expect($user->hasRole('user'))->toBeTrue();
    });

    test('既存のメールアドレスでは登録できない', function () {
        User::factory()->create(['email' => 'test@example.com']);

        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'turnstile_token' => 'test_token',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('必須フィールドが不足している場合は登録できない', function () {
        $response = $this->postJson('/api/auth/register', [
            'turnstile_token' => 'test_token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    test('無効なメールアドレスでは登録できない', function () {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'turnstile_token' => 'test_token',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('短すぎるパスワードでは登録できない', function () {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
            'turnstile_token' => 'test_token',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });
});
