<?php

use App\Models\User;
use App\UseCases\WebAuthn\GenerateRegistrationOptionsUseCase;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreator;

describe('GenerateRegistrationOptionsUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 簡単なモックレスポンス構造を作成
        $mockResponse = new class {
            public $challenge = 'mock-challenge-data';
            public $json;

            public function __construct() {
                $this->json = new class {
                    public function toArray() {
                        return [
                            'challenge' => 'mock-challenge-data',
                            'rp' => ['name' => 'fragfolio', 'id' => 'localhost'],
                            'user' => ['id' => 'mock-user-id', 'name' => 'Test User', 'displayName' => 'Test User'],
                            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7]],
                            'authenticatorSelection' => ['userVerification' => 'preferred'],
                            'attestation' => 'none'
                        ];
                    }
                };
            }

            public function thenReturn() {
                return $this;
            }
        };

        // AttestationCreatorをモック
        $this->attestationCreator = \Mockery::mock(AttestationCreator::class);
        $this->attestationCreator->shouldReceive('send')
            ->andReturnSelf();
        $this->attestationCreator->shouldReceive('thenReturn')
            ->andReturn($mockResponse);

        $this->useCase = new GenerateRegistrationOptionsUseCase($this->attestationCreator);
        createDefaultRoles();
    });

    test('WebAuthn登録オプションが生成される', function () {
        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('challenge');
        expect($result)->toHaveKey('challenge_key');
        expect($result)->toHaveKey('rp');
        expect($result)->toHaveKey('user');
    });

    test('基本構造が正しく生成される', function () {
        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect($result['challenge'])->toBe('mock-challenge-data');
        expect($result['rp']['name'])->toBe('fragfolio');
        expect($result['user']['name'])->toBe('Test User');
    });
});