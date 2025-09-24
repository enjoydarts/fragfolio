<?php

use App\Models\User;
use App\Models\WebauthnCredential;

describe('WebAuthn ログイン', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // WebAuthnクレデンシャルを作成
        $this->credential = WebauthnCredential::factory()->forUser($this->user)->create();
    });

    test('WebAuthnチャレンジオプションを取得できる', function () {
        $response = $this->postJson('/api/auth/webauthn/login/options', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'challenge',
                'allowCredentials',
            ]);
    });

    test('WebAuthn複雑な統合テストはスキップ', function () {
        $this->markTestSkipped('WebAuthn login requires complex browser integration and valid credentials');
    });
});
