<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreator;

class GenerateRegistrationOptionsUseCase
{
    public function __construct(
        private AttestationCreator $attestationCreator
    ) {}

    public function execute(User $user): array
    {
        try {
            \Log::info('WebAuthn registration options generation started', ['user_id' => $user->id]);

            // WebAuthn登録オプションを生成
            $attestation = new AttestationCreation(
                user: $user
            );

            $options = $this->attestationCreator
                ->send($attestation)
                ->thenReturn();

            \Log::info('WebAuthn options generated successfully', ['user_id' => $user->id]);

            // チャレンジをキャッシュに保存（ユーザーIDとチャレンジを関連付け）
            $challengeKey = "webauthn_challenge:{$user->id}:" . Str::random(40);
            Cache::put($challengeKey, $options->challenge, now()->addMinutes(10));

            \Log::info('Challenge stored in cache', ['challenge_key' => $challengeKey, 'user_id' => $user->id]);

            // レスポンスにチャレンジキーを含める
            $response = $options->json->toArray();
            $response['challenge_key'] = $challengeKey;

            \Log::info('WebAuthn registration options generated successfully', ['user_id' => $user->id, 'challenge_key' => $challengeKey]);

            return $response;
        } catch (\Exception $e) {
            \Log::error('Failed to generate WebAuthn registration options', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}