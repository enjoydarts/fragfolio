<?php

use App\Models\User;
use App\UseCases\WebAuthn\RegisterCredentialUseCase;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidator;
use Illuminate\Support\Facades\Cache;

describe('RegisterCredentialUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // AttestationValidatorをモック
        $this->attestationValidator = \Mockery::mock(AttestationValidator::class);

        // 成功時のモックレスポンスを設定
        $this->attestationValidator->shouldReceive('send')
            ->andReturnSelf();
        $this->attestationValidator->shouldReceive('thenReturn')
            ->andReturn(new class {
                public $success = true;
                public $credential;

                public function __construct() {
                    $this->credential = new class {
                        public $id = 'mock-credential-id';
                        public $user_id = 'mock-user-id';
                        public $counter = 0;
                        public $rp_id = 'localhost';
                        public $origin = 'http://localhost';
                        public $alias;

                        public function save() {
                            return true;
                        }
                    };
                }
            });

        $this->useCase = new RegisterCredentialUseCase($this->attestationValidator);
        createDefaultRoles();

        // モックチャレンジをキャッシュに設定
        Cache::put('test-challenge-key', 'mock-challenge-data', now()->addMinutes(10));
    });

    test('基本的なWebAuthn登録が動作する', function () {
        $credentialData = [
            'id' => 'credential-id-123',
            'type' => 'public-key',
            'response' => [
                'attestationObject' => base64_encode('test-attestation-object'),
                'clientDataJSON' => base64_encode('test-client-data'),
            ],
        ];

        // void戻り値のため例外がスローされなければOK
        $this->useCase->execute($this->user, $credentialData, 'test-challenge-key', 'My Security Key');

        // テストが例外なく完了すれば成功
        expect(true)->toBe(true);
    });

    test('無効なチャレンジキーでエラー', function () {
        $credentialData = [
            'id' => 'credential-id-123',
            'type' => 'public-key',
            'response' => [],
        ];

        expect(fn () => $this->useCase->execute($this->user, $credentialData, 'invalid-challenge-key'))
            ->toThrow(\InvalidArgumentException::class);
    });
});