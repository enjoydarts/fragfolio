<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\Log;

class EnableCredentialUseCase
{
    public function execute(User $user, string $credentialId): void
    {
        Log::info('EnableCredentialUseCase started', [
            'user_id' => $user->id,
            'credential_id' => $credentialId,
        ]);

        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->whereNotNull('disabled_at')
            ->first();

        Log::info('Credential search result for enable', [
            'found' => $credential !== null,
            'credential' => $credential ? $credential->toArray() : null,
        ]);

        if (! $credential) {
            Log::warning('WebAuthn credential not found for enable', [
                'user_id' => $user->id,
                'credential_id' => $credentialId,
            ]);
            throw new \InvalidArgumentException(__('auth.webauthn_disabled_credential_not_found'));
        }

        try {
            $credential->disabled_at = null;
            $credential->save();
            Log::info('Credential enabled successfully');
        } catch (\Exception $e) {
            Log::error('Failed to enable credential', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
