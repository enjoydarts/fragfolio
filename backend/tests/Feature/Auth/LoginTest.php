<?php

use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Support\Facades\Hash;

describe('ユーザーログイン', function () {
    beforeEach(function () {
        // TurnstileServiceをモック
        $this->mock(TurnstileService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('verify')->andReturn(true);
        });
    });

    test('正常なログインができる', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'cf-turnstile-response' => 'test_token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'profile',

                ],
                'token',
            ]);

        expect($response->json('user.id'))->toBe($user->id);
        expect($response->json('token'))->toBeString();
    });

    test('記憶するオプション付きでログインができる', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'remember' => true,
            'cf-turnstile-response' => 'test_token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'token',
            ]);
    });

    test('間違ったパスワードではログインできない', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
            'cf-turnstile-response' => 'test_token',
        ]);

        $response->assertStatus(422);
    });

    test('存在しないメールアドレスではログインできない', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
            'cf-turnstile-response' => 'test_token',
        ]);

        $response->assertStatus(422);
    });

    test('必須フィールドが不足している場合はログインできない', function () {
        $response = $this->postJson('/api/login', [
            'cf-turnstile-response' => 'test_token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

    test('無効なメールアドレスではログインできない', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'cf-turnstile-response' => 'test_token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});
