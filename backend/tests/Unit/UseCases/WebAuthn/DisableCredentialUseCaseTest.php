<?php

use App\Models\User;
use App\UseCases\WebAuthn\DisableCredentialUseCase;
use Laragear\WebAuthn\Models\WebAuthnCredential;

describe('DisableCredentialUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->useCase = new DisableCredentialUseCase;
        createDefaultRoles();
    });

    test('WebAuthnクレデンシャルを無効化できる', function () {
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => base64_encode(random_bytes(64)),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        $this->useCase->execute($this->user, $credential->id);

        // クレデンシャルが無効化されている
        $credential->refresh();
        expect($credential->disabled_at)->not()->toBeNull();
    });

    test('存在しないクレデンシャルIDでエラー', function () {
        expect(fn () => $this->useCase->execute($this->user, 'non-existent-id'))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('他のユーザーのクレデンシャルは無効化できない', function () {
        $otherUser = User::factory()->create();
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id-2',
            'authenticatable_id' => $otherUser->id,
            'authenticatable_type' => User::class,
            'user_id' => base64_encode(random_bytes(64)),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        expect(fn () => $this->useCase->execute($this->user, $credential->id))
            ->toThrow(\InvalidArgumentException::class);

        // クレデンシャルは変更されていない
        $credential->refresh();
        expect($credential->disabled_at)->toBeNull();
    });

    test('既に無効化されたクレデンシャルでエラー', function () {
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id-3',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => base64_encode(random_bytes(64)),
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

        expect(fn () => $this->useCase->execute($this->user, $credential->id))
            ->toThrow(\InvalidArgumentException::class);
    });
});
