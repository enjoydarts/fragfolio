<?php

use App\Http\Controllers\Api\AI\CompletionController;
use App\Http\Controllers\Api\AI\CostController;
use App\Http\Controllers\Api\AI\NormalizationController;
use App\Http\Controllers\Api\AI\NoteSuggestionController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\TwoFactorLoginController;
use App\Http\Controllers\Api\Auth\WebAuthnManagementController;
use App\Http\Controllers\Api\FragranceNormalizationController;
use App\Http\Controllers\Api\TurnstileController;
use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes - 既存認証を段階的にFortifyに移行
Route::prefix('auth')->group(function () {
    // Fortifyに移行済み - 以下のルートは無効化
    // Route::post('/register', [AuthController::class, 'register']);
    // Route::post('/login', [AuthController::class, 'login']);
    // Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    // Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Email verification route
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailFromLink'])
        ->middleware(['signed'])
        ->name('verification.verify');

    // Email change verification route (public access)
    Route::get('/email/verify-change/{token}', [AuthController::class, 'verifyEmailChange']);

    // 2FA login verification (public access - session based)
    Route::post('/two-factor-challenge', [TwoFactorLoginController::class, 'verify']);
    Route::post('/two-factor-webauthn', [TwoFactorLoginController::class, 'verifyWithWebAuthn']);
    Route::post('/two-factor-webauthn/complete', [TwoFactorLoginController::class, 'completeWebAuthnTwoFactor']);

    // WebAuthn login routes (public access)
    Route::prefix('webauthn')->group(function () {
        Route::post('/login/options', [WebAuthnLoginController::class, 'options']);
        Route::post('/login', [WebAuthnLoginController::class, 'login']);
    });
});

// Turnstile configuration
Route::get('/turnstile/config', [TurnstileController::class, 'config']);

// AI routes
Route::prefix('ai')->group(function () {
    // Public routes for auto-completion (no authentication required for basic completion)
    Route::post('/complete', [CompletionController::class, 'complete']);
    Route::post('/batch-complete', [CompletionController::class, 'batchComplete']);
    Route::get('/providers', [CompletionController::class, 'providers']);
    Route::get('/health', [CompletionController::class, 'health']);

    // New normalization routes
    Route::post('/normalize', [NormalizationController::class, 'normalize']);
    Route::post('/batch-normalize', [NormalizationController::class, 'batchNormalize']);
    Route::get('/normalization/providers', [NormalizationController::class, 'providers']);
    Route::get('/normalization/health', [NormalizationController::class, 'health']);

    // Note suggestion routes
    Route::post('/suggest-notes', [NoteSuggestionController::class, 'suggest']);
    Route::post('/batch-suggest-notes', [NoteSuggestionController::class, 'batchSuggest']);
    Route::get('/note-suggestion/providers', [NoteSuggestionController::class, 'providers']);
    Route::get('/note-suggestion/health', [NoteSuggestionController::class, 'health']);
    Route::get('/note-categories', [NoteSuggestionController::class, 'noteCategories']);
    Route::post('/similar-fragrances', [NoteSuggestionController::class, 'similarFragrances']);

    // Legacy normalization route (for backward compatibility)
    Route::post('/legacy-normalize', [FragranceNormalizationController::class, 'normalize']);
    Route::get('/legacy-providers', [FragranceNormalizationController::class, 'getAvailableProviders']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user()->load('profile');
    });

    Route::prefix('auth')->group(function () {
        // Fortifyで提供されるため、以下をコメントアウト
        // Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);

        // Password change route
        Route::put('/password', [AuthController::class, 'changePassword']);

        // Email change request routes
        Route::post('/email/change-request', [AuthController::class, 'requestEmailChange']);

        // Custom 2FA routes with Sanctum authentication
        Route::get('/two-factor-status', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'status']);
        Route::post('/two-factor-authentication', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'enable']);
        Route::delete('/two-factor-authentication', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'disable']);
        Route::post('/confirmed-two-factor-authentication', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'confirm']);
        Route::get('/two-factor-qr-code', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'qrCode']);
        Route::get('/two-factor-secret-key', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'secretKey']);
        Route::get('/two-factor-recovery-codes', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'recoveryCodes']);
        Route::post('/two-factor-recovery-codes', [\App\Http\Controllers\Api\TwoFactorAuthController::class, 'regenerateRecoveryCodes']);

        // WebAuthn management for authenticated users
        Route::prefix('webauthn')->group(function () {
            Route::post('/register/options', [WebAuthnRegisterController::class, 'options']);
            Route::post('/register', [WebAuthnRegisterController::class, 'register']);
            Route::get('/credentials', [WebAuthnManagementController::class, 'index']);
            Route::delete('/credentials/{credentialId}', [WebAuthnManagementController::class, 'destroy']);
            Route::post('/credentials/{credentialId}/disable', [WebAuthnManagementController::class, 'disable']);
            Route::post('/credentials/{credentialId}/enable', [WebAuthnManagementController::class, 'enable']);
            Route::put('/credentials/{credentialId}', [WebAuthnManagementController::class, 'updateAlias']);
        });
    });

    // AI normalization history
    Route::get('/ai/history', [FragranceNormalizationController::class, 'getNormalizationHistory']);

    // AI feedback routes (authenticated)
    Route::post('/ai/normalization/feedback/{user}', [NormalizationController::class, 'feedback']);
    Route::post('/ai/note-suggestion/feedback/{user}', [NoteSuggestionController::class, 'feedback']);

    // AI cost management routes (authenticated)
    Route::prefix('ai/cost')->group(function () {
        Route::get('/usage', [CostController::class, 'usage']);
        Route::get('/limits', [CostController::class, 'limits']);
        Route::get('/patterns', [CostController::class, 'patterns']);
        Route::get('/efficiency', [CostController::class, 'efficiency']);
        Route::get('/prediction', [CostController::class, 'prediction']);
        Route::get('/history', [CostController::class, 'history']);
        Route::post('/report', [CostController::class, 'generateReport']);

        // Admin only routes - Note: Admin check is now handled in the controller
        Route::get('/global-stats', [CostController::class, 'globalStats']);
        Route::get('/top-users', [CostController::class, 'topUsers']);
    });
});
