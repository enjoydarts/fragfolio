<?php

use App\Models\User;
use App\UseCases\WebAuthn\UpdateCredentialAliasUseCase;
use Laragear\WebAuthn\Models\WebAuthnCredential;

describe('UpdateCredentialAliasUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->useCase = new UpdateCredentialAliasUseCase;
        createDefaultRoles();
    });

    test('WebAuthnクレデンシャルのエイリアスを更新できる', function () {
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Old Name',
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        $newAlias = 'New Security Key';
        $result = $this->useCase->execute($this->user, $credential->id, $newAlias);

        expect($result)->toBeArray();
        expect($result['alias'])->toBe($newAlias);

        // データベースが更新されている
        $credential->refresh();
        expect($credential->alias)->toBe($newAlias);
    });

    test('存在しないクレデンシャルIDでエラー', function () {
        expect(fn () => $this->useCase->execute($this->user, 'non-existent-id', 'New Name'))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('他のユーザーのクレデンシャルは更新できない', function () {
        $otherUser = User::factory()->create();
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id-2',
            'authenticatable_id' => $otherUser->id,
            'authenticatable_type' => User::class,
            'user_id' => $otherUser->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Original Name',
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        expect(fn () => $this->useCase->execute($this->user, $credential->id, 'New Name'))
            ->toThrow(\InvalidArgumentException::class);

        // エイリアスは変更されていない
        $credential->refresh();
        expect($credential->alias)->toBe('Original Name');
    });

    test('空のエイリアスでも処理される', function () {
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id-3',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Original',
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        $result = $this->useCase->execute($this->user, $credential->id, '');

        expect($result)->toBeArray();
        expect($result['alias'])->toBe('');
    });

    test('長すぎるエイリアスでデータベースエラー', function () {
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id-4',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Original',
            'disabled_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        $longAlias = str_repeat('a', 256); // 255文字を超える

        expect(fn () => $this->useCase->execute($this->user, $credential->id, $longAlias))
            ->toThrow(\Exception::class);
    });

    test('無効化されたクレデンシャルは更新できない', function () {
        $credential = new WebAuthnCredential;
        $credential->forceFill([
            'id' => 'test-credential-id-5',
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => User::class,
            'user_id' => $this->user->webAuthnId()->toString(),
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_format' => 'none',
            'public_key' => base64_encode(random_bytes(77)),
            'alias' => 'Old Name',
            'disabled_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ])->save();

        expect(fn () => $this->useCase->execute($this->user, $credential->id, 'New Name'))
            ->toThrow(\InvalidArgumentException::class);
    });
});
