<?php

namespace App\WebAuthn;

use Illuminate\Support\Facades\Cache;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

class CacheChallengeRepository implements WebAuthnChallengeRepository
{
    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        $userId = $ceremony->user->getAuthIdentifier();
        $type = $ceremony instanceof AttestationCreation ? 'attestation' : 'assertion';
        $key = $this->getCacheKey($userId, $type);

        Cache::put($key, $challenge, now()->addMinutes(10));
    }

    public function pull(AttestationValidation|AssertionValidation $ceremony): ?Challenge
    {
        $userId = $ceremony->user->getAuthIdentifier();
        $type = $ceremony instanceof AttestationValidation ? 'attestation' : 'assertion';
        $key = $this->getCacheKey($userId, $type);

        $challenge = Cache::pull($key);

        return $challenge instanceof Challenge ? $challenge : null;
    }

    private function getCacheKey(string $userId, string $type): string
    {
        return "webauthn_challenge:{$type}:{$userId}";
    }
}
