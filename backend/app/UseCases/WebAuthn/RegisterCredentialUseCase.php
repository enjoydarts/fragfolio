<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidator;
use Laragear\WebAuthn\JsonTransport;

class RegisterCredentialUseCase
{
    public function __construct(
        private AttestationValidator $attestationValidator
    ) {}

    public function execute(User $user, array $credentialData, string $challengeKey, ?string $alias = null): void
    {
        // キャッシュからチャレンジを取得
        $storedChallenge = Cache::get($challengeKey);
        if (! $storedChallenge) {
            throw new \InvalidArgumentException('Challenge has expired or is invalid.');
        }

        // AttestationValidationオブジェクトを作成
        $attestation = new AttestationValidation(
            user: $user,
            json: new JsonTransport($credentialData)
        );

        // WebAuthn認証情報を検証
        $validatedAttestation = $this->attestationValidator
            ->send($attestation)
            ->thenReturn();

        // 認証情報をデータベースに保存
        $webauthnCredential = $validatedAttestation->credential;
        if ($alias) {
            $webauthnCredential->alias = $alias;
        }
        $webauthnCredential->save();

        // チャレンジをキャッシュから削除
        Cache::forget($challengeKey);
    }
}
