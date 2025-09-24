<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class DeleteCredentialUseCase
{
    public function execute(User $user, string $credentialId): void
    {
        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->first();

        if (! $credential) {
            throw new \InvalidArgumentException(__('auth.webauthn_credential_not_found'));
        }

        $credential->delete();
    }
}
