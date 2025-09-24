<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\UseCases\TwoFactor\ConfirmTwoFactorUseCase;
use App\UseCases\TwoFactor\DisableTwoFactorUseCase;
use App\UseCases\TwoFactor\EnableTwoFactorUseCase;
use App\UseCases\TwoFactor\GenerateQrCodeUseCase;
use App\UseCases\TwoFactor\GetRecoveryCodesUseCase;
use App\UseCases\TwoFactor\RegenerateRecoveryCodesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthController extends Controller
{
    public function __construct(
        private EnableTwoFactorUseCase $enableTwoFactorUseCase,
        private DisableTwoFactorUseCase $disableTwoFactorUseCase,
        private ConfirmTwoFactorUseCase $confirmTwoFactorUseCase,
        private GenerateQrCodeUseCase $generateQrCodeUseCase,
        private GetRecoveryCodesUseCase $getRecoveryCodesUseCase,
        private RegenerateRecoveryCodesUseCase $regenerateRecoveryCodesUseCase
    ) {}

    /**
     * 2FA状態取得
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => (bool) $user->two_factor_secret,
            'confirmed' => (bool) $user->two_factor_confirmed_at,
            'has_recovery_codes' => (bool) $user->two_factor_recovery_codes,
        ]);
    }

    /**
     * 2FA有効化
     */
    public function enable(Request $request): JsonResponse
    {
        try {
            $result = $this->enableTwoFactorUseCase->execute($request->user());

            return response()->json([
                'success' => true,
                'secret' => $result['secret'],
                'qr_code_url' => $result['qr_code_url'],
                'message' => __('auth.two_factor_setup_started'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 2FA確認（有効化完了）
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|min:6|max:6',
        ]);

        try {
            $result = $this->confirmTwoFactorUseCase->execute($request->user(), $request->code);

            return response()->json([
                'success' => true,
                'recovery_codes' => $result['recovery_codes'],
                'message' => __('auth.two_factor_enabled'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('auth.two_factor_code_invalid'),
            ], 422);
        }
    }

    /**
     * 2FA無効化
     */
    public function disable(Request $request): JsonResponse
    {
        try {
            $this->disableTwoFactorUseCase->execute($request->user());

            return response()->json([
                'success' => true,
                'message' => __('auth.two_factor_disabled'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * QRコード生成
     */
    public function qrCode(Request $request): Response|JsonResponse
    {
        try {
            $qrCodeSvg = $this->generateQrCodeUseCase->execute($request->user());

            return response($qrCodeSvg)->header('Content-Type', 'image/svg+xml');
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * シークレットキー取得
     */
    public function secretKey(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => __('auth.two_factor_not_enabled'),
            ], 422);
        }

        return response()->json([
            'secret_key' => decrypt($user->two_factor_secret),
        ]);
    }

    /**
     * リカバリコード取得
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        try {
            $codes = $this->getRecoveryCodesUseCase->execute($request->user());

            return response()->json([
                'success' => true,
                'recovery_codes' => $codes,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * リカバリコード再生成
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        try {
            $codes = $this->regenerateRecoveryCodesUseCase->execute($request->user());

            return response()->json([
                'success' => true,
                'recovery_codes' => $codes,
                'message' => __('auth.recovery_codes_regenerated'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
