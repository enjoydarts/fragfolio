<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\Log;

class DisableCredentialUseCase
{
    public function execute(User $user, string $credentialId): void
    {
        Log::info('DisableCredentialUseCase started', [
            'user_id' => $user->id,
            'credential_id' => $credentialId,
        ]);

        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->whereNull('disabled_at')
            ->first();

        Log::info('Credential search result for disable', [
            'found' => $credential !== null,
            'credential' => $credential ? $credential->toArray() : null,
        ]);

        if (! $credential) {
            Log::warning('WebAuthn credential not found for disable', [
                'user_id' => $user->id,
                'credential_id' => $credentialId,
            ]);
            throw new \InvalidArgumentException(__('auth.webauthn_credential_not_found'));
        }

        try {
            $credential->update(['disabled_at' => now()]);
            Log::info('Credential disabled successfully');
        } catch (\Exception $e) {
            Log::error('Failed to disable credential', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
