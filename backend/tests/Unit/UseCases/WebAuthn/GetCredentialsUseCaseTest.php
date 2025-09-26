<?php

use App\Models\User;
use App\UseCases\WebAuthn\GetCredentialsUseCase;
use Laragear\WebAuthn\Models\WebAuthnCredential;

describe('GetCredentialsUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->useCase = new GetCredentialsUseCase;
        createDefaultRoles();
    });

    test('ユーザーのWebAuthnクレデンシャル一覧を取得できる', function () {
        // アクティブなクレデンシャルを作成
        $activeCredential = new WebAuthnCredential;
        $activeCredential->forceFill([
            'id' => 'active-credential-id',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'My Security Key',
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        // 無効化されたクレデンシャルを作成
        $disabledCredential = new WebAuthnCredential;
        $disabledCredential->forceFill([
            'id' => 'disabled-credential-id',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Old Security Key',
            'disabled_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        // 他のユーザーのクレデンシャルを作成
        $otherUser = User::factory()->create();
        $otherUserCredential = new WebAuthnCredential;
        $otherUserCredential->forceFill([
            'id' => 'other-credential-id',
            'authenticatable_id' => $otherUser->id,
            'authenticatable_type' => User::class,
            'user_id' => $otherUser->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Other User Key',
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        $result = $this->useCase->execute($this->user);

        // アクティブと無効化されたクレデンシャルの両方が返されることを確認
        expect($result)->toHaveCount(2);

        // 2つのクレデンシャルが返されることを確認（ソート順は不定）
        $foundActive = false;
        $foundDisabled = false;

        foreach ($result as $credential) {
            if ($credential['id'] === $activeCredential->id) {
                expect($credential['alias'])->toBe('My Security Key');
                expect($credential['disabled_at'])->toBeNull();
                $foundActive = true;
            }
            if ($credential['id'] === $disabledCredential->id) {
                expect($credential['alias'])->toBe('Old Security Key');
                expect($credential['disabled_at'])->not->toBeNull();
                $foundDisabled = true;
            }
        }

        expect($foundActive)->toBe(true);
        expect($foundDisabled)->toBe(true);
    });

    test('クレデンシャルがない場合は空のコレクションを返す', function () {
        $result = $this->useCase->execute($this->user);

        expect($result)->toHaveCount(0);
        expect(empty($result))->toBe(true);
    });

    test('全て無効化されたクレデンシャルの場合は無効化されたものが返される', function () {
        $disabledCredential = new WebAuthnCredential;
        $disabledCredential->forceFill([
            'id' => 'disabled-only-credential-id',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'disabled_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        $result = $this->useCase->execute($this->user);

        expect($result)->toHaveCount(1);
        expect($result[0]['disabled_at'])->not->toBeNull();
    });
});
