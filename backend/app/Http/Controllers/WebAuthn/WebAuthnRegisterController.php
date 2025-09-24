<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class WebAuthnRegisterController
{
    /**
     * Returns a challenge to be verified by the user device.
     */
    public function options(AttestationRequest $request): \Illuminate\Http\JsonResponse
    {
        $attestation = $request
            ->fastRegistration()
            ->toCreate();

        return response()->json([
            'success' => true,
            'options' => $attestation,
        ]);
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response
    {
        // WebAuthn認証器を登録
        $credentialId = $request->save();

        // エイリアスが提供されている場合は設定
        if ($request->has('alias') && ! empty($request->input('alias'))) {
            $alias = $request->input('alias');

            // 登録された認証器をIDで検索してエイリアスを設定
            $credential = \App\Models\WebAuthnCredential::find($credentialId);
            if ($credential) {
                $credential->update(['alias' => $alias]);

                Log::info('WebAuthn credential registered with alias', [
                    'credential_id' => $credentialId,
                    'alias' => $alias,
                    'user_id' => $request->user()->id,
                ]);
            } else {
                Log::warning('WebAuthn credential not found for alias update', [
                    'credential_id' => $credentialId,
                    'user_id' => $request->user()->id,
                ]);
            }
        } else {
            Log::info('WebAuthn credential registered without alias', [
                'credential_id' => $credentialId,
                'user_id' => $request->user()->id,
            ]);
        }

        return response()->noContent();
    }
}
