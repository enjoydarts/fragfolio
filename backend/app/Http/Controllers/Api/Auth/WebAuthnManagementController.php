<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class WebAuthnManagementController extends Controller
{
    /**
     * 認証されたユーザーの WebAuthn 認証器一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $credentials = WebAuthnCredential::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->whereNull('disabled_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($credential) {
                return [
                    'id' => $credential->id,
                    'alias' => $credential->alias,
                    'created_at' => $credential->created_at,
                    'disabled_at' => $credential->disabled_at,
                ];
            });

        return response()->json([
            'success' => true,
            'credentials' => $credentials,
        ]);
    }

    /**
     * WebAuthn 認証器を無効化
     */
    public function disable(Request $request, string $credentialId): JsonResponse
    {
        $user = $request->user();

        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->whereNull('disabled_at')
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => '指定された認証器が見つかりません',
            ], 404);
        }

        $credential->update([
            'disabled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '認証器を無効化しました',
        ]);
    }

    /**
     * WebAuthn 認証器のエイリアスを更新
     */
    public function updateAlias(Request $request, string $credentialId): JsonResponse
    {
        $request->validate([
            'alias' => 'required|string|max:255',
        ]);

        $user = $request->user();

        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->whereNull('disabled_at')
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => '指定された認証器が見つかりません',
            ], 404);
        }

        $credential->update([
            'alias' => $request->alias,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'エイリアスを更新しました',
        ]);
    }

    /**
     * WebAuthn 認証器を完全に削除
     */
    public function destroy(Request $request, string $credentialId): JsonResponse
    {
        $user = $request->user();

        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => '指定された認証器が見つかりません',
            ], 404);
        }

        $credential->delete();

        return response()->json([
            'success' => true,
            'message' => '認証器を削除しました',
        ]);
    }
}
