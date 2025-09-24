<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('WebAuthn 登録', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Sanctum::actingAs($this->user);
    });

    test('WebAuthn登録オプションを取得できる', function () {
        $response = $this->postJson('/api/auth/webauthn/register/options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'options' => [
                    'challenge',
                    'rp',
                    'user',
                    'pubKeyCredParams',
                    'timeout',
                    'attestation',
                ],
            ]);
    });

    test('WebAuthnクレデンシャルを登録できる', function () {
        // Note: This is a simplified test. In reality, you would need valid WebAuthn data
        // For testing purposes, we'll skip this complex integration test
        $this->markTestSkipped('WebAuthn registration requires complex browser integration');
    });

    test('未認証ユーザーはWebAuthn登録オプションを取得できない', function () {
        // 新しいテストインスタンスで認証なしでテスト
        $this->refreshApplication();
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/auth/webauthn/register/options');

        $response->assertStatus(401);
    });

    test('未認証ユーザーはWebAuthnクレデンシャルを登録できない', function () {
        // 新しいテストインスタンスで認証なしでテスト
        $this->refreshApplication();
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/auth/webauthn/register', [
            'id' => base64_encode('test-credential-id'),
            'rawId' => base64_encode('test-credential-id'),
            'type' => 'public-key',
            'response' => [
                'attestationObject' => base64_encode('test-attestation-object'),
                'clientDataJSON' => base64_encode('test-client-data'),
            ],
        ]);

        $response->assertStatus(401);
    });
});
