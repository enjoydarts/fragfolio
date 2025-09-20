<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebAuthnController extends Controller
{
    public function registerBegin(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = auth()->user() ?? \App\Models\User::find($request->user_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザーが見つかりません',
            ], 404);
        }

        try {
            // WebAuthn登録用のチャレンジを生成
            $challenge = Str::random(32);

            // セッションまたはキャッシュにチャレンジを保存
            session(['webauthn_challenge' => $challenge]);

            $publicKeyCredentialCreationOptions = [
                'rp' => [
                    'name' => config('app.name'),
                    'id' => parse_url(config('app.url'), PHP_URL_HOST),
                ],
                'user' => [
                    'id' => base64url_encode($user instanceof \App\Models\User ? $user->id : $user->first()?->id),
                    'name' => $user instanceof \App\Models\User ? $user->email : $user->first()?->email,
                    'displayName' => $user instanceof \App\Models\User ? ($user->name ?? $user->email) : ($user->first()->name ?? $user->first()->email),
                ],
                'challenge' => base64url_encode($challenge),
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7], // ES256
                    ['type' => 'public-key', 'alg' => -257], // RS256
                ],
                'timeout' => 60000,
                'attestation' => 'direct',
                'authenticatorSelection' => [
                    'authenticatorAttachment' => 'platform',
                    'userVerification' => 'preferred',
                    'requireResidentKey' => false,
                ],
                'excludeCredentials' => $this->getExistingCredentials($user->id),
            ];

            return response()->json([
                'success' => true,
                'publicKey' => $publicKeyCredentialCreationOptions,
            ]);

        } catch (\Exception $e) {
            Log::error('WebAuthn register begin failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebAuthn登録の開始に失敗しました',
            ], 500);
        }
    }

    public function registerComplete(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'response' => 'required|array',
            'response.clientDataJSON' => 'required|string',
            'response.attestationObject' => 'required|string',
            'type' => 'required|string|in:public-key',
        ]);

        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => '認証が必要です',
            ], 401);
        }

        try {
            $challenge = session('webauthn_challenge');
            if (! $challenge) {
                return response()->json([
                    'success' => false,
                    'message' => 'チャレンジが見つかりません',
                ], 400);
            }

            // 簡略化した検証（実際のプロダクションでは適切なWebAuthnライブラリを使用）
            $credentialId = $request->input('id');
            $clientDataJSON = base64_decode($request->input('response.clientDataJSON'));
            $attestationObject = base64_decode($request->input('response.attestationObject'));

            // 基本的な検証
            $clientData = json_decode($clientDataJSON, true);
            if ($clientData['type'] !== 'webauthn.create') {
                throw new \Exception('Invalid client data type');
            }

            // 認証情報をデータベースに保存
            DB::table('webauthn_credentials')->insert([
                'user_id' => $user->id,
                'credential_id' => $credentialId,
                'public_key' => base64_encode($attestationObject), // 実際は公開鍵を抽出
                'counter' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // セッションからチャレンジを削除
            session()->forget('webauthn_challenge');

            return response()->json([
                'success' => true,
                'message' => 'WebAuthn認証情報が正常に登録されました',
            ]);

        } catch (\Exception $e) {
            Log::error('WebAuthn register complete failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebAuthn登録に失敗しました',
            ], 500);
        }
    }

    public function authenticateBegin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $user = \App\Models\User::where('email', $request->email)->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ユーザーが見つかりません',
                ], 404);
            }

            $challenge = Str::random(32);
            session(['webauthn_auth_challenge' => $challenge, 'webauthn_user_id' => $user->id]);

            $publicKeyCredentialRequestOptions = [
                'challenge' => base64url_encode($challenge),
                'timeout' => 60000,
                'rpId' => parse_url(config('app.url'), PHP_URL_HOST),
                'allowCredentials' => $this->getExistingCredentials($user->id),
                'userVerification' => 'preferred',
            ];

            return response()->json([
                'success' => true,
                'publicKey' => $publicKeyCredentialRequestOptions,
            ]);

        } catch (\Exception $e) {
            Log::error('WebAuthn authenticate begin failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebAuthn認証の開始に失敗しました',
            ], 500);
        }
    }

    public function authenticateComplete(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'response' => 'required|array',
            'response.clientDataJSON' => 'required|string',
            'response.authenticatorData' => 'required|string',
            'response.signature' => 'required|string',
            'type' => 'required|string|in:public-key',
        ]);

        try {
            $challenge = session('webauthn_auth_challenge');
            $userId = session('webauthn_user_id');

            if (! $challenge || ! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'チャレンジまたはユーザー情報が見つかりません',
                ], 400);
            }

            $user = \App\Models\User::find($userId);
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ユーザーが見つかりません',
                ], 404);
            }

            $credentialId = $request->input('id');

            // 認証情報が存在するか確認
            $credential = DB::table('webauthn_credentials')
                ->where('user_id', $userId)
                ->where('credential_id', $credentialId)
                ->first();

            if (! $credential) {
                return response()->json([
                    'success' => false,
                    'message' => '認証情報が見つかりません',
                ], 404);
            }

            // 簡略化した検証（実際のプロダクションでは適切な署名検証が必要）
            $clientDataJSON = base64_decode($request->input('response.clientDataJSON'));
            $clientData = json_decode($clientDataJSON, true);

            if ($clientData['type'] !== 'webauthn.get') {
                throw new \Exception('Invalid client data type');
            }

            // 認証成功時の処理
            $token = $user->createToken('webauthn')->plainTextToken;

            // セッションをクリア
            session()->forget(['webauthn_auth_challenge', 'webauthn_user_id']);

            return response()->json([
                'success' => true,
                'message' => 'WebAuthn認証が成功しました',
                'token' => $token,
                'user' => $user->load('profile'),
            ]);

        } catch (\Exception $e) {
            Log::error('WebAuthn authenticate complete failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebAuthn認証に失敗しました',
            ], 500);
        }
    }

    private function getExistingCredentials(int $userId): array
    {
        $credentials = DB::table('webauthn_credentials')
            ->where('user_id', $userId)
            ->get(['credential_id']);

        return $credentials->map(function ($credential) {
            return [
                'type' => 'public-key',
                'id' => $credential->credential_id,
            ];
        })->toArray();
    }
}

// Helper function for base64url encoding
if (! function_exists('base64url_encode')) {
    function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (! function_exists('base64url_decode')) {
    function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
