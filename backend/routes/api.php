<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\WebAuthnController;
use App\Http\Controllers\Api\FragranceNormalizationController;
use App\Http\Controllers\Api\TurnstileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Email verification route
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailFromLink'])
        ->middleware(['signed'])
        ->name('verification.verify');

    // WebAuthn routes
    Route::prefix('webauthn')->group(function () {
        Route::post('/register/begin', [WebAuthnController::class, 'registerBegin']);
        Route::post('/register/complete', [WebAuthnController::class, 'registerComplete']);
        Route::post('/authenticate/begin', [WebAuthnController::class, 'authenticateBegin']);
        Route::post('/authenticate/complete', [WebAuthnController::class, 'authenticateComplete']);
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
    });

    // AI normalization history
    Route::get('/ai/history', [FragranceNormalizationController::class, 'getNormalizationHistory']);
});
