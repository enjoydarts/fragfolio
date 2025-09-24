<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\UseCases\WebAuthn\DeleteCredentialUseCase;
use App\UseCases\WebAuthn\DisableCredentialUseCase;
use App\UseCases\WebAuthn\EnableCredentialUseCase;
use App\UseCases\WebAuthn\GetCredentialsUseCase;
use App\UseCases\WebAuthn\UpdateCredentialAliasUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnManagementController extends Controller
{
    public function __construct(
        private GetCredentialsUseCase $getCredentialsUseCase,
        private DisableCredentialUseCase $disableCredentialUseCase,
        private EnableCredentialUseCase $enableCredentialUseCase,
        private UpdateCredentialAliasUseCase $updateCredentialAliasUseCase,
        private DeleteCredentialUseCase $deleteCredentialUseCase
    ) {}

    /**
     * 認証されたユーザーの WebAuthn 認証器一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $credentials = $this->getCredentialsUseCase->execute($request->user());

            return response()->json([
                'success' => true,
                'credentials' => $credentials,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.webauthn_credentials_fetch_failed'),
            ], 500);
        }
    }

    /**
     * WebAuthn 認証器を無効化
     */
    public function disable(Request $request, string $credentialId): JsonResponse
    {
        try {
            $this->disableCredentialUseCase->execute($request->user(), $credentialId);

            return response()->json([
                'success' => true,
                'message' => __('auth.webauthn_credential_disabled'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.webauthn_credential_disable_failed'),
            ], 500);
        }
    }

    /**
     * WebAuthn 認証器を有効化
     */
    public function enable(Request $request, string $credentialId): JsonResponse
    {
        try {
            $this->enableCredentialUseCase->execute($request->user(), $credentialId);

            return response()->json([
                'success' => true,
                'message' => __('auth.webauthn_credential_enabled'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.webauthn_credential_enable_failed'),
            ], 500);
        }
    }

    /**
     * WebAuthn 認証器のエイリアスを更新
     */
    public function updateAlias(Request $request, string $credentialId): JsonResponse
    {
        $request->validate([
            'alias' => 'required|string|max:255',
        ]);

        try {
            $credential = $this->updateCredentialAliasUseCase->execute(
                $request->user(),
                $credentialId,
                $request->alias
            );

            return response()->json([
                'success' => true,
                'message' => __('auth.webauthn_alias_updated'),
                'credential' => $credential,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.webauthn_alias_update_failed'),
            ], 500);
        }
    }

    /**
     * WebAuthn 認証器を完全に削除
     */
    public function destroy(Request $request, string $credentialId): JsonResponse
    {
        try {
            $this->deleteCredentialUseCase->execute($request->user(), $credentialId);

            return response()->json([
                'success' => true,
                'message' => __('auth.webauthn_credential_deleted'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.webauthn_credential_delete_failed'),
            ], 500);
        }
    }
}