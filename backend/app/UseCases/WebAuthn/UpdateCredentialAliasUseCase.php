<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\Log;

class UpdateCredentialAliasUseCase
{
    public function execute(User $user, string $credentialId, string $alias): array
    {
        Log::info('UpdateCredentialAliasUseCase started', [
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'alias' => $alias,
        ]);

        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->whereNull('disabled_at')
            ->first();

        Log::info('Credential search result', [
            'found' => $credential !== null,
            'credential' => $credential ? $credential->toArray() : null,
        ]);

        if (! $credential) {
            Log::warning('WebAuthn credential not found', [
                'user_id' => $user->id,
                'credential_id' => $credentialId,
            ]);
            throw new \InvalidArgumentException(__('auth.webauthn_credential_not_found'));
        }

        try {
            $credential->update([
                'alias' => $alias,
            ]);
            Log::info('Credential alias updated successfully');

            return [
                'id' => $credential->id,
                'alias' => $credential->alias,
                'created_at' => $credential->created_at,
                'disabled_at' => $credential->disabled_at,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update credential alias', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
