<?php

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\Auth;

describe('WebAuthnManagementController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Auth::login($this->user);
    });

    test('WebAuthnクレデンシャル一覧を取得できる', function () {
        // ユーザーのクレデンシャルを作成
        $activeCredential = WebauthnCredential::factory()->forUser($this->user)->create([
            'alias' => 'My Security Key',
            'disabled_at' => null,
        ]);

        $disabledCredential = WebauthnCredential::factory()->forUser($this->user)->create([
            'alias' => 'Old Security Key',
            'disabled_at' => now(),
        ]);

        $response = $this->getJson('/api/auth/webauthn/credentials');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'credentials' => [
                    '*' => [
                        'id',
                        'alias',
                        'created_at',
                        'disabled_at',
                    ],
                ],
            ]);

        expect($response->json('success'))->toBe(true);
        // アクティブと無効化されたクレデンシャルの両方が返される
        expect($response->json('credentials'))->toHaveCount(2);

        // 2つのクレデンシャルが返されることを確認（ソート順は不定）
        $credentials = $response->json('credentials');
        $aliases = array_column($credentials, 'alias');

        expect($aliases)->toContain('My Security Key');
        expect($aliases)->toContain('Old Security Key');

        // アクティブと無効化されたクレデンシャルがそれぞれ含まれることを確認
        $hasActive = false;
        $hasDisabled = false;

        foreach ($credentials as $credential) {
            if ($credential['disabled_at'] === null) {
                $hasActive = true;
            } else {
                $hasDisabled = true;
            }
        }

        expect($hasActive)->toBe(true);
        expect($hasDisabled)->toBe(true);
    });

    test('WebAuthnクレデンシャルを削除できる', function () {
        $credential = WebauthnCredential::factory()->forUser($this->user)->create();

        $response = $this->deleteJson("/api/auth/webauthn/credentials/{$credential->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => __('webauthn.credential_deleted'),
            ]);

        // クレデンシャルが削除されている
        expect(WebauthnCredential::find($credential->id))->toBeNull();
    });

    test('存在しないクレデンシャルの削除でエラー', function () {
        $response = $this->deleteJson('/api/auth/webauthn/credentials/non-existent-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => __('webauthn.credential_not_found'),
            ]);
    });

    test('他のユーザーのクレデンシャルは削除できない', function () {
        $otherUser = User::factory()->create();
        $credential = WebauthnCredential::factory()->forUser($otherUser)->create();

        $response = $this->deleteJson("/api/auth/webauthn/credentials/{$credential->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => __('webauthn.credential_not_found'),
            ]);

        // クレデンシャルは削除されていない
        expect(WebauthnCredential::find($credential->id))->not()->toBeNull();
    });

    test('WebAuthnクレデンシャルのエイリアスを更新できる', function () {
        $credential = WebauthnCredential::factory()->forUser($this->user)->create([
            'alias' => 'Old Name',
        ]);

        $response = $this->putJson("/api/auth/webauthn/credentials/{$credential->id}", [
            'alias' => 'New Security Key',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'credential' => [
                    'id',
                    'alias',
                    'created_at',
                ],
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('credential.alias'))->toBe('New Security Key');

        // データベースが更新されている
        $credential->refresh();
        expect($credential->alias)->toBe('New Security Key');
    });

    test('空のエイリアスでバリデーションエラー', function () {
        $credential = WebauthnCredential::factory()->forUser($this->user)->create();

        $response = $this->putJson("/api/auth/webauthn/credentials/{$credential->id}", [
            'alias' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['alias']);
    });

    test('WebAuthnクレデンシャルを無効化できる', function () {
        $credential = WebauthnCredential::factory()->forUser($this->user)->create([
            'disabled_at' => null,
        ]);

        $response = $this->postJson("/api/auth/webauthn/credentials/{$credential->id}/disable");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => __('webauthn.credential_disabled'),
            ]);

        // クレデンシャルが無効化されている
        $credential->refresh();
        expect($credential->disabled_at)->not()->toBeNull();
    });

    test('WebAuthnクレデンシャルを有効化できる', function () {
        $credential = WebauthnCredential::factory()->forUser($this->user)->create([
            'disabled_at' => now(),
        ]);

        $response = $this->postJson("/api/auth/webauthn/credentials/{$credential->id}/enable");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => __('webauthn.credential_enabled'),
            ]);

        // クレデンシャルが有効化されている
        $credential->refresh();
        expect($credential->disabled_at)->toBeNull();
    });

    test('未認証ユーザーはアクセスできない', function () {
        // アプリケーションをリフレッシュして認証をクリア
        $this->refreshApplication();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/auth/webauthn/credentials');
        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->deleteJson('/api/auth/webauthn/credentials/test-id');
        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->putJson('/api/auth/webauthn/credentials/test-id', ['alias' => 'Test']);
        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/auth/webauthn/credentials/test-id/disable');
        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/auth/webauthn/credentials/test-id/enable');
        $response->assertStatus(401);
    });
});
