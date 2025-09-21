<?php

use App\Http\Controllers\Api\Auth\AuthController;
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

    // WebAuthn routes (Laragear/WebAuthn)
    Route::prefix('webauthn')->group(function () {
        Route::post('/register/options', [WebAuthnRegisterController::class, 'options']);
        Route::post('/register', [WebAuthnRegisterController::class, 'register']);
        Route::post('/login/options', [WebAuthnLoginController::class, 'options']);
        Route::post('/login', [WebAuthnLoginController::class, 'login']);
    });
});

// Turnstile configuration
Route::get('/turnstile/config', [TurnstileController::class, 'config']);

// AI normalization routes (public for auto-completion)
Route::prefix('ai')->group(function () {
    Route::post('/normalize', [FragranceNormalizationController::class, 'normalize']);
    Route::get('/providers', [FragranceNormalizationController::class, 'getAvailableProviders']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user()->load('profile');
    });

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);

        // WebAuthn management for authenticated users
        Route::prefix('webauthn')->group(function () {
            Route::post('/register/options', [WebAuthnRegisterController::class, 'options']);
            Route::post('/register', [WebAuthnRegisterController::class, 'register']);
            Route::get('/credentials', [WebAuthnManagementController::class, 'index']);
            Route::delete('/credentials/{credentialId}', [WebAuthnManagementController::class, 'disable']);
            Route::put('/credentials/{credentialId}', [WebAuthnManagementController::class, 'updateAlias']);
        });
    });

    // AI normalization history
    Route::get('/ai/history', [FragranceNormalizationController::class, 'getNormalizationHistory']);
});
