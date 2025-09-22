<?php

namespace App\UseCases\WebAuthn;

use App\Models\User;
use App\Models\WebAuthnCredential;

class GetCredentialsUseCase
{
    public function execute(User $user): array
    {
        $credentials = WebAuthnCredential::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($credential) {
                return [
                    'id' => $credential->id,
                    'alias' => $credential->alias,
                    'created_at' => $credential->created_at,
                    'disabled_at' => $credential->disabled_at,
                ];
            })
            ->toArray();

        return $credentials;
    }
}